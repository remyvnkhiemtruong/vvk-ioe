<?php

namespace App\Console\Commands;

use App\Services\AcademicResultImportService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class ImportAcademicResultsCommand extends Command
{
    protected $signature = 'school:import-academic-results {path : File Excel kết quả học tập}
        {--header-row= : Dòng tiêu đề nếu muốn chỉ định thủ công}
        {--commit : Ghi dữ liệu sau khi preview hợp lệ}
        {--dry-run : Chỉ kiểm tra, không ghi dữ liệu}';

    protected $description = 'Import kết quả học tập theo Mã học sinh + năm học + học kỳ/giai đoạn.';

    public function handle(AcademicResultImportService $service): int
    {
        $path = $this->argument('path');
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException("Không đọc được file: {$path}");
        }

        $headerRow = $this->option('header-row') ? (int) $this->option('header-row') : null;
        $analysis = $service->analyzePath($path, basename($path), $headerRow);
        $this->report($analysis);

        if ((bool) $this->option('commit') && ! (bool) $this->option('dry-run')) {
            if ($analysis['invalid_rows'] > 0) {
                $this->error('File còn dòng lỗi, vui lòng xử lý trước khi commit.');

                return self::FAILURE;
            }
            $count = $service->commit($service->previewPath($path, basename($path), $headerRow));
            $this->info("Đã import {$count} dòng kết quả học tập.");
        } else {
            $this->info('Dry-run hoàn tất, chưa ghi dữ liệu.');
        }

        return self::SUCCESS;
    }

    private function report(array $analysis): void
    {
        $this->line("Header row: {$analysis['header_row']}. Hợp lệ: {$analysis['valid_rows']}/{$analysis['total_rows']}. Lỗi: {$analysis['invalid_rows']}.");
        foreach (array_slice($analysis['errors'] ?? [], 0, 10) as $error) {
            $this->warn('Dòng '.$error['row'].': '.implode('; ', $error['messages'] ?? []));
        }
    }
}
