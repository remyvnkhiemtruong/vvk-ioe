<?php

namespace App\Console\Commands;

use App\Services\BusinessDataResetImportService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Throwable;

class ResetImportStudentsCommand extends Command
{
    protected $signature = 'ioe:reset-import
        {file : Đường dẫn file Excel danh sách học sinh}
        {--school-year=2025-2026 : Năm học áp dụng cho hồ sơ học sinh}
        {--confirm : Thực hiện clear và import. Nếu thiếu option này chỉ dry-run}
        {--reset-award-rules : Xóa cả cấu hình rule xếp giải hiện có}';

    protected $description = 'Clear dữ liệu nghiệp vụ IOE và import lại danh sách học sinh từ Excel.';

    public function handle(BusinessDataResetImportService $service): int
    {
        $path = (string) $this->argument('file');
        $schoolYear = (string) $this->option('school-year');
        $resetAwardRules = (bool) $this->option('reset-award-rules');

        if (! is_file($path)) {
            $this->error('Không tìm thấy file: '.$path);

            return self::FAILURE;
        }

        try {
            $report = $this->option('confirm')
                ? $service->resetAndImportPath($path, $schoolYear, $resetAwardRules)
                : $service->dryRunFromPath($path, $schoolYear, $resetAwardRules);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error('Reset/import thất bại, dữ liệu đã được rollback: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info($report['dry_run'] ? 'DRY-RUN: chưa xóa hoặc import dữ liệu.' : 'Đã clear và import dữ liệu thành công.');
        $this->line('Năm học: '.$report['school_year']);
        $this->line('Dòng Excel: '.$report['total_rows']);
        $this->line('Hợp lệ: '.$report['valid_rows']);
        $this->line('Có lỗi: '.$report['invalid_rows']);
        $this->line('Học sinh tạo mới: '.$report['created']);
        $this->line('Học sinh cập nhật: '.$report['updated']);
        $this->line('Tổng bản ghi nghiệp vụ '.($report['dry_run'] ? 'sẽ xóa' : 'đã xóa').': '.$report['cleared_total']);

        $this->newLine();
        $this->table(
            ['Bảng', $report['dry_run'] ? 'Sẽ xóa' : 'Đã xóa'],
            collect($report['cleared'])->map(fn ($count, $table) => [$table, $count])->values()->all()
        );

        if ($report['dry_run']) {
            $this->warn('Thêm --confirm để thực hiện transaction clear/import thật.');
        }

        return self::SUCCESS;
    }
}
