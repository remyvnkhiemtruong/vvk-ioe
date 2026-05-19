<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class StudentClassOptionService
{
    public function names(?string $yearCode = null): Collection
    {
        $year = $this->academicYear($yearCode);

        $classes = $this->fromSchoolClasses($year);

        if ($classes->isEmpty()) {
            $classes = $this->fromStudents($year, $yearCode);
        }

        return $this->naturalSort($classes);
    }

    public function contains(string $className, ?string $yearCode = null): bool
    {
        $className = trim($className);

        if ($className === '') {
            return false;
        }

        return $this->names($yearCode)->contains($className);
    }

    public function naturalSort(Collection|array $classes): Collection
    {
        return collect($classes)
            ->filter(fn ($class) => trim((string) $class) !== '')
            ->map(fn ($class) => trim((string) $class))
            ->unique()
            ->sort(fn (string $a, string $b) => $this->compare($a, $b))
            ->values();
    }

    private function academicYear(?string $yearCode): ?AcademicYear
    {
        if (! Schema::hasTable('academic_years')) {
            return null;
        }

        if ($yearCode) {
            return AcademicYear::where('code', $yearCode)->first();
        }

        return AcademicYear::where('is_current', true)->first()
            ?: AcademicYear::where('is_active', true)->latest('id')->first();
    }

    private function fromSchoolClasses(?AcademicYear $year): Collection
    {
        if (! Schema::hasTable('school_classes')) {
            return collect();
        }

        $query = SchoolClass::query()->active();

        if ($year && Schema::hasColumn('school_classes', 'academic_year_id')) {
            $query->where('academic_year_id', $year->id);
        } elseif ($year && Schema::hasColumn('school_classes', 'school_year')) {
            $query->where('school_year', $year->code);
        }

        return $query->pluck('class_name');
    }

    private function fromStudents(?AcademicYear $year, ?string $yearCode): Collection
    {
        if (! Schema::hasTable('students')) {
            return collect();
        }

        $query = Student::query()
            ->where('status', 'active')
            ->whereNotNull('class_name');

        if ($year && Schema::hasColumn('students', 'academic_year_id')) {
            $query->where('academic_year_id', $year->id);
        } elseif ($yearCode && Schema::hasColumn('students', 'source_academic_year')) {
            $query->where('source_academic_year', $yearCode);
        }

        return $query->distinct()->pluck('class_name');
    }

    private function compare(string $a, string $b): int
    {
        $left = $this->classParts($a);
        $right = $this->classParts($b);

        return [$left['grade'], $left['letters'], $left['number'], $left['suffix'], $a]
            <=> [$right['grade'], $right['letters'], $right['number'], $right['suffix'], $b];
    }

    private function classParts(string $className): array
    {
        $normalized = strtoupper(preg_replace('/\s+/', '', $className) ?: $className);

        preg_match('/^(?<grade>\d{1,2})(?<letters>[A-Z]+)?(?<number>\d+)?(?<suffix>.*)$/u', $normalized, $matches);

        return [
            'grade' => isset($matches['grade']) ? (int) $matches['grade'] : PHP_INT_MAX,
            'letters' => $matches['letters'] ?? '',
            'number' => ($matches['number'] ?? '') === '' ? 0 : (int) $matches['number'],
            'suffix' => $matches['suffix'] ?? '',
        ];
    }
}
