<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('student.profile.edit', [
            'student' => $request->user()->student,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['nullable', 'regex:/^(0|\+84)(3|5|7|8|9)[0-9]{8}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'phone.regex' => 'Số điện thoại Việt Nam không đúng định dạng.',
            'email.email' => 'Email không đúng định dạng.',
        ]);

        $request->user()->student->update($data);

        return redirect()->route('student.profile.edit')->with('status', 'Đã cập nhật thông tin liên hệ.');
    }
}
