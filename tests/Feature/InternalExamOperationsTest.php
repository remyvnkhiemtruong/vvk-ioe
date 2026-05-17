<?php

namespace Tests\Feature;

use App\Models\AcademicResult;
use App\Models\Exam;
use App\Models\ExamChecklist;
use App\Models\ExamMinute;
use App\Models\ExamRoom;
use App\Models\ExamSession;
use App\Models\Student;
use App\Models\User;
use App\Models\VideoEvidence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class InternalExamOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([
            'dashboard.view',
            'attendance.manage',
            'minutes.upload',
            'registrations.view',
            'registrations.approve',
        ] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'])->syncPermissions(Permission::all());
    }

    public function test_internal_registration_mode_does_not_require_session_choice(): void
    {
        [$student, $user] = $this->studentUser();
        $exam = Exam::factory()->create([
            'registration_mode' => 'admin_assign_session',
            'require_session_choice' => false,
        ]);

        $this->actingAs($user)
            ->post(route('student.registrations.store', $exam), $this->registrationPayload($student))
            ->assertRedirect();

        $this->assertDatabaseHas('exam_registrations', [
            'student_id' => $student->id,
            'exam_id' => $exam->id,
            'exam_session_id' => null,
            'status' => 'submitted',
        ]);
    }

    public function test_student_select_session_mode_still_requires_a_valid_session(): void
    {
        [$student, $user] = $this->studentUser();
        $exam = Exam::factory()->create([
            'registration_mode' => 'student_select_session',
            'require_session_choice' => true,
        ]);

        $this->actingAs($user)
            ->post(route('student.registrations.store', $exam), $this->registrationPayload($student))
            ->assertSessionHasErrors('exam_session_id');
    }

    public function test_backup_external_account_is_controlled_by_exam_setting(): void
    {
        [$student, $user] = $this->studentUser();
        $exam = Exam::factory()->create([
            'registration_mode' => 'admin_assign_session',
            'settings' => ['allow_backup_account' => false],
        ]);

        $this->actingAs($user)
            ->post(route('student.registrations.store', $exam), $this->registrationPayload($student, [
                'backup_external_account_id' => 'backup-001',
            ]))
            ->assertSessionHasErrors('backup_external_account_id');

        $exam->update(['settings' => ['allow_backup_account' => true]]);

        $this->actingAs($user)
            ->post(route('student.registrations.store', $exam), $this->registrationPayload($student, [
                'ioe_id' => 'primary-002',
                'backup_external_account_id' => 'backup-002',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('exam_registrations', [
            'student_id' => $student->id,
            'backup_external_account_id' => 'backup-002',
        ]);
    }

    public function test_academic_result_import_command_dry_run_and_commit(): void
    {
        Student::factory()->create(['student_code' => 'HS001', 'full_name' => 'Nguyễn Văn A']);
        $path = $this->xlsx([
            ['SỞ GIÁO DỤC VÀ ĐÀO TẠO'],
            ['Trường THPT Võ Văn Kiệt'],
            [],
            ['KẾT QUẢ HỌC TẬP'],
            [],
            ['STT', 'Khối học/Nhóm tuổi', 'Tên lớp học', 'Họ và tên', 'Mã học sinh', 'Mã định danh Bộ GD&ĐT', 'Trạng thái', 'Điểm tổng kết', 'Năm học', 'Học kỳ', 'Giai đoạn', 'Học lực', 'Hạnh kiểm', 'Danh hiệu', 'Kết quả học tập', 'Kết quả rèn luyện', 'Id Tổng kết hs'],
            [1, 'Khối 10', '10A1', 'Nguyễn Văn A', 'HS001', '950000001', 'Đang học', 8.5, '2025', '1', 'Giữa kỳ', 'Tốt', 'Tốt', 'Học sinh giỏi', 'Tốt', 'Tốt', 'TK001'],
        ]);

        $this->artisan('school:import-academic-results', [
            'path' => $path,
            '--dry-run' => true,
        ])->assertExitCode(0);
        $this->assertSame(0, AcademicResult::count());

        $this->artisan('school:import-academic-results', [
            'path' => $path,
            '--commit' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('academic_results', [
            'student_code' => 'HS001',
            'school_year' => '2025-2026',
            'semester' => '1',
            'stage' => 'Giữa kỳ',
        ]);
    }

    public function test_monitoring_routes_store_checklist_minutes_and_video(): void
    {
        $admin = $this->adminUser();
        $exam = Exam::factory()->create();
        $room = ExamRoom::factory()->create();
        $session = ExamSession::factory()->create(['exam_id' => $exam->id, 'exam_room_id' => $room->id]);

        $this->actingAs($admin)->post(route('admin.monitoring.checklist'), [
            'exam_id' => $exam->id,
            'exam_session_id' => $session->id,
            'exam_room_id' => $room->id,
            'internet_ok' => 1,
            'computers_ok' => 1,
            'headsets_ok' => 1,
            'camera_ok' => 1,
            'time_zone_ok' => 1,
            'backup_power_network_ready' => 1,
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.monitoring.minute'), [
            'exam_id' => $exam->id,
            'exam_session_id' => $session->id,
            'exam_room_id' => $room->id,
            'status' => 'uploaded',
            'notes' => 'Đã nộp bản scan.',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.monitoring.video'), [
            'exam_id' => $exam->id,
            'exam_session_id' => $session->id,
            'exam_room_id' => $room->id,
            'video_url' => 'https://drive.google.com/file/d/example',
            'storage_provider' => 'google_drive',
            'quality_status' => 'ok',
            'visibility_checked' => 1,
        ])->assertRedirect();

        $this->assertSame(1, ExamChecklist::count());
        $this->assertSame(1, ExamMinute::count());
        $this->assertSame(1, VideoEvidence::count());
    }

    private function studentUser(): array
    {
        $student = Student::factory()->create(['class_name' => '10A1', 'grade' => 10]);
        $user = User::factory()->create(['role' => 'student', 'student_id' => $student->id, 'username' => $student->student_code]);
        $user->assignRole('student');

        return [$student, $user];
    }

    private function adminUser(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        $user->assignRole('admin');

        return $user;
    }

    private function registrationPayload(Student $student, array $overrides = []): array
    {
        return array_merge([
            'full_name' => $student->full_name,
            'ioe_id' => 'primary-001',
            'date_of_birth' => $student->date_of_birth->format('Y-m-d'),
            'gender' => $student->gender,
            'identity_number' => $student->identity_number,
            'class_name' => $student->class_name,
            'address' => $student->address,
            'phone' => '0912345678',
            'email' => 'student@example.test',
            'uses_personal_computer' => 0,
            'confirm_information' => 1,
        ], $overrides);
    }

    private function xlsx(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($rows);
        $path = tempnam(sys_get_temp_dir(), 'academic-results').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }
}
