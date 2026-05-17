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
use App\Services\StudentImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;
use ZipArchive;

class IoeSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissions() as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'proctor', 'guard_name' => 'web'])->syncPermissions(['checkins.manage', 'incidents.manage', 'scores.enter']);
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web'])->syncPermissions(['students.export', 'scores.enter']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'])->syncPermissions(Permission::all());
    }

    public function test_proctor_cannot_see_or_update_another_room_in_same_session(): void
    {
        [$proctor, $assigned, $otherRoom] = $this->sameSessionDifferentRooms();

        $response = $this->actingAs($proctor)->get(route('proctor.checkins.index'));

        $response->assertOk()
            ->assertSee($assigned->registration->full_name)
            ->assertDontSee($otherRoom->registration->full_name);

        $this->actingAs($proctor)
            ->patch(route('proctor.checkins.update', $otherRoom), ['status' => 'present'])
            ->assertForbidden();
    }

    public function test_proctor_cannot_enter_score_or_incident_outside_assigned_room(): void
    {
        [$proctor, , $otherRoom] = $this->sameSessionDifferentRooms();

        $this->actingAs($proctor)
            ->post(route('proctor.scores.store', $otherRoom->registration), [
                'official_score' => 900,
                'exam_status' => 'completed',
            ])
            ->assertForbidden();

        $this->actingAs($proctor)
            ->post(route('proctor.incidents.store'), [
                'seat_assignment_id' => $otherRoom->id,
                'incident_type' => 'Máy tính lỗi',
                'description' => 'Không khởi động được trình duyệt.',
            ])
            ->assertForbidden();
    }

    public function test_teacher_without_lock_permission_cannot_lock_score(): void
    {
        $teacher = $this->roleUser('teacher');
        $score = ExamScore::create([
            'exam_registration_id' => $this->assignmentFixture()->exam_registration_id,
            'official_score' => 910,
            'exam_status' => 'completed',
            'score_status' => 'entered',
        ]);

        $this->actingAs($teacher)
            ->post(route('admin.scores.lock', $score))
            ->assertForbidden();
    }

    public function test_student_does_not_see_score_before_publication(): void
    {
        $exam = Exam::factory()->create(['publish_scores' => false]);
        $student = Student::factory()->create();
        $user = User::factory()->create(['role' => 'student', 'student_id' => $student->id, 'username' => $student->student_code]);
        $user->assignRole('student');
        $registration = ExamRegistration::factory()->create(['exam_id' => $exam->id, 'student_id' => $student->id, 'identity_number' => $student->identity_number]);
        ExamScore::create([
            'exam_registration_id' => $registration->id,
            'official_score' => 900,
            'exam_status' => 'completed',
            'score_status' => 'locked',
        ]);

        $this->actingAs($user)
            ->get(route('student.registrations.show', $registration))
            ->assertOk()
            ->assertDontSee('Điểm thi');
    }

    public function test_registration_update_rejects_duplicate_ioe_id_in_own_exam(): void
    {
        $exam = Exam::factory()->create();
        $student = Student::factory()->create(['class_name' => '10A1']);
        $user = User::factory()->create(['role' => 'student', 'student_id' => $student->id, 'username' => $student->student_code]);
        $user->assignRole('student');
        $session = ExamSession::factory()->create([
            'exam_id' => $exam->id,
            'target_grade' => $student->grade,
            'status' => 'open',
        ]);
        $registration = ExamRegistration::factory()->create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'identity_number' => $student->identity_number,
            'exam_session_id' => $session->id,
        ]);
        ExamRegistration::factory()->create(['exam_id' => $exam->id, 'ioe_id' => 'duplicate-ioe']);

        $payload = $this->registrationPayload($student, [
            'exam_session_id' => $session->id,
            'ioe_id' => 'duplicate-ioe',
        ]);

        $this->actingAs($user)
            ->put(route('student.registrations.update', $registration), $payload)
            ->assertSessionHasErrors('ioe_id');
    }

    public function test_student_outside_exam_target_cannot_register_even_when_session_allows_all(): void
    {
        $exam = Exam::factory()->create(['target_grades' => [10, 11, 12]]);
        $student = Student::factory()->create(['grade' => 9, 'class_name' => '9A1']);
        $user = User::factory()->create(['role' => 'student', 'student_id' => $student->id, 'username' => $student->student_code]);
        $user->assignRole('student');
        $session = ExamSession::factory()->create([
            'exam_id' => $exam->id,
            'target_grade' => null,
            'target_classes' => null,
            'status' => 'open',
        ]);

        $this->actingAs($user)
            ->post(route('student.registrations.store', $exam), $this->registrationPayload($student, [
                'exam_session_id' => $session->id,
            ]))
            ->assertSessionHasErrors('exam_session_id');
    }

    public function test_student_cannot_update_registration_to_session_outside_own_grade(): void
    {
        $exam = Exam::factory()->create();
        $student = Student::factory()->create(['grade' => 10, 'class_name' => '10A1']);
        $user = User::factory()->create(['role' => 'student', 'student_id' => $student->id, 'username' => $student->student_code]);
        $user->assignRole('student');
        $currentSession = ExamSession::factory()->create([
            'exam_id' => $exam->id,
            'target_grade' => 10,
            'status' => 'open',
        ]);
        $wrongSession = ExamSession::factory()->create([
            'exam_id' => $exam->id,
            'target_grade' => 11,
            'status' => 'open',
        ]);
        $registration = ExamRegistration::factory()->create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'identity_number' => $student->identity_number,
            'class_name' => $student->class_name,
            'exam_session_id' => $currentSession->id,
            'ioe_id' => 'ioe-current',
        ]);

        $this->actingAs($user)
            ->put(route('student.registrations.update', $registration), $this->registrationPayload($student, [
                'exam_session_id' => $wrongSession->id,
                'ioe_id' => 'ioe-current',
            ]))
            ->assertSessionHasErrors('exam_session_id');
    }

    public function test_second_student_cannot_register_when_session_capacity_is_full(): void
    {
        $exam = Exam::factory()->create();
        $studentA = Student::factory()->create(['grade' => 12, 'class_name' => '12A6']);
        $studentB = Student::factory()->create(['grade' => 12, 'class_name' => '12A7']);
        $userA = User::factory()->create(['role' => 'student', 'student_id' => $studentA->id, 'username' => $studentA->student_code]);
        $userB = User::factory()->create(['role' => 'student', 'student_id' => $studentB->id, 'username' => $studentB->student_code]);
        $userA->assignRole('student');
        $userB->assignRole('student');
        $session = ExamSession::factory()->create([
            'exam_id' => $exam->id,
            'target_grade' => 12,
            'max_candidates' => 1,
            'status' => 'open',
        ]);

        $this->actingAs($userA)
            ->post(route('student.registrations.store', $exam), $this->registrationPayload($studentA, [
                'exam_session_id' => $session->id,
                'ioe_id' => 'ioe-a',
            ]))
            ->assertRedirect();

        $this->actingAs($userB)
            ->post(route('student.registrations.store', $exam), $this->registrationPayload($studentB, [
                'exam_session_id' => $session->id,
                'ioe_id' => 'ioe-b',
            ]))
            ->assertSessionHasErrors('exam_session_id');
    }

    public function test_restore_rechecks_capacity_for_cancelled_registration(): void
    {
        $admin = $this->roleUser('admin');
        $exam = Exam::factory()->create();
        $session = ExamSession::factory()->create([
            'exam_id' => $exam->id,
            'max_candidates' => 1,
            'status' => 'open',
        ]);
        ExamRegistration::factory()->create([
            'exam_id' => $exam->id,
            'exam_session_id' => $session->id,
            'status' => 'approved',
        ]);
        $cancelled = ExamRegistration::factory()->create([
            'exam_id' => $exam->id,
            'exam_session_id' => $session->id,
            'status' => 'cancelled',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.registrations.restore', $cancelled))
            ->assertSessionHasErrors('exam_session_id');

        $this->assertSame('cancelled', $cancelled->refresh()->status);
    }

    public function test_settings_checkboxes_can_be_saved_false(): void
    {
        $admin = $this->roleUser('admin');
        $exam = Exam::factory()->create([
            'require_session_choice' => true,
            'auto_lock_full_sessions' => true,
            'show_countdown' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), [
                'school' => ['name' => 'Trường THPT Võ Văn Kiệt', 'address' => '', 'website' => ''],
                'site' => [
                    'site_name' => 'IOE cấp trường',
                    'contest_name' => 'Đăng ký dự thi Olympic Tiếng Anh trên Internet cấp trường',
                    'school_year' => '2025-2026',
                    'home_description' => 'Hệ thống đăng ký IOE cấp trường.',
                    'guide_content' => '',
                ],
                'contact' => ['teacher_name' => '', 'phone' => '', 'email' => '', 'note' => ''],
                'exam' => [
                    'name' => $exam->name,
                    'school_year' => '2025-2026',
                    'registration_opens_at' => now()->subDay()->format('Y-m-d H:i:s'),
                    'registration_closes_at' => now()->addDay()->format('Y-m-d H:i:s'),
                    'exam_date' => now()->addWeek()->toDateString(),
                    'exam_time' => '07:00',
                    'countdown_mode' => 'auto',
                    'status' => 'open',
                    'description' => 'Kỳ đăng ký IOE cấp trường.',
                ],
                'options' => [
                    'allow_student_edit' => '0',
                    'allow_student_session_change' => '0',
                    'require_session_choice' => '0',
                    'allow_personal_computer' => '0',
                    'auto_lock_full_sessions' => '0',
                    'show_public_stats' => '0',
                    'require_approval' => '0',
                    'show_countdown' => '0',
                ],
                'score' => [
                    'publish_scores' => '0',
                    'show_ranking' => '0',
                    'ranking_scope' => 'class',
                    'public_scoreboard' => '0',
                ],
                'mail' => [
                    'host' => '',
                    'port' => 587,
                    'username' => '',
                    'password' => '',
                    'encryption' => 'tls',
                    'from_address' => '',
                    'from_name' => '',
                ],
                'security' => ['auto_logout_minutes' => 60, 'max_login_attempts' => 5],
            ])
            ->assertRedirect();

        $exam->refresh();
        $this->assertFalse($exam->require_session_choice);
        $this->assertFalse($exam->auto_lock_full_sessions);
        $this->assertFalse($exam->show_countdown);
    }

    public function test_student_account_creation_is_blocked_when_account_exists(): void
    {
        $student = Student::factory()->create(['class_name' => '10A1', 'student_code' => 'HS001']);
        User::factory()->create(['role' => 'student', 'student_id' => $student->id, 'username' => 'HS001']);

        $this->post('/register', [
            'class_name' => '10A1',
            'credential' => 'HS001',
            'email' => 'new@example.test',
            'password' => 'abc12345',
            'password_confirmation' => 'abc12345',
        ])->assertSessionHasErrors('credential');
    }

    public function test_import_preview_reports_duplicate_and_missing_identifiers(): void
    {
        $batch = app(StudentImportService::class)->preview($this->studentExcel([
            ['Nguyễn Văn A', '10A1', 10, 'HS001', '012345678901', '2010-01-01', 'Nam'],
            ['Trần Văn B', '10A2', 10, 'HS001', '012345678902', '2010-01-02', 'Nam'],
            ['Lê Văn C', '11A1', 11, null, null, '2009-01-01', 'Nam'],
        ]));

        $this->assertSame(2, $batch->invalid_rows);
        $this->assertStringContainsString('trùng', implode(' ', $batch->errors[0]['messages']));
        $this->assertStringContainsString('mã học sinh hoặc CCCD', implode(' ', $batch->errors[1]['messages']));
    }

    public function test_assignment_rejects_cross_exam_pending_byod_and_over_capacity(): void
    {
        $admin = $this->roleUser('admin');
        $session = ExamSession::factory()->create(['max_candidates' => 1]);
        $room = $session->room;
        RoomComputer::factory()->create(['exam_room_id' => $room->id, 'computer_number' => 1, 'computer_label' => 'Máy 1']);

        $crossExam = ExamRegistration::factory()->create(['status' => 'approved']);

        $this->actingAs($admin)->post(route('admin.assignments.store'), [
            'exam_session_id' => $session->id,
            'exam_room_id' => $room->id,
            'registration_ids' => [$crossExam->id],
            'method' => 'class',
        ])->assertSessionHasErrors('registration_ids');

        $pendingByod = ExamRegistration::factory()->create([
            'exam_id' => $session->exam_id,
            'status' => 'approved',
            'uses_personal_computer' => true,
            'personal_computer_status' => 'pending',
        ]);

        $this->actingAs($admin)->post(route('admin.assignments.store'), [
            'exam_session_id' => $session->id,
            'exam_room_id' => $room->id,
            'registration_ids' => [$pendingByod->id],
            'method' => 'class',
        ])->assertSessionHasErrors('registration_ids');

        $registrations = ExamRegistration::factory()->count(2)->create(['exam_id' => $session->exam_id, 'status' => 'approved']);

        $this->actingAs($admin)->post(route('admin.assignments.store'), [
            'exam_session_id' => $session->id,
            'exam_room_id' => $room->id,
            'registration_ids' => $registrations->pluck('id')->all(),
            'method' => 'class',
        ])->assertSessionHasErrors('max_candidates');
    }

    public function test_backup_computer_cannot_be_reused_in_same_session_room(): void
    {
        $admin = $this->roleUser('admin');
        $assignment = $this->assignmentFixture();
        $other = $this->assignmentFixture($assignment->session, $assignment->room, 2);
        $backup = RoomComputer::factory()->create([
            'exam_room_id' => $assignment->exam_room_id,
            'computer_label' => 'Máy dự phòng 1',
            'computer_number' => 1,
            'type' => 'backup',
            'status' => 'ready',
        ]);
        $other->update(['computer_id' => $backup->id, 'backup_computer_id' => $backup->id, 'seat_type' => 'backup_computer']);

        $this->actingAs($admin)->patch(route('admin.assignments.move', $assignment), [
            'new_computer_id' => $backup->id,
            'reason' => 'Máy chính lỗi.',
        ])->assertSessionHasErrors('new_computer_id');
    }

    public function test_locked_score_requires_unlock_permission_and_reason(): void
    {
        $admin = $this->roleUser('admin');
        $assignment = $this->assignmentFixture();
        $score = ExamScore::create([
            'exam_registration_id' => $assignment->exam_registration_id,
            'seat_assignment_id' => $assignment->id,
            'official_score' => 900,
            'exam_status' => 'completed',
            'score_status' => 'locked',
        ]);

        $this->actingAs($admin)->post(route('admin.scores.store', $assignment->registration), [
            'official_score' => 950,
            'exam_status' => 'completed',
            'locked_change' => 1,
        ])->assertSessionHasErrors('reason');

        $this->assertSame('900.00', $score->refresh()->official_score);
    }

    public function test_student_export_masks_identity_for_teacher_without_sensitive_permission(): void
    {
        $teacher = $this->roleUser('teacher');
        Student::factory()->create(['identity_number' => '123456789012']);

        $response = $this->actingAs($teacher)->get(route('admin.students.export'));

        $response->assertOk();
        $zip = new ZipArchive;
        $zip->open($response->baseResponse->getFile()->getPathname());
        $sharedStrings = $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();

        $this->assertStringNotContainsString('123456789012', $sharedStrings);
        $this->assertStringContainsString('********9012', $sharedStrings);
    }

    private function sameSessionDifferentRooms(): array
    {
        $proctor = $this->roleUser('proctor');
        $exam = Exam::factory()->create();
        $roomA = ExamRoom::factory()->create(['room_code' => 'A']);
        $roomB = ExamRoom::factory()->create(['room_code' => 'B']);
        $session = ExamSession::factory()->create(['exam_id' => $exam->id, 'exam_room_id' => $roomA->id]);
        $assigned = $this->assignmentFixture($session, $roomA, 1, 'Nguyễn Văn A');
        $otherRoom = $this->assignmentFixture($session, $roomB, 2, 'Trần Văn B');

        ProctorAssignment::create([
            'user_id' => $proctor->id,
            'exam_session_id' => $session->id,
            'exam_room_id' => $roomA->id,
        ]);

        return [$proctor, $assigned, $otherRoom];
    }

    private function assignmentFixture(?ExamSession $session = null, ?ExamRoom $room = null, int $candidateNumber = 1, string $name = 'Nguyễn Văn A'): SeatAssignment
    {
        $exam = $session?->exam ?? Exam::factory()->create();
        $room ??= ExamRoom::factory()->create();
        $computer = RoomComputer::factory()->create([
            'exam_room_id' => $room->id,
            'computer_label' => 'Máy '.fake()->unique()->numberBetween(1, 999),
            'computer_number' => fake()->unique()->numberBetween(1, 999),
        ]);
        $session ??= ExamSession::factory()->create(['exam_id' => $exam->id, 'exam_room_id' => $room->id]);
        $student = Student::factory()->create(['full_name' => $name]);
        $registration = ExamRegistration::factory()->create(['exam_id' => $exam->id, 'student_id' => $student->id, 'full_name' => $name, 'identity_number' => $student->identity_number]);

        return SeatAssignment::create([
            'exam_registration_id' => $registration->id,
            'exam_session_id' => $session->id,
            'exam_room_id' => $room->id,
            'seat_type' => 'school_computer',
            'computer_id' => $computer->id,
            'computer_number' => $computer->computer_number,
            'candidate_number' => $candidateNumber,
            'assignment_method' => 'manual',
            'status' => 'assigned',
        ]);
    }

    private function roleUser(string $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        $user->assignRole($role);

        return $user;
    }

    private function registrationPayload(Student $student, array $overrides = []): array
    {
        return array_merge([
            'full_name' => $student->full_name,
            'ioe_id' => 'ioe-unique',
            'date_of_birth' => $student->date_of_birth->format('Y-m-d'),
            'gender' => 'Nam',
            'identity_number' => $student->identity_number,
            'class_name' => $student->class_name,
            'address' => $student->address,
            'phone' => '0912345678',
            'email' => 'student@example.test',
            'uses_personal_computer' => 0,
            'confirm_information' => 1,
        ], $overrides);
    }

    private function studentExcel(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['Họ và tên', 'Lớp', 'Khối', 'Mã học sinh', 'CCCD', 'Ngày sinh', 'Giới tính'],
            ...$rows,
        ]);

        $path = tempnam(sys_get_temp_dir(), 'students').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'students.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function permissions(): array
    {
        return [
            'dashboard.view',
            'students.view',
            'students.view_sensitive',
            'students.import',
            'students.export',
            'exams.manage',
            'form.manage',
            'registrations.view',
            'registrations.approve',
            'registrations.update',
            'registrations.delete',
            'devices.approve',
            'rooms.manage',
            'sessions.manage',
            'assignments.manage',
            'assignments.lock',
            'checkins.manage',
            'incidents.manage',
            'scores.enter',
            'scores.verify',
            'scores.lock',
            'scores.unlock',
            'exports.manage',
            'research.manage',
            'users.manage',
            'activity.view',
            'settings.manage',
        ];
    }
}
