<?php

namespace Database\Seeders;

use App\Services\HistoricalIoeImportService;
use Illuminate\Database\Seeder;

class IoeHistorical20252026Seeder extends Seeder
{
    public function run(): void
    {
        app(HistoricalIoeImportService::class)->import(false);
    }
}
