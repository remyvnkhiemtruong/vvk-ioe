<?php

namespace App\Console\Commands;

use App\Services\AcademicYearDataResetService;
use Illuminate\Console\Command;
use Throwable;

class ResetAcademicYearCommand extends Command
{
    protected $signature = 'ioe:reset-year
        {year : Năm học cần xóa dữ liệu nghiệp vụ, ví dụ 2025-2026}
        {--dry-run : Chỉ xem report, không ghi dữ liệu}
        {--confirm : Thực sự xóa trong transaction}
        {--delete-student-users : Xóa user học sinh cũ thay vì detach student_id}';

    protected $description = 'Xóa an toàn dữ liệu nghiệp vụ theo năm học, giữ admin/roles/permissions/settings.';

    public function handle(AcademicYearDataResetService $reset): int
    {
        $year = (string) $this->argument('year');
        $confirmed = (bool) $this->option('confirm') && ! (bool) $this->option('dry-run');

        try {
            $report = $confirmed
                ? $reset->execute($year, (bool) $this->option('delete-student-users'))
                : $reset->dryRun($year);
        } catch (Throwable $throwable) {
            $this->error('Reset thất bại, transaction đã rollback: '.$throwable->getMessage());

            return self::FAILURE;
        }

        $this->info($confirmed ? 'Đã reset dữ liệu nghiệp vụ năm '.$year.'.' : 'DRY-RUN: chưa xóa dữ liệu.');
        $this->line('Năm học: '.$year);
        $this->line('Academic year ID: '.($report['academic_year_id'] ?: 'không có'));
        $this->line('Exams scoped: '.count($report['exam_ids']));
        $this->line('Students scoped: '.count($report['student_ids']));
        $this->newLine();

        $counts = $confirmed ? ($report['deleted'] ?? []) : ($report['counts'] ?? []);
        $this->table(
            ['Bảng', $confirmed ? 'Đã xóa/detach' : 'Sẽ xóa/detach'],
            collect($counts)->map(fn ($count, $table) => [$table, $count])->values()->all()
        );

        if (! $confirmed) {
            $this->warn('Thêm --confirm để thực hiện. Thiếu --confirm luôn là dry-run.');
        }

        return self::SUCCESS;
    }
}
