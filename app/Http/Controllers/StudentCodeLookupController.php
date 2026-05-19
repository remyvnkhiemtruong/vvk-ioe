<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Support\SchoolClassOptions;
use App\Support\StudentNameNormalizer;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentCodeLookupController extends Controller
{
    public function create(): View
    {
        return view('student.code-lookup', [
            'classes' => SchoolClassOptions::names(),
            'result' => null,
            'status' => null,
        ]);
    }

    public function store(Request $request): View
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'class_name' => ['required', 'string', 'max:50'],
            'date_of_birth' => ['required', 'date'],
            'identity_number' => ['nullable', 'string', 'max:30'],
        ], [
            'full_name.required' => 'Vui lòng nhập họ và tên.',
            'class_name.required' => 'Vui lòng nhập lớp.',
            'date_of_birth.required' => 'Vui lòng nhập ngày sinh.',
        ]);

        $normalizedName = StudentNameNormalizer::normalize($data['full_name']);
        $className = trim($data['class_name']);
        $identity = trim((string) ($data['identity_number'] ?? ''));

        $students = Student::query()
            ->where('class_name', $className)
            ->whereDate('date_of_birth', $data['date_of_birth'])
            ->when($identity !== '', fn ($query) => $query->where(function ($q) use ($identity) {
                $q->where('identity_number', $identity)
                    ->orWhere('ministry_identifier', $identity);
            }))
            ->limit(20)
            ->get()
            ->filter(fn (Student $student) => StudentNameNormalizer::normalize($student->normalized_name ?: $student->full_name) === $normalizedName)
            ->values();

        $status = match (true) {
            $students->count() === 1 => 'found',
            $students->count() > 1 => 'multiple',
            default => 'not_found',
        };

        return view('student.code-lookup', [
            'classes' => SchoolClassOptions::names(),
            'result' => $students->count() === 1 ? $students->first() : null,
            'status' => $status,
        ]);
    }
}
