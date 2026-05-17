<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamRegistration;
use App\Models\ExamRoom;
use App\Models\ExamScore;
use App\Models\ExamSession;
use App\Models\ProctorAssignment;
use App\Models\RoomComputer;
use App\Models\SeatAssignment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class IoeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['scores.enter', 'scores.verify', 'scores.lock', 'scores.unlock', 'checkins.manage', 'incidents.manage'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'proctor', 'guard_name' => 'web'])->syncPermissions(['scores.enter', 'checkins.manage', 'incidents.manage']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'])->syncPermissions(Permission::all());
    }

    public function test_student_can_create_account_from_imported_student(): void
    {
        $student = Student::factory()->create(['class_name' => '10A1', 'student_code' => 'HS001']);

        $this->post('/register', [
            'class_name' => '10A1',
            'credential' => 'HS001',
            'email' => 'student@example.test',
            'password' => 'abc12345',
            'password_confirmation' => 'abc12345',
        ])->assertRedirect(route('student.dashboard', absolute: false));

        $this->assertDatabaseHas('users', [
            'student_id' => $student->id,
            'role' => 'student',
            'email' => 'student@example.test',
        ]);
    }

    public function test_student_can_register_for_school_ioe_exam(): void
    {
        [$student, $user] = $this->studentUser();
        $exam = Exam::factory()->create();
        $session = $this->availableSession($exam, $student);

        $this->actingAs($user)->post(route('student.registrations.store', $exam), $this->registrationPayload($student, [
            'exam_session_id' => $session->id,
        ]))
            ->assertRedirect();

        $this->assertDatabaseHas('exam_registrations', [
            'student_id' => $student->id,
            'exam_id' => $exam->id,
            'ioe_id' => 'ioe-001',
            'exam_session_id' => $session->id,
        ]);
    }

    public function test_registration_rejects_duplicate_ioe_id_in_same_exam(): void
    {
        [$student, $user] = $this->studentUser();
        $exam = Exam::factory()->create();
        $session = $this->availableSession($exam, $student);
        ExamRegistration::factory()->create(['exam_id' => $exam->id, 'ioe_id' => 'ioe-001']);

        $this->actingAs($user)->post(route('student.registrations.store', $exam), $this->registrationPayload($student, [
            'exam_session_id' => $session->id,
        ]))
            ->assertSessionHasErrors('ioe_id');
    }

    public function test_registration_rejects_duplicate_identity_in_same_exam(): void
    {
        [$student, $user] = $this->studentUser();
        $exam = Exam::factory()->create();
        $session = $this->availableSession($exam, $student);
        ExamRegistration::factory()->create(['exam_id' => $exam->id, 'identity_number' => $student->identity_number]);

        $payload = $this->registrationPayload($student, ['exam_session_id' => $session->id]);
        $payload['ioe_id'] = 'ioe-unique';

        $this->actingAs($user)->post(route('student.registrations.store', $exam), $payload)
            ->assertSessionHasErrors('identity_number');
    }

    public function test_student_cannot_view_another_students_registration(): void
    {
        [, $user] = $this->studentUser();
        $other = ExamRegistration::factory()->create();

        $this->actingAs($user)->get(route('student.registrations.show', $other))
            ->assertForbidden();
    }

    public function test_proctor_only_sees_assigned_session_and_room(): void
    {
        $proctor = $this->roleUser('proctor');
        [$assigned, $unassigned] = [$this->assignmentFixture('Nguyễn Văn A'), $this->assignmentFixture('Trần Văn B')];
        ProctorAssignment::create([
            'user_id' => $proctor->id,
            'exam_session_id' => $assigned->exam_session_id,
            'exam_room_id' => $assigned->exam_room_id,
        ]);

        $response = $this->actingAs($proctor)->get(route('proctor.checkins.index'));

        $response->assertOk()->assertSee('Nguyễn Văn A')->assertDontSee('Trần Văn B');
    }

    public function test_database_rejects_duplicate_computer_in_same_session_room(): void
    {
        $assignment = $this->assignmentFixture('Nguyễn Văn A');
        $other = ExamRegistration::factory()->create(['exam_id' => $assignment->session->exam_id]);

        $this->expectException(QueryException::class);

        SeatAssignment::create([
            'exam_registration_id' => $other->id,
            'exam_session_id' => $assignment->exam_session_id,
            'exam_room_id' => $assignment->exam_room_id,
            'seat_type' => 'school_computer',
            'computer_id' => $assignment->computer_id,
            'computer_number' => $assignment->computer_number,
            'candidate_number' => 99,
            'assignment_method' => 'manual',
            'status' => 'assigned',
        ]);
    }

    public function test_score_can_be_entered_and_locked(): void
    {
        $admin = $this->roleUser('admin');
        $assignment = $this->assignmentFixture('Nguyễn Văn A');

        $this->actingAs($admin)->post(route('admin.scores.store', $assignment->registration), [
            'official_score' => 920,
            'exam_status' => 'completed',
        ])->assertRedirect();

        $score = ExamScore::first();
        $this->actingAs($admin)->post(route('admin.scores.lock', $score))->assertRedirect();

        $this->assertDatabaseHas('exam_scores', [
            'id' => $score->id,
            'official_score' => 920,
            'score_status' => 'locked',
        ]);
    }

    public function test_proctor_cannot_edit_locked_score(): void
    {
        $proctor = $this->roleUser('proctor');
        $admin = $this->roleUser('admin');
        $assignment = $this->assignmentFixture('Nguyễn Văn A');
        ProctorAssignment::create([
            'user_id' => $proctor->id,
            'exam_session_id' => $assignment->exam_session_id,
            'exam_room_id' => $assignment->exam_room_id,
        ]);
        $score = ExamScore::create([
            'exam_registration_id' => $assignment->exam_registration_id,
            'seat_assignment_id' => $assignment->id,
            'official_score' => 900,
            'exam_status' => 'completed',
            'score_status' => 'locked',
            'locked_by' => $admin->id,
            'locked_at' => now(),
        ]);

        $this->actingAs($proctor)->post(route('proctor.scores.store', $assignment->registration), [
            'official_score' => 950,
            'exam_status' => 'completed',
            'reason' => 'Sửa thử',
            'locked_change' => 1,
        ])->assertSessionHasErrors('score');

        $this->assertSame('900.00', $score->refresh()->official_score);
    }

    public function test_registration_validation_rejects_invalid_phone(): void
    {
        [$student, $user] = $this->studentUser();
        $exam = Exam::factory()->create();
        $session = $this->availableSession($exam, $student);
        $payload = $this->registrationPayload($student, ['exam_session_id' => $session->id]);
        $payload['phone'] = '123456';

        $this->actingAs($user)->post(route('student.registrations.store', $exam), $payload)
            ->assertSessionHasErrors('phone');
    }

    private function studentUser(): array
    {
        $student = Student::factory()->create(['class_name' => '10A1']);
        $user = User::factory()->create(['role' => 'student', 'student_id' => $student->id, 'username' => $student->student_code]);
        $user->assignRole('student');

        return [$student, $user];
    }

    private function roleUser(string $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        $user->assignRole($role);

        return $user;
    }

    private function assignmentFixture(string $name): SeatAssignment
    {
        $exam = Exam::factory()->create();
        $room = ExamRoom::factory()->create();
        $computer = RoomComputer::factory()->create(['exam_room_id' => $room->id, 'computer_label' => 'Máy '.fake()->unique()->numberBetween(1, 999)]);
        $session = ExamSession::factory()->create(['exam_id' => $exam->id, 'exam_room_id' => $room->id]);
        $student = Student::factory()->create(['full_name' => $name]);
        $registration = ExamRegistration::factory()->create(['exam_id' => $exam->id, 'student_id' => $student->id, 'full_name' => $name, 'identity_number' => $student->identity_number]);

        return SeatAssignment::create([
            'exam_registration_id' => $registration->id,
            'exam_session_id' => $session->id,
            'exam_room_id' => $room->id,
            'seat_type' => 'school_computer',
            'computer_id' => $computer->id,
            'computer_number' => $computer->computer_number,
            'candidate_number' => 1,
            'assignment_method' => 'manual',
            'status' => 'assigned',
        ]);
    }

    private function availableSession(Exam $exam, Student $student): ExamSession
    {
        return ExamSession::factory()->create([
            'exam_id' => $exam->id,
            'target_grade' => $student->grade,
            'target_classes' => null,
            'max_candidates' => 25,
            'status' => 'open',
        ]);
    }

    private function registrationPayload(Student $student, array $overrides = []): array
    {
        return array_merge([
            'full_name' => $student->full_name,
            'ioe_id' => 'ioe-001',
            'date_of_birth' => $student->date_of_birth->format('Y-m-d'),
            'gender' => $student->gender,
            'identity_number' => $student->identity_number,
            'class_name' => $student->class_name,
            'address' => $student->address,
            'phone' => '0912345678',
            'email' => 'student@example.test',
            'uses_personal_computer' => 0,
            'note' => 'Đăng ký test',
            'confirm_information' => 1,
        ], $overrides);
    }
}
