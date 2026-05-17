<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamChecklist;
use App\Models\ExamMinute;
use App\Models\ExamRoom;
use App\Models\ExamSession;
use App\Models\Student;
use App\Models\User;
use App\Models\VideoEvidence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Feature tests cho Dashboard và Registration flows.
 * Đảm bảo:
 * - Home page render được với fresh SQLite database.
 * - Admin dashboard render được sau migrate:fresh --seed.
 * - Admin dashboard không crash khi bảng exam_checklists rỗng.
 * - Student registration hoạt động khi require_session_choice = false.
 * - Student registration hoạt động khi require_session_choice = true.
 * - has_charger = 1 pass, has_charger = 0 (absent) pass.
 * - Dashboard redirect đúng theo role.
 * - Bảng exam_checklists tồn tại sau migrate.
 */
class DashboardAndRegistrationTest extends TestCase
{
    use RefreshDatabase;

    /** Permissions cần thiết cho dashboard */
    private array $adminPermissions = [
        'dashboard.view',
        'students.view',
        'exams.manage',
        'registrations.view',
        'registrations.approve',
        'assignments.manage',
        'checkins.manage',
        'incidents.manage',
        'scores.enter',
        'scores.verify',
        'scores.lock',
        'results.enter',
        'results.review',
        'minutes.upload',
        'minutes.generate',
        'minutes.review',
        'exports.manage',
        'reports.export',
        'attendance.manage',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->adminPermissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'])
            ->syncPermissions(Permission::all());
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web'])
            ->syncPermissions(Permission::all());
        Role::firstOrCreate(['name' => 'exam_admin', 'guard_name' => 'web'])
            ->syncPermissions(Permission::all());
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web'])
            ->syncPermissions(['dashboard.view', 'registrations.view', 'scores.enter', 'attendance.manage', 'minutes.upload', 'minutes.generate', 'minutes.review', 'exports.manage', 'reports.export']);
        Role::firstOrCreate(['name' => 'proctor', 'guard_name' => 'web'])
            ->syncPermissions(['checkins.manage', 'incidents.manage', 'scores.enter', 'attendance.manage']);
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Cơ sở hạ tầng DB
    // ─────────────────────────────────────────────────────────────────────────

    public function test_exam_checklists_table_exists_after_migration(): void
    {
        $this->assertTrue(
            Schema::hasTable('exam_checklists'),
            'Bảng exam_checklists phải tồn tại sau khi migrate.'
        );
    }

    public function test_exam_minutes_table_exists_after_migration(): void
    {
        $this->assertTrue(Schema::hasTable('exam_minutes'));
    }

    public function test_video_evidence_table_exists_after_migration(): void
    {
        $this->assertTrue(Schema::hasTable('video_evidence'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Home page
    // ─────────────────────────────────────────────────────────────────────────

    public function test_home_page_renders_with_fresh_database(): void
    {
        $response = $this->get('/');

        $response->assertOk();
    }

    public function test_home_page_renders_without_any_exam(): void
    {
        // Không có exam nào trong DB → phải render được
        $this->assertSame(0, Exam::count());
        $this->get('/')->assertOk();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Admin Dashboard
    // ─────────────────────────────────────────────────────────────────────────

    public function test_admin_dashboard_renders_with_empty_database(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_admin_dashboard_does_not_crash_when_exam_checklists_empty(): void
    {
        $admin = $this->adminUser();
        // Tạo exam và session nhưng không có checklist
        $exam = Exam::factory()->create(['level' => 'school']);
        ExamRoom::factory()->create();
        ExamSession::factory()->create(['exam_id' => $exam->id]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk();

        // Bảng exam_checklists phải rỗng (không có dữ liệu seed tự động)
        $this->assertSame(0, ExamChecklist::count());
    }

    public function test_admin_dashboard_shows_zeros_when_no_registrations(): void
    {
        $admin = $this->adminUser();
        Exam::factory()->create(['level' => 'school']);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        // Có thể assert view data nếu cần
        $response->assertViewHas('stats');
        $stats = $response->viewData('stats');
        $this->assertSame(0, $stats['registrations']);
        $this->assertSame(0, $stats['approved']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Dashboard redirect theo role
    // ─────────────────────────────────────────────────────────────────────────

    public function test_dashboard_redirects_admin_to_admin_dashboard(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_dashboard_redirects_teacher_to_admin_dashboard(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $teacher->assignRole('teacher');

        $this->actingAs($teacher)
            ->get('/dashboard')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_dashboard_redirects_proctor_to_proctor_dashboard(): void
    {
        $proctor = User::factory()->create(['role' => 'proctor']);
        $proctor->assignRole('proctor');

        $this->actingAs($proctor)
            ->get('/dashboard')
            ->assertRedirect(route('proctor.dashboard'));
    }

    public function test_dashboard_redirects_student_to_student_dashboard(): void
    {
        $student = Student::factory()->create(['class_name' => '10A1']);
        $user = User::factory()->create(['role' => 'student', 'student_id' => $student->id, 'username' => $student->student_code]);
        $user->assignRole('student');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('student.dashboard'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Student Registration – require_session_choice = false
    // ─────────────────────────────────────────────────────────────────────────

    public function test_registration_succeeds_when_session_choice_not_required(): void
    {
        [$student, $user] = $this->studentUser('10A1', 10);
        $exam = Exam::factory()->create([
            'registration_mode' => 'admin_assign_session',
            'require_session_choice' => false,
            'status' => 'open',
            'target_grades' => [10, 11, 12],
        ]);

        $this->actingAs($user)
            ->post(route('student.registrations.store', $exam), $this->basePayload($student))
            ->assertRedirect();

        $this->assertDatabaseHas('exam_registrations', [
            'student_id' => $student->id,
            'exam_id' => $exam->id,
            'exam_session_id' => null,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Student Registration – require_session_choice = true
    // ─────────────────────────────────────────────────────────────────────────

    public function test_registration_succeeds_when_session_choice_required_and_valid_session_provided(): void
    {
        [$student, $user] = $this->studentUser('10A1', 10);
        $exam = Exam::factory()->create([
            'registration_mode' => 'student_select_session',
            'require_session_choice' => true,
            'status' => 'open',
            'target_grades' => [10, 11, 12],
        ]);
        $session = ExamSession::factory()->create([
            'exam_id' => $exam->id,
            'target_grade' => 10,
            'target_classes' => null,
            'max_candidates' => 25,
            'status' => 'open',
        ]);

        $payload = array_merge($this->basePayload($student), ['exam_session_id' => $session->id]);

        $this->actingAs($user)
            ->post(route('student.registrations.store', $exam), $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('exam_registrations', [
            'student_id' => $student->id,
            'exam_id' => $exam->id,
            'exam_session_id' => $session->id,
        ]);
    }

    public function test_registration_fails_when_session_choice_required_but_no_session_given(): void
    {
        [$student, $user] = $this->studentUser('10A1', 10);
        $exam = Exam::factory()->create([
            'registration_mode' => 'student_select_session',
            'require_session_choice' => true,
            'status' => 'open',
        ]);

        $this->actingAs($user)
            ->post(route('student.registrations.store', $exam), $this->basePayload($student))
            ->assertSessionHasErrors('exam_session_id');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // has_charger logic
    // ─────────────────────────────────────────────────────────────────────────

    public function test_registration_with_personal_computer_and_charger_passes(): void
    {
        [$student, $user] = $this->studentUser('10A1', 10);
        $exam = Exam::factory()->create([
            'require_session_choice' => false,
            'allow_personal_computer' => true,
            'status' => 'open',
            'target_grades' => [10, 11, 12],
        ]);

        $payload = array_merge($this->basePayload($student), [
            'ioe_id' => 'ioe-byod-1',
            'uses_personal_computer' => 1,
            'device_type' => 'Laptop',
            'device_os' => 'Windows',
            'has_charger' => 1,
            'device_commitment' => 1,
        ]);

        $this->actingAs($user)
            ->post(route('student.registrations.store', $exam), $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('exam_registrations', [
            'student_id' => $student->id,
            'uses_personal_computer' => 1,
            'has_charger' => 1,
        ]);
    }

    public function test_registration_with_personal_computer_and_no_charger_passes(): void
    {
        // has_charger = 0 là hợp lệ về nghiệp vụ (không mang sạc nhưng vẫn dùng máy cá nhân)
        [$student, $user] = $this->studentUser('10A2', 10);
        $exam = Exam::factory()->create([
            'require_session_choice' => false,
            'allow_personal_computer' => true,
            'status' => 'open',
            'target_grades' => [10, 11, 12],
        ]);

        $payload = array_merge($this->basePayload($student), [
            'ioe_id' => 'ioe-byod-2',
            'uses_personal_computer' => 1,
            'device_type' => 'Laptop',
            'device_os' => 'Windows',
            'has_charger' => 0,
            'device_commitment' => 1,
        ]);

        $this->actingAs($user)
            ->post(route('student.registrations.store', $exam), $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('exam_registrations', [
            'student_id' => $student->id,
            'uses_personal_computer' => 1,
            'has_charger' => 0,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Duplicate checks
    // ─────────────────────────────────────────────────────────────────────────

    public function test_duplicate_ioe_id_in_same_exam_is_rejected(): void
    {
        [$student, $user] = $this->studentUser('10A1', 10);
        $exam = Exam::factory()->create([
            'require_session_choice' => false,
            'status' => 'open',
            'target_grades' => [10, 11, 12],
        ]);

        // Đăng ký lần 1 thành công
        $this->actingAs($user)
            ->post(route('student.registrations.store', $exam), $this->basePayload($student))
            ->assertRedirect();

        // Tạo student khác với ioe_id giống
        $student2 = Student::factory()->create(['class_name' => '10A1', 'grade' => 10]);
        $user2 = User::factory()->create(['role' => 'student', 'student_id' => $student2->id, 'username' => $student2->student_code]);
        $user2->assignRole('student');

        $payload = $this->basePayload($student2);
        // ioe_id giống
        $this->actingAs($user2)
            ->post(route('student.registrations.store', $exam), $payload)
            ->assertSessionHasErrors('ioe_id');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Monitoring – checklist, minutes, video không crash với bảng rỗng
    // ─────────────────────────────────────────────────────────────────────────

    public function test_monitoring_checklist_store_works(): void
    {
        $admin = $this->adminUser();
        $exam = Exam::factory()->create();
        $room = ExamRoom::factory()->create();
        $session = ExamSession::factory()->create(['exam_id' => $exam->id, 'exam_room_id' => $room->id]);

        $this->actingAs($admin)
            ->post(route('admin.monitoring.checklist'), [
                'exam_id' => $exam->id,
                'exam_session_id' => $session->id,
                'exam_room_id' => $room->id,
                'internet_ok' => 1,
                'computers_ok' => 1,
                'headsets_ok' => 1,
                'camera_ok' => 1,
                'time_zone_ok' => 1,
                'backup_power_network_ready' => 1,
            ])
            ->assertRedirect();

        $this->assertSame(1, ExamChecklist::count());
    }

    public function test_monitoring_minute_store_works(): void
    {
        $admin = $this->adminUser();
        $exam = Exam::factory()->create();
        $room = ExamRoom::factory()->create();
        $session = ExamSession::factory()->create(['exam_id' => $exam->id, 'exam_room_id' => $room->id]);

        $this->actingAs($admin)
            ->post(route('admin.monitoring.minute'), [
                'exam_id' => $exam->id,
                'exam_session_id' => $session->id,
                'exam_room_id' => $room->id,
                'status' => 'uploaded',
                'notes' => 'Đã nộp bản scan.',
            ])
            ->assertRedirect();

        $this->assertSame(1, ExamMinute::count());
    }

    public function test_monitoring_video_store_works(): void
    {
        $admin = $this->adminUser();
        $exam = Exam::factory()->create();
        $room = ExamRoom::factory()->create();
        $session = ExamSession::factory()->create(['exam_id' => $exam->id, 'exam_room_id' => $room->id]);

        $this->actingAs($admin)
            ->post(route('admin.monitoring.video'), [
                'exam_id' => $exam->id,
                'exam_session_id' => $session->id,
                'exam_room_id' => $room->id,
                'video_url' => 'https://drive.google.com/file/d/test',
                'storage_provider' => 'google_drive',
                'quality_status' => 'ok',
                'visibility_checked' => 1,
            ])
            ->assertRedirect();

        $this->assertSame(1, VideoEvidence::count());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function adminUser(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        $user->assignRole('admin');

        return $user;
    }

    private function studentUser(string $class = '10A1', int $grade = 10): array
    {
        $student = Student::factory()->create([
            'class_name' => $class,
            'grade' => $grade,
        ]);
        $user = User::factory()->create([
            'role' => 'student',
            'student_id' => $student->id,
            'username' => $student->student_code,
        ]);
        $user->assignRole('student');

        return [$student, $user];
    }

    private function basePayload(Student $student, array $overrides = []): array
    {
        return array_merge([
            'full_name' => $student->full_name,
            'ioe_id' => 'ioe-test-001',
            'date_of_birth' => $student->date_of_birth instanceof \Carbon\Carbon
                ? $student->date_of_birth->format('Y-m-d')
                : $student->date_of_birth,
            'gender' => $student->gender,
            'identity_number' => $student->identity_number,
            'class_name' => $student->class_name,
            'address' => $student->address ?? 'Địa chỉ test',
            'phone' => '0912345678',
            'email' => 'student@example.test',
            'uses_personal_computer' => 0,
            'confirm_information' => 1,
        ], $overrides);
    }
}
