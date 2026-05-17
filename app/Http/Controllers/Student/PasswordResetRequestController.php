<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetRequest;
use App\Models\Student;
use App\Support\SchoolClassOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PasswordResetRequestController extends Controller
{
    public function create(): View
    {
        return view('student.password-reset-requests.create', [
            'classes' => SchoolClassOptions::names(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'class_name' => ['required', 'string', 'max:50'],
            'credential' => ['required', 'string', 'max:100'],
            'request_note' => ['nullable', 'string', 'max:1000'],
        ], [
            'class_name.required' => 'Vui lòng chọn lớp.',
            'credential.required' => 'Vui lòng nhập mã học sinh hoặc CCCD/mã định danh.',
        ]);

        $student = Student::query()
            ->where('class_name', $data['class_name'])
            ->where(function ($query) use ($data) {
                $query->where('student_code', $data['credential'])
                    ->orWhere('identity_number', $data['credential']);
            })
            ->first();

        if ($student?->user) {
            PasswordResetRequest::create([
                'student_id' => $student->id,
                'user_id' => $student->user->id,
                'status' => 'pending',
                'request_note' => $data['request_note'] ?? null,
            ]);
        }

        return back()->with('status', 'Nếu thông tin khớp với dữ liệu đã import, yêu cầu cấp lại mật khẩu đã được gửi cho giáo viên/Admin xác minh.');
    }
}
