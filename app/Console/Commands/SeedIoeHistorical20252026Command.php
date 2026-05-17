<?php

namespace App\Console\Commands;

use App\Services\HistoricalIoeImportService;
use Illuminate\Console\Command;

class SeedIoeHistorical20252026Command extends Command
{
    protected $signature = 'ioe:seed-2025-2026 {--dry-run : Chạy thử trong transaction rồi rollback}';

    protected $description = 'Seed/import dữ liệu lịch sử IOE năm học 2025-2026 và rollover sang 2026-2027.';

    public function handle(HistoricalIoeImportService $service): int
    {
        $summary = $service->import((bool) $this->option('dry-run'));

        $this->info($summary['dry_run'] ? 'Dry-run hoàn tất, không ghi dữ liệu.' : 'Seed IOE 2025-2026 hoàn tất.');

        foreach ($summary as $key => $value) {
            if (is_array($value)) {
                $this->line($key.': '.json_encode($value, JSON_UNESCAPED_UNICODE));
                continue;
            }

            $this->line($key.': '.$value);
        }

        return self::SUCCESS;
    }
}
