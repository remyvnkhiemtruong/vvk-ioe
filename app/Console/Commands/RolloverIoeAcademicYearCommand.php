<?php

namespace App\Console\Commands;

use App\Services\AcademicYearRolloverService;
use Illuminate\Console\Command;

class RolloverIoeAcademicYearCommand extends Command
{
    protected $signature = 'ioe:rollover-year {from : Năm học nguồn, ví dụ 2025-2026} {to : Năm học đích, ví dụ 2026-2027} {--dry-run : Chạy thử trong transaction rồi rollback} {--all-students : Rollover tất cả học sinh của năm học nguồn, không chỉ tài khoản IOE}';

    protected $description = 'Rollover học sinh IOE sang năm học mới ở trạng thái chờ thông tin chính thức.';

    public function handle(AcademicYearRolloverService $service): int
    {
        $summary = $this->option('all-students')
            ? $service->rolloverAllStudents(
                (string) $this->argument('from'),
                (string) $this->argument('to'),
                (bool) $this->option('dry-run')
            )
            : $service->rollover(
                (string) $this->argument('from'),
                (string) $this->argument('to'),
                (bool) $this->option('dry-run')
            );

        $this->info($summary['dry_run'] ? 'Dry-run rollover hoàn tất, không ghi dữ liệu.' : 'Rollover hoàn tất.');

        foreach ($summary as $key => $value) {
            $this->line($key.': '.$value);
        }

        return self::SUCCESS;
    }
}
