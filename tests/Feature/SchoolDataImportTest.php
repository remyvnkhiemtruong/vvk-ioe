<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\StudentImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SchoolDataImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_import_reads_header_on_row_eight_and_vietnamese_dates(): void
    {
        $path = $this->xlsx([
            ['SỞ GIÁO DỤC VÀ ĐÀO TẠO TỈNH CÀ MAU'],
            ['Trường THPT Võ Văn Kiệt'],
            [],
            [],
            ['DANH SÁCH HỌC SINH'],
            ['Năm học 2025-2026'],
            [],
            ['STT', 'Họ và tên', 'Khối học/Nhóm lớp', 'Lớp học', 'Mã học sinh', 'Mã định danh bộ GD&ĐT', 'Ngày sinh', 'Giới tính', 'Trạng thái'],
            [1, 'Nguyễn Văn A', '10', '10A1', 'HS001', '123456789012', '30/09/2010', 'Nam', 'Đang học'],
        ]);

        $analysis = app(StudentImportService::class)->analyzePath($path, 'students.xlsx');

        $this->assertSame(1, $analysis['valid_rows']);
        $this->assertSame('2010-09-30', $analysis['preview_rows'][0]['data']['date_of_birth']);
        $this->assertSame('active', $analysis['preview_rows'][0]['data']['status']);
    }

    public function test_import_command_commits_classes_staff_students_and_logo_without_creating_staff_users(): void
    {
        Storage::fake('public');

        $classes = $this->xlsx([
            ['Sở Giáo dục và Đào tạo Tỉnh Cà Mau'],
            ['Trường THPT Võ Văn Kiệt'],
            [],
            [],
            ['DANH SÁCH LỚP HỌC'],
            ['Năm học 2025-2026'],
            [],
            ['STT', 'Mã lớp', 'Tên lớp học', 'Khối học/Nhóm lớp', 'Giáo viên chủ nhiệm', 'Buổi học', 'Học ngoại ngữ 1', 'Lớp chuyên', 'Số buổi học trên tuần'],
            [1, 'LH001', '10A1', 'Khối 10', 'Nguyễn Văn GV', 'Sáng, chiều', 'Tiếng Anh', 'Không', 9],
        ]);
        $staff = $this->xlsx([
            ['SỞ GIÁO DỤC VÀ ĐÀO TẠO TỈNH CÀ MAU'],
            ['Trường THPT Võ Văn Kiệt'],
            [],
            [],
            ['DANH SÁCH GIÁO VIÊN'],
            ['Năm học 2025-2026'],
            [],
            ['STT', 'Mã cán bộ', 'Mã định danh bộ GD&ĐT', 'Họ và tên', 'Ngày sinh', 'Giới tính', 'Trạng thái', 'Loại cán bộ', 'Nhóm chức vụ', 'Hình thức hợp đồng', 'T.Độ chuyên môn nghiệp vụ', 'Môn dạy'],
            [1, 'GV001', null, 'Trần Văn GV', '01/09/1980', 'Nam', 'Đang làm việc', 'Giáo viên', 'Giáo viên', 'Viên chức', 'Đại học sư phạm', 'Toán'],
        ]);
        $students = $this->xlsx([
            ['SỞ GIÁO DỤC VÀ ĐÀO TẠO TỈNH CÀ MAU'],
            ['Trường THPT Võ Văn Kiệt'],
            [],
            [],
            ['DANH SÁCH HỌC SINH'],
            ['Năm học 2025-2026'],
            [],
            ['STT', 'Họ và tên', 'Khối học/Nhóm lớp', 'Lớp học', 'Mã học sinh', 'Mã định danh bộ GD&ĐT', 'Ngày sinh', 'Giới tính', 'Trạng thái'],
            [1, 'Nguyễn Văn A', '10', '10A1', 'HS001', '123456789012', '30/09/2010', 'Nam', 'Đang học'],
        ]);
        $logo = $this->png();

        $this->artisan('ioe:import-school-data', [
            '--classes' => $classes,
            '--staff' => $staff,
            '--students' => $students,
            '--logo' => $logo,
            '--commit' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('school_classes', ['class_name' => '10A1', 'grade' => 10]);
        $this->assertDatabaseHas('staff_profiles', ['staff_code' => 'GV001', 'full_name' => 'Trần Văn GV']);
        $this->assertDatabaseHas('students', ['student_code' => 'HS001', 'class_name' => '10A1']);
        $this->assertSame(0, User::count());
        $this->assertSame(3, ImportBatch::where('status', 'committed')->count());
        Storage::disk('public')->assertExists('school/logo-vvk.png');
        $this->assertSame('school/logo-vvk.png', SystemSetting::where('key', 'school.logo_path')->first()?->value['path'] ?? null);
    }

    public function test_import_command_dry_run_does_not_write_database_or_logo(): void
    {
        Storage::fake('public');

        $classes = $this->xlsx([
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            ['STT', 'Mã lớp', 'Tên lớp học', 'Khối học/Nhóm lớp'],
            [1, 'LH001', '10A1', 'Khối 10'],
        ]);

        $this->artisan('ioe:import-school-data', [
            '--classes' => $classes,
            '--logo' => $this->png(),
            '--dry-run' => true,
        ])->assertExitCode(0);

        $this->assertSame(0, SchoolClass::count());
        $this->assertSame(0, ImportBatch::count());
        Storage::disk('public')->assertMissing('school/logo-vvk.png');
    }

    public function test_class_dropdown_uses_imported_school_classes_before_students(): void
    {
        SchoolClass::create([
            'class_code' => 'LH009',
            'class_name' => '10A9',
            'grade' => 10,
            'school_year' => '2025-2026',
            'status' => 'active',
        ]);
        Student::factory()->create(['class_name' => '10A1', 'status' => 'active']);

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('10A9')
            ->assertDontSee('10A1');
    }

    private function xlsx(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($rows);
        $path = tempnam(sys_get_temp_dir(), 'ioe-import').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function png(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'logo').'.png';
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAFgwJ/lcQZJwAAAABJRU5ErkJggg=='));

        return $path;
    }
}
