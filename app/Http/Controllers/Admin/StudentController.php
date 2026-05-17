<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ArrayExport;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Support\SchoolClassOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class StudentController extends Controller
{
    public function index(Request $request): View
    {
        $students = Student::query()
            ->when($request->filled('q'), fn ($q) => $q->where(function ($search) use ($request) {
                $search->where('full_name', 'like', '%'.$request->q.'%')
                    ->orWhere('student_code', 'like', '%'.$request->q.'%')
                    ->orWhere('identity_number', 'like', '%'.$request->q.'%')
                    ->orWhere('class_name', 'like', '%'.$request->q.'%');
            }))
            ->when($request->filled('grade'), fn ($q) => $q->where('grade', $request->grade))
            ->when($request->filled('class_name'), fn ($q) => $q->where('class_name', $request->class_name))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderBy('class_name')
            ->orderBy('full_name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.students.index', [
            'students' => $students,
            'classes' => SchoolClassOptions::names(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Student::create($this->payload($request));

        return back()->with('status', 'Đã thêm học sinh.');
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $student->update($this->payload($request, $student));

        return back()->with('status', 'Đã cập nhật học sinh.');
    }

    public function toggle(Student $student): RedirectResponse
    {
        $student->update(['status' => $student->status === 'active' ? 'inactive' : 'active']);
        $student->user?->update(['status' => $student->status]);

        return back()->with('status', 'Đã cập nhật trạng thái học sinh.');
    }

    public function export()
    {
        $canViewSensitive = request()->user()->can('students.view_sensitive');

        $rows = Student::orderBy('class_name')->orderBy('full_name')->get()
            ->map(fn ($student) => [
                $student->full_name,
                $student->class_name,
                $student->grade,
                $student->student_code,
                $canViewSensitive ? $student->identity_number : $student->maskedIdentity(),
                optional($student->date_of_birth)->format('d/m/Y'),
                $student->gender,
                $student->status,
            ])
            ->all();

        return Excel::download(new ArrayExport(['Họ tên', 'Lớp', 'Khối', 'Mã học sinh', 'CCCD/Mã định danh', 'Ngày sinh', 'Giới tính', 'Trạng thái'], $rows), 'danh-sach-hoc-sinh.xlsx');
    }

    private function payload(Request $request, ?Student $student = null): array
    {
        return $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'grade' => ['required', 'integer', 'in:10,11,12'],
            'class_name' => ['required', 'string', 'max:50'],
            'student_code' => ['nullable', 'string', 'max:100', Rule::unique('students', 'student_code')->ignore($student)],
            'identity_number' => ['nullable', 'string', 'max:20', Rule::unique('students', 'identity_number')->ignore($student)],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }
}
