<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Student;
use App\Services\StudentClassOptionService;
use App\Support\StudentNameNormalizer;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StudentCodeLookupController extends Controller
{
    public function create(StudentClassOptionService $classes): View
    {
        return view('student.code-lookup', [
            'classes' => $classes->names(),
            'result' => null,
            'status' => null,
        ]);
    }

    public function store(Request $request, StudentClassOptionService $classes): View
    {
        $classOptions = $classes->names();

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'class_name' => ['required', 'string', 'max:50', function (string $attribute, mixed $value, \Closure $fail) use ($classOptions): void {
                if (! $classOptions->contains(trim((string) $value))) {
                    $fail('Lớp phải được chọn từ danh sách lớp năm học hiện tại.');
                }
            }],
            'date_of_birth' => ['required', 'date'],
            'identity_number' => ['nullable', 'string', 'max:30'],
        ], [
            'full_name.required' => 'Vui lòng nhập họ và tên.',
            'class_name.required' => 'Vui lòng chọn lớp.',
            'date_of_birth.required' => 'Vui lòng nhập ngày sinh.',
        ]);

        if ($classOptions->isEmpty()) {
            throw ValidationException::withMessages([
                'class_name' => 'Chưa có dữ liệu lớp. Vui lòng liên hệ quản trị viên hoặc import danh sách học sinh trước.',
            ]);
        }

        $year = AcademicYear::where('is_current', true)->first()
            ?: AcademicYear::where('is_active', true)->latest('id')->first();
        $normalizedName = StudentNameNormalizer::normalize($data['full_name']);
        $className = trim($data['class_name']);
        $identity = trim((string) ($data['identity_number'] ?? ''));

        $students = Student::query()
            ->when($year, fn ($query) => $query->where('academic_year_id', $year->id))
            ->where('status', 'active')
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
            'classes' => $classOptions,
            'result' => $students->count() === 1 ? $students->first() : null,
            'status' => $status,
        ]);
    }
}
