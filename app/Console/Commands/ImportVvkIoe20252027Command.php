<?php

namespace App\Console\Commands;

use App\Services\IoeAttachedDataImportService;
use Illuminate\Console\Command;

class ImportVvkIoe20252027Command extends Command
{
    protected $signature = 'ioe:import-vvk-2025-2027
        {--base-path= : Thu muc chua cac file dinh kem, mac dinh la thu muc Downloads}
        {--dry-run : Chi doc/validate, khong ghi database hoac storage}';

    protected $description = 'Import tron bo du lieu dinh kem IOE VVK 2025-2027 va rollover hoc sinh sang nam hoc 2026-2027.';

    public function handle(IoeAttachedDataImportService $service): int
    {
        if (ini_get('memory_limit') !== '-1') {
            ini_set('memory_limit', '512M');
        }

        $basePath = (string) ($this->option('base-path') ?: dirname(base_path()));
        $summary = $service->import($service->defaultPaths($basePath), (bool) $this->option('dry-run'));

        $this->info($summary['dry_run'] ? 'Dry-run hoan tat, chua ghi du lieu.' : 'Import IOE VVK 2025-2027 hoan tat.');
        $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
