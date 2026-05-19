<?php

namespace App\Console\Commands;

use App\Services\AcademicYearRosterImportService;
use Illuminate\Console\Command;
use Throwable;

class SeedRosterAcademicYearCommand extends Command
{
    protected $signature = 'ioe:seed-roster
        {year=2026-2027 : Năm học nhận danh sách học sinh}
        {--path= : File hoặc thư mục chứa xlsx/xls/csv}
        {--dry-run : Chỉ preview/report, không import}
        {--confirm : Thực sự import trong transaction}';

    protected $description = 'Import danh sách học sinh năm học mới từ Excel, idempotent và không tạo kỳ thi/điểm.';

    public function handle(AcademicYearRosterImportService $importer): int
    {
        $year = (string) $this->argument('year');
        $path = (string) ($this->option('path') ?: storage_path('app/imports/'.$year));
        $confirmed = (bool) $this->option('confirm') && ! (bool) $this->option('dry-run');

        try {
            $report = $importer->importPath($path, $year, ! $confirmed, auth()->id());
        } catch (Throwable $throwable) {
            $this->error('Import roster thất bại: '.$throwable->getMessage());

            return self::FAILURE;
        }

        $this->info($confirmed ? 'Đã import roster '.$year.'.' : 'DRY-RUN: chỉ preview roster, chưa import.');
        $this->line('Đường dẫn: '.$path);
        $this->line('Files: '.$report['total_files'].' | Sheets: '.$report['total_sheets']);
        $this->line('Rows: '.$report['total_rows'].' | Valid: '.$report['valid_rows'].' | Errors: '.$report['invalid_rows']);
        $this->line('Students created: '.$report['created_students'].' | updated: '.$report['updated_students']);
        $this->line('Classes created: '.$report['created_classes'].' | grades created: '.$report['created_grades']);

        if (($report['errors'] ?? []) !== []) {
            $this->newLine();
            $this->warn('Một số dòng lỗi đầu tiên:');
            $this->table(
                ['File', 'Sheet', 'Row', 'Field', 'Message'],
                collect($report['errors'])->take(10)->map(fn ($error) => [
                    $error['file_name'] ?? '',
                    $error['sheet_name'] ?? '',
                    $error['row_number'] ?? '',
                    $error['field'] ?? '',
                    $error['message'] ?? '',
                ])->all()
            );
        }

        if (! $confirmed) {
            $this->warn('Thêm --confirm để ghi dữ liệu. Roster import không tạo exam/score/ranking/award.');
        }

        return self::SUCCESS;
    }
}
