<?php

namespace App\Services;

use App\Models\ExamRegistration;
use App\Models\Student;

class StudentGradeResolver
{
    public function resolve(Student|ExamRegistration|array|null $source): ?int
    {
        if (! $source) {
            return null;
        }

        $grade = $this->value($source, 'grade');
        if (in_array((int) $grade, [10, 11, 12], true)) {
            return (int) $grade;
        }

        $className = (string) $this->value($source, 'class_name');
        if (preg_match('/^(10|11|12)/', trim($className), $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function value(Student|ExamRegistration|array $source, string $key): mixed
    {
        if (is_array($source)) {
            return $source[$key] ?? null;
        }

        return $source->{$key} ?? null;
    }
}
