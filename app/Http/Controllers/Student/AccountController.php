<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStudentAccountRequest;
use App\Models\Student;
use App\Models\User;
use App\Services\SystemSettingService;
use App\Support\SchoolClassOptions;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function create(SystemSettingService $settings): View
    {
        return view('student.account.create', [
            'classes' => SchoolClassOptions::names(),
            'settings' => $settings,
            'account' => $settings->accountOptions(),
            'contact' => $settings->contact(),
            'registrationEnabled' => $settings->studentAccountRegistrationEnabled(),
        ]);
    }

    public function store(StoreStudentAccountRequest $request, SystemSettingService $settings): RedirectResponse
    {
        if (! $settings->studentAccountRegistrationEnabled()) {
            throw ValidationException::withMessages([
                'credential' => 'Nhà trường đang tạm khóa chức năng tạo tài khoản học sinh. Vui lòng xem hướng dẫn liên hệ trên trang này.',
            ]);
        }

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

        // Xử lý username ưu tiên: user chọn → student_code → hs{id tạm}
        $chosenUsername = trim((string) $request->input('username'));
        $fallbackUsername = $student->student_code
            ? $student->student_code
            : 'hs'.($student->id ?? time());

        $username = $chosenUsername !== '' ? $chosenUsername : $fallbackUsername;

        try {
            $user = DB::transaction(function () use ($request, $student, $username) {
                $student = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();

                if ($student->user()->exists()) {
                    throw ValidationException::withMessages([
                        'credential' => 'Học sinh này đã có tài khoản. Vui lòng đăng nhập hoặc gửi yêu cầu quên mật khẩu.',
                    ]);
                }

                $user = User::create([
                    'name'     => $student->full_name,
                    'email'    => $request->input('email') ?: $student->email,
                    'username' => $username,
                    'phone'    => $request->input('phone') ?: $student->phone,
                    'password' => Hash::make($request->input('password')),
                    'role'     => 'student',
                    'status'   => 'active',
                    'student_id' => $student->id,
                ]);
                $user->assignRole('student');

                // Xử lý ảnh đại diện
                if ($request->hasFile('avatar')) {
                    $path = $request->file('avatar')->store('avatars', 'public');
                    $user->update(['avatar_path' => $path]);
                }

                // Đồng bộ phone/email từ user nhập vào student record
                $updates = [];
                if ($request->filled('phone') && blank($student->phone)) {
                    $updates['phone'] = $request->input('phone');
                }
                if ($request->filled('email') && blank($student->email)) {
                    $updates['email'] = $request->input('email');
                }
                if ($updates) {
                    $student->update($updates);
                }

                return $user;
            });
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'credential' => 'Học sinh này đã có tài khoản hoặc username/email đã được sử dụng.',
            ]);
        }

        Auth::login($user);

        return redirect()->route('student.dashboard')->with('status', 'Tạo tài khoản học sinh thành công.');
    }
}
