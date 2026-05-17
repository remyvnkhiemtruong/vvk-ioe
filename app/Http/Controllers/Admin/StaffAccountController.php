<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class StaffAccountController extends Controller
{
    /** Danh sách nhân sự và trạng thái tài khoản */
    public function index(Request $request): View
    {
        $query = StaffProfile::with('user')
            ->where('status', 'active')
            ->orderBy('full_name');

        if ($request->filled('role')) {
            $query->where('suggested_role', $request->input('role'));
        }

        if ($request->filled('has_account')) {
            if ($request->input('has_account') === '1') {
                $query->whereNotNull('user_id');
            } else {
                $query->whereNull('user_id');
            }
        }

        $staff = $query->paginate(30)->withQueryString();

        return view('admin.staff.index', [
            'staff'    => $staff,
            'roleFilter' => $request->input('role'),
            'hasAccountFilter' => $request->input('has_account'),
        ]);
    }

    /** Tạo tài khoản cho một nhân sự */
    public function store(Request $request, StaffProfile $staff): RedirectResponse
    {
        if ($staff->user_id) {
            return back()->withErrors(['staff' => "Nhân sự {$staff->full_name} đã có tài khoản."]);
        }

        $validated = $request->validate([
            'role'     => ['required', 'in:teacher,exam_admin,proctor,admin,viewer'],
            'username' => ['nullable', 'string', 'max:50', 'regex:/^[a-zA-Z0-9_]+$/', 'unique:users,username'],
            'email'    => ['nullable', 'email', 'max:255', 'unique:users,email'],
        ]);

        $username = $this->resolveUsername($staff, $validated['username'] ?? null);
        $password = $this->resolvePassword($staff);

        try {
            DB::transaction(function () use ($staff, $validated, $username, $password) {
                $user = User::create([
                    'name'     => $staff->full_name,
                    'email'    => $validated['email'] ?? null,
                    'username' => $username,
                    'phone'    => null,
                    'password' => Hash::make($password),
                    'role'     => $validated['role'],
                    'status'   => 'active',
                ]);
                $user->assignRole($validated['role']);

                $staff->update(['user_id' => $user->id]);
            });
        } catch (\Exception $e) {
            return back()->withErrors(['staff' => 'Lỗi tạo tài khoản: '.$e->getMessage()]);
        }

        return back()->with('status', "Đã tạo tài khoản cho {$staff->full_name}. Mật khẩu mặc định: {$password}");
    }

    /** Tạo hàng loạt tài khoản giáo viên chưa có TK */
    public function bulk(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'role'   => ['required', 'in:teacher,proctor'],
            'filter' => ['required', 'in:suggested,all'],
        ]);

        $query = StaffProfile::whereNull('user_id')->where('status', 'active');

        if ($validated['filter'] === 'suggested') {
            $query->whereIn('suggested_role', ['teacher', 'proctor']);
        }

        $staffList = $query->get();
        $created   = 0;
        $skipped   = 0;
        $errors    = [];

        foreach ($staffList as $staff) {
            $role     = $validated['filter'] === 'suggested' && $staff->suggested_role
                ? $staff->suggested_role
                : $validated['role'];
            $username = $this->resolveUsername($staff);
            $password = $this->resolvePassword($staff);

            // Bỏ qua nếu username trùng
            if (User::where('username', $username)->exists()) {
                $skipped++;
                $errors[] = "{$staff->full_name}: username {$username} đã tồn tại.";

                continue;
            }

            try {
                DB::transaction(function () use ($staff, $role, $username, $password) {
                    $user = User::create([
                        'name'     => $staff->full_name,
                        'email'    => null,
                        'username' => $username,
                        'password' => Hash::make($password),
                        'role'     => $role,
                        'status'   => 'active',
                    ]);
                    $user->assignRole($role);
                    $staff->update(['user_id' => $user->id]);
                });
                $created++;
            } catch (\Exception $e) {
                $skipped++;
                $errors[] = "{$staff->full_name}: ".$e->getMessage();
            }
        }

        $message = "Đã tạo {$created} tài khoản";
        if ($skipped) {
            $message .= ", bỏ qua {$skipped} (trùng hoặc lỗi)";
        }
        $message .= '.';

        return back()
            ->with('status', $message)
            ->with('bulk_errors', $errors);
    }

    /** Xoá liên kết user khỏi staff (không xoá user) */
    public function unlink(StaffProfile $staff): RedirectResponse
    {
        if (! $staff->user_id) {
            return back()->withErrors(['staff' => 'Nhân sự này chưa có tài khoản để gỡ liên kết.']);
        }

        $staff->update(['user_id' => null]);

        return back()->with('status', "Đã gỡ liên kết tài khoản khỏi {$staff->full_name}.");
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function resolveUsername(StaffProfile $staff, ?string $preferred = null): string
    {
        if ($preferred) {
            return $preferred;
        }

        // Ưu tiên staff_code
        if ($staff->staff_code) {
            $base = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $staff->staff_code));
            if ($base !== '' && ! User::where('username', $base)->exists()) {
                return $base;
            }
        }

        // Fallback: tên không dấu
        $base = $this->toAsciiSlug($staff->full_name);
        $candidate = $base;
        $i = 2;
        while (User::where('username', $candidate)->exists()) {
            $candidate = $base.$i++;
        }

        return $candidate;
    }

    private function resolvePassword(StaffProfile $staff): string
    {
        // Ngày sinh định dạng DDMMYYYY nếu có
        if ($staff->date_of_birth) {
            return $staff->date_of_birth->format('dmY');
        }

        // Fallback an toàn
        return 'Vvk@12345';
    }

    private function toAsciiSlug(string $name): string
    {
        $map = [
            'à' => 'a','á' => 'a','â' => 'a','ã' => 'a','ä' => 'a','å' => 'a',
            'è' => 'e','é' => 'e','ê' => 'e','ë' => 'e',
            'ì' => 'i','í' => 'i','î' => 'i','ï' => 'i',
            'ò' => 'o','ó' => 'o','ô' => 'o','õ' => 'o','ö' => 'o',
            'ù' => 'u','ú' => 'u','û' => 'u','ü' => 'u',
            'ý' => 'y','ÿ' => 'y','ñ' => 'n','ç' => 'c',
            'đ' => 'd',
            'ă' => 'a','ắ' => 'a','ặ' => 'a','ằ' => 'a','ẵ' => 'a','ẳ' => 'a',
            'â' => 'a','ấ' => 'a','ậ' => 'a','ầ' => 'a','ẫ' => 'a','ẩ' => 'a',
            'ư' => 'u','ứ' => 'u','ự' => 'u','ừ' => 'u','ữ' => 'u','ử' => 'u',
            'ơ' => 'o','ớ' => 'o','ợ' => 'o','ờ' => 'o','ỡ' => 'o','ở' => 'o',
            'ê' => 'e','ế' => 'e','ệ' => 'e','ề' => 'e','ễ' => 'e','ể' => 'e',
            'ô' => 'o','ố' => 'o','ộ' => 'o','ồ' => 'o','ỗ' => 'o','ổ' => 'o',
            'ẹ' => 'e','ẻ' => 'e','ẽ' => 'e','ẽ' => 'e','ẻ' => 'e',
            'ị' => 'i','ỉ' => 'i','ĩ' => 'i',
            'ụ' => 'u','ủ' => 'u','ũ' => 'u',
            'ọ' => 'o','ỏ' => 'o','õ' => 'o',
            'ạ' => 'a','ả' => 'a','ã' => 'a',
            'ỵ' => 'y','ỷ' => 'y','ỹ' => 'y',
            'Đ' => 'd',
        ];

        $lower = mb_strtolower($name);
        $ascii = strtr($lower, $map);
        // Lấy ký tự cuối của mỗi từ làm username
        $parts = explode(' ', $ascii);
        $slug  = implode('', array_map(fn ($p) => preg_replace('/[^a-z0-9]/', '', $p), $parts));

        return $slug ?: 'staff'.time();
    }
}
