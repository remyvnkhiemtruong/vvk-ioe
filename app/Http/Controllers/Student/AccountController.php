<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStudentAccountRequest;
use App\Models\Student;
use App\Models\User;
use App\Support\SchoolClassOptions;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function create(): View
    {
        return view('student.account.create', [
            'classes' => SchoolClassOptions::names(),
        ]);
    }

    public function store(StoreStudentAccountRequest $request): RedirectResponse
    {
        $student = Student::query()
            ->where('class_name', $request->string('class_name'))
            ->where(function ($query) use ($request) {
                $query->where('student_code', $request->string('credential'))
                    ->orWhere('identity_number', $request->string('credential'));
            })
            ->first();

        if (! $student) {
            throw ValidationException::withMessages([
                'credential' => 'Không tìm thấy học sinh khớp với lớp và mã đã nhập. Vui lòng liên hệ giáo viên phụ trách.',
            ]);
        }

        try {
            $user = DB::transaction(function () use ($request, $student) {
                $student = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();

                if ($student->user()->exists()) {
                    throw ValidationException::withMessages([
                        'credential' => 'Học sinh này đã có tài khoản. Vui lòng đăng nhập hoặc gửi yêu cầu quên mật khẩu.',
                    ]);
                }

                $user = User::create([
                    'name' => $student->full_name,
                    'email' => $request->input('email') ?: $student->email,
                    'username' => $student->student_code ?: 'hs'.$student->id,
                    'password' => Hash::make($request->input('password')),
                    'role' => 'student',
                    'status' => 'active',
                    'student_id' => $student->id,
                ]);
                $user->assignRole('student');

                return $user;
            });
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'credential' => 'Học sinh này đã có tài khoản hoặc email đã được sử dụng.',
            ]);
        }

        Auth::login($user);

        return redirect()->route('student.dashboard')->with('status', 'Tạo tài khoản học sinh thành công.');
    }
}
