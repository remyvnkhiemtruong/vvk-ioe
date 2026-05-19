<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStudentAccountRequest;
use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\User;
use App\Services\StudentClassOptionService;
use App\Services\SystemSettingService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function create(SystemSettingService $settings, StudentClassOptionService $classes): View
    {
        return view('student.account.create', [
            'classes' => $classes->names(),
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

        $student = $this->findCurrentStudent(
            $request->string('class_name')->toString(),
            $request->string('credential')->toString(),
            (bool) data_get($settings->accountOptions(), 'allow_ioe_id_as_credential', false)
        );

        if (! $student) {
            throw ValidationException::withMessages([
                'credential' => 'Không tìm thấy học sinh active thuộc năm học hiện tại khớp với lớp và mã đã nhập. Vui lòng tra cứu mã hoặc liên hệ giáo viên phụ trách.',
            ]);
        }

        if ($student->status !== 'active') {
            throw ValidationException::withMessages([
                'credential' => 'Hồ sơ học sinh chưa active nên chưa thể tạo tài khoản.',
            ]);
        }

        $chosenUsername = trim((string) $request->input('username'));
        $fallbackUsername = $student->student_code ?: ($student->ioe_account_id ?: 'hs'.$student->id);
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
                    'name' => $student->full_name,
                    'email' => $request->input('email') ?: $student->email,
                    'username' => $username,
                    'phone' => $request->input('phone') ?: $student->phone,
                    'password' => Hash::make($request->input('password')),
                    'role' => 'student',
                    'status' => 'active',
                    'student_id' => $student->id,
                ]);
                $user->assignRole('student');

                if ($request->hasFile('avatar')) {
                    $path = $request->file('avatar')->store('avatars', 'public');
                    $user->update(['avatar_path' => $path]);
                }

                $updates = [];
                if ($request->filled('phone') && blank($student->phone)) {
                    $updates['phone'] = $request->input('phone');
                }
                if ($request->filled('email') && blank($student->email)) {
                    $updates['email'] = $request->input('email');
                }
                if ($updates !== []) {
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

    private function findCurrentStudent(string $className, string $credential, bool $allowIoeId): ?Student
    {
        $year = AcademicYear::where('is_current', true)->first()
            ?: AcademicYear::where('is_active', true)->latest('id')->first();

        return Student::query()
            ->when($year, fn ($query) => $query->where('academic_year_id', $year->id))
            ->where('status', 'active')
            ->where('class_name', trim($className))
            ->where(function ($query) use ($credential, $allowIoeId) {
                $query->where('student_code', $credential)
                    ->orWhere('identity_number', $credential)
                    ->orWhere('ministry_identifier', $credential);

                if ($allowIoeId) {
                    $query->orWhere('ioe_account_id', $credential);
                }
            })
            ->first();
    }
}
