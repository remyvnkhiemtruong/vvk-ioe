<?php

namespace App\Support;

use App\Services\StudentClassOptionService;
use Illuminate\Support\Collection;

class SchoolClassOptions
{
    public static function names(?string $yearCode = null): Collection
    {
        return app(StudentClassOptionService::class)->names($yearCode);
    }

    public static function contains(string $className, ?string $yearCode = null): bool
    {
        return app(StudentClassOptionService::class)->contains($className, $yearCode);
    }
}
