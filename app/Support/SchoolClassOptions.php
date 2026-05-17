<?php

namespace App\Support;

use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class SchoolClassOptions
{
    public static function names(): Collection
    {
        if (Schema::hasTable('school_classes') && SchoolClass::query()->active()->exists()) {
            return SchoolClass::query()
                ->active()
                ->orderBy('grade')
                ->orderBy('class_name')
                ->pluck('class_name');
        }

        if (Schema::hasTable('students')) {
            return Student::query()
                ->where('status', 'active')
                ->distinct()
                ->orderBy('class_name')
                ->pluck('class_name');
        }

        return collect();
    }

    public static function contains(string $className): bool
    {
        if (Schema::hasTable('school_classes') && SchoolClass::query()->active()->exists()) {
            return SchoolClass::query()
                ->active()
                ->where('class_name', $className)
                ->exists();
        }

        if (Schema::hasTable('students')) {
            return Student::query()
                ->where('status', 'active')
                ->where('class_name', $className)
                ->exists();
        }

        return false;
    }
}
