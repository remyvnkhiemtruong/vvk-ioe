<?php

namespace App\Console\Commands;

use App\Services\AcademicYearDataResetService;
use App\Services\AcademicYearRosterImportService;
use App\Services\SystemSettingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class PrepareNewAcademicYearCommand extends Command
{
    protected $signature = 'ioe:prepare-new-year
        {year=2026-2027 : Năm học mới}
        {--delete-year=2025-2026 : Năm học cũ cần reset dữ liệu nghiệp vụ}
        {--path= : File hoặc thư mục roster}
        {--logo= : Đường dẫn logo trường để copy vào storage public}
        {--dry-run : Chỉ xem report}
        {--confirm : Thực sự reset và import}';

    protected $description = 'Chuẩn bị năm học mới: reset năm cũ an toàn, tạo năm mới, import roster, không seed kỳ thi.';

    public function handle(
        AcademicYearDataResetService $reset,
        AcademicYearRosterImportService $roster,
        SystemSettingService $settings
    ): int {
        $year = (string) $this->argument('year');
        $deleteYear = (string) $this->option('delete-year');
        $path = (string) ($this->option('path') ?: storage_path('app/imports/'.$year));
        $confirmed = (bool) $this->option('confirm') && ! (bool) $this->option('dry-run');

        try {
            if (! $confirmed) {
                $resetReport = $reset->dryRun($deleteYear);
                $rosterReport = is_file($path) || is_dir($path)
                    ? $roster->importPath($path, $year, true, auth()->id())
                    : ['total_files' => 0, 'total_sheets' => 0, 'total_rows' => 0, 'valid_rows' => 0, 'invalid_rows' => 0, 'errors' => []];
            } else {
                [$resetReport, $rosterReport] = DB::transaction(function () use ($reset, $roster, $settings, $deleteYear, $path, $year): array {
                    $resetReport = $reset->execute($deleteYear);
                    $rosterReport = $roster->importPath($path, $year, false, auth()->id());

                    if ($logo = $this->option('logo')) {
                        $settings->storeLogoFromPath((string) $logo);
                    }

                    return [$resetReport, $rosterReport];
                });
            }
        } catch (Throwable $throwable) {
            $this->error('Prepare new year thất bại, dữ liệu đã rollback: '.$throwable->getMessage());

            return self::FAILURE;
        }

        $this->info($confirmed ? 'Đã chuẩn bị năm học mới '.$year.'.' : 'DRY-RUN: chưa reset/import dữ liệu.');
        $this->newLine();
        $this->line('Reset '.$deleteYear.' scoped records: '.($confirmed ? array_sum($resetReport['deleted'] ?? []) : $resetReport['total']));
        $this->line('Roster '.$year.': files '.$rosterReport['total_files'].', valid '.$rosterReport['valid_rows'].', errors '.$rosterReport['invalid_rows']);
        $this->line('Students created '.$rosterReport['created_students'].', updated '.$rosterReport['updated_students']);
        $this->line('Exams created by prepare workflow: 0');

        if (! $confirmed) {
            $this->warn('Thêm --confirm để thực hiện reset và import trong transaction.');
        }

        return self::SUCCESS;
    }
}
