<?php

namespace App\Console\Commands;

use App\Models\AcademicYear;
use App\Models\AcademicYearStudent;
use App\Models\Exam;
use App\Models\SystemSetting;
use App\Services\AcademicYearRolloverService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PrepareIoeAcademicYearCommand extends Command
{
    protected $signature = 'ioe:prepare-year
        {year=2026-2027 : Năm học cần đưa lên workflow chính}
        {--from=2025-2026 : Năm học lịch sử dùng để rollover}
        {--dry-run : Chạy thử trong transaction rồi rollback}';

    protected $description = 'Chuẩn bị workflow IOE năm học mới, archive kỳ cũ và giữ dữ liệu lịch sử.';

    public function handle(AcademicYearRolloverService $rollover): int
    {
        $yearCode = (string) $this->argument('year');
        $fromCode = (string) $this->option('from');
        $dryRun = (bool) $this->option('dry-run');

        DB::beginTransaction();

        try {
            $year = AcademicYear::updateOrCreate(
                ['code' => $yearCode],
                [
                    'name' => 'Năm học '.str_replace('-', ' - ', $yearCode),
                    'start_date' => $this->startDate($yearCode),
                    'end_date' => $this->endDate($yearCode),
                    'starts_at' => $this->startDate($yearCode),
                    'ends_at' => $this->endDate($yearCode),
                    'status' => 'current',
                    'is_current' => true,
                    'is_active' => true,
                ]
            );

            AcademicYear::whereKeyNot($year->id)->update([
                'is_current' => false,
                'is_active' => false,
            ]);

            AcademicYear::where('code', $fromCode)->update([
                'status' => 'archived',
                'is_current' => false,
                'is_active' => false,
            ]);

            $archivedExams = Exam::where('school_year', $fromCode)
                ->where('status', '!=', 'archived')
                ->update(['status' => 'archived']);

            $rolloverSummary = $rollover->rolloverAllStudents($fromCode, $yearCode, false, false);

            $this->updateSettings($yearCode);

            $summary = [
                'dry_run' => $dryRun,
                'active_year' => $yearCode,
                'archived_exams' => $archivedExams,
                'rollover_total' => $rolloverSummary['total'] ?? 0,
                'rollover_created' => $rolloverSummary['created'] ?? 0,
                'rollover_updated' => $rolloverSummary['updated'] ?? 0,
                'rollover_skipped' => $rolloverSummary['skipped'] ?? 0,
                'active_year_students' => AcademicYearStudent::where('academic_year_id', $year->id)->count(),
                'new_year_exams' => Exam::where('school_year', $yearCode)->count(),
            ];

            $dryRun ? DB::rollBack() : DB::commit();
        } catch (\Throwable $throwable) {
            DB::rollBack();
            throw $throwable;
        }

        $this->info($dryRun ? 'Dry-run prepare-year hoàn tất, chưa ghi dữ liệu.' : 'Đã chuẩn bị workflow năm học mới.');
        foreach ($summary as $key => $value) {
            $this->line($key.': '.$value);
        }

        return self::SUCCESS;
    }

    private function updateSettings(string $yearCode): void
    {
        $site = SystemSetting::where('key', 'site.info')->first()?->value ?? [];
        SystemSetting::updateOrCreate(['key' => 'site.info'], [
            'value' => array_replace([
                'site_name' => 'IOE nội bộ',
                'contest_name' => 'IOE nội bộ Trường THPT Võ Văn Kiệt',
                'home_description' => 'Hệ thống quản lý IOE nội bộ của Trường THPT Võ Văn Kiệt: theo dõi kỳ thi, danh sách học sinh, ca thi, live mã ca và nhập điểm sau khi thi trên ioe.vn.',
            ], $site, ['school_year' => $yearCode]),
        ]);

        $contact = SystemSetting::where('key', 'site.contact')->first()?->value ?? [];
        SystemSetting::updateOrCreate(['key' => 'site.contact'], [
            'value' => array_replace([
                'teacher_name' => 'Thầy Huỳnh Thanh Hào',
                'teacher_title' => 'Giáo viên tiếng Anh, phụ trách tổ chức thi IOE',
                'teacher_email' => 'huynhthanhhaota@gmail.com',
                'support_name' => 'Trương Minh Khiêm',
                'support_title' => 'Cựu học sinh, học viên Trường Sĩ quan Thông tin',
                'support_phone' => '0385844458',
                'support_email' => 'truongminhkhiemvta@gmail.com',
                'developer_name' => 'Trương Minh Khiêm',
                'note' => 'Học sinh liên hệ giáo viên phụ trách hoặc bộ phận hỗ trợ khi cần mã học sinh, tài khoản hoặc thông tin ca thi.',
            ], $contact),
        ]);

        $account = SystemSetting::where('key', 'account.options')->first()?->value ?? [];
        SystemSetting::updateOrCreate(['key' => 'account.options'], [
            'value' => array_replace([
                'student_registration_enabled' => true,
                'student_code_lookup_url' => '',
                'student_code_help' => 'Nếu chưa biết mã học sinh, học sinh có thể liên hệ Trương Minh Khiêm để được hỗ trợ hoặc dùng link tra cứu khi nhà trường công bố.',
            ], $account),
        ]);
    }

    private function startDate(string $yearCode): string
    {
        return substr($yearCode, 0, 4).'-09-01';
    }

    private function endDate(string $yearCode): string
    {
        return substr($yearCode, -4).'-05-31';
    }
}
