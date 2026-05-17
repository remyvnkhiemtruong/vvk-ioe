<?php

namespace App\Console\Commands;

use App\Services\StudentImportService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class ImportStudentsCommand extends Command
{
    protected $signature = 'school:import-students {path : File Excel danh sách học sinh}
        {--header-row= : Dòng tiêu đề nếu muốn chỉ định thủ công}
        {--commit : Ghi dữ liệu sau khi preview hợp lệ}
        {--dry-run : Chỉ kiểm tra, không ghi dữ liệu}';

    protected $description = 'Import danh sách học sinh theo định dạng dữ liệu nhà trường.';

    public function handle(StudentImportService $service): int
    {
        return $this->runImport(fn () => $service->analyzePath(
            $this->argument('path'),
            basename($this->argument('path')),
            [],
            $this->option('header-row') ? (int) $this->option('header-row') : null,
        ), fn () => $service->commit($service->previewPath(
            $this->argument('path'),
            basename($this->argument('path')),
            [],
            $this->option('header-row') ? (int) $this->option('header-row') : null,
        )));
    }

    private function runImport(callable $analyze, callable $commit): int
    {
        $path = $this->argument('path');
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException("Không đọc được file: {$path}");
        }

        $analysis = $analyze();
        $this->report($analysis);

        if ((bool) $this->option('commit') && ! (bool) $this->option('dry-run')) {
            if ($analysis['invalid_rows'] > 0) {
                $this->error('File còn dòng lỗi, vui lòng xử lý trước khi commit.');

                return self::FAILURE;
            }
            $count = $commit();
            $this->info("Đã import {$count} dòng.");
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
