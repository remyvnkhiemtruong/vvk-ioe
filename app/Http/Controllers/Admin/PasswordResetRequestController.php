<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PasswordResetRequestController extends Controller
{
    public function index(): View
    {
        return view('admin.password-reset-requests.index', [
            'requests' => PasswordResetRequest::with(['student', 'user', 'resolver'])->latest()->paginate(20),
        ]);
    }

    public function resolve(Request $request, PasswordResetRequest $passwordResetRequest): RedirectResponse
    {
        $data = $request->validate([
            'temporary_password' => ['required', 'confirmed', 'min:8', 'regex:/^(?=.*[A-Za-z])(?=.*\d).+$/'],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ], [
            'temporary_password.required' => 'Vui lòng nhập mật khẩu tạm thời.',
            'temporary_password.confirmed' => 'Xác nhận mật khẩu tạm thời không khớp.',
            'temporary_password.regex' => 'Mật khẩu tạm thời phải có cả chữ và số.',
        ]);

        abort_unless($passwordResetRequest->user, 422, 'Yêu cầu chưa liên kết tài khoản người dùng.');

        $passwordResetRequest->user->forceFill([
            'password' => Hash::make($data['temporary_password']),
        ])->save();

        $passwordResetRequest->update([
            'status' => 'resolved',
            'admin_note' => $data['admin_note'] ?? null,
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        return back()->with('status', 'Đã đặt mật khẩu tạm thời. Hãy thông báo trực tiếp cho học sinh và yêu cầu đổi lại sau khi đăng nhập.');
    }
}
