<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\SystemSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class SystemSettingService
{
    public function all(): array
    {
        return SystemSetting::query()
            ->pluck('value', 'key')
            ->map(fn ($value) => is_array($value) ? $value : [])
            ->all();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return SystemSetting::where('key', $key)->first()?->value ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        SystemSetting::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public function text(string $key, string $path, string $default): string
    {
        return (string) Arr::get($this->get($key, []), $path, $default);
    }

    public function schoolName(): string
    {
        return $this->text('school.info', 'name', 'Trường THPT Võ Văn Kiệt');
    }

    public function siteName(): string
    {
        return $this->text('site.info', 'site_name', 'IOE cấp trường');
    }

    public function contestName(): string
    {
        return $this->text('site.info', 'contest_name', 'Đăng ký dự thi Olympic Tiếng Anh trên Internet cấp trường');
    }

    public function schoolYear(): string
    {
        return $this->text('site.info', 'school_year', '2025-2026');
    }

    public function contact(): array
    {
        return $this->get('site.contact', [
            'teacher_name' => 'Giáo viên phụ trách IOE',
            'phone' => '',
            'email' => '',
            'note' => 'Học sinh liên hệ giáo viên phụ trách khi cần hỗ trợ tài khoản, thông tin cá nhân hoặc ca thi.',
        ]);
    }

    public function logoUrl(): ?string
    {
        $setting = $this->get('school.logo_path');
        $disk = is_array($setting) ? ($setting['disk'] ?? 'public') : 'public';
        $path = is_array($setting) ? ($setting['path'] ?? null) : null;

        return $path ? Storage::disk($disk)->url($path) : null;
    }

    public function storeLogo(UploadedFile $file): void
    {
        $path = $file->store('school', 'public');

        $this->set('school.logo_path', [
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    public function saveMailSettings(array $mail): void
    {
        $current = $this->get('mail.smtp', []);
        if (filled($mail['password'] ?? null)) {
            $mail['password'] = Crypt::encryptString($mail['password']);
            $mail['password_set'] = true;
        } else {
            $mail['password'] = $current['password'] ?? null;
            $mail['password_set'] = (bool) ($current['password_set'] ?? false);
        }

        $this->set('mail.smtp', $mail);
    }

    public function currentSchoolExam(): Exam
    {
        return Exam::where('level', 'school')->latest()->first()
            ?: Exam::create([
                'name' => 'IOE cấp trường năm học 2025-2026',
                'school_year' => $this->schoolYear(),
                'level' => 'school',
                'target_grades' => [10, 11, 12],
                'allow_student_edit' => true,
                'allow_student_session_change' => true,
                'require_session_choice' => true,
                'allow_personal_computer' => true,
                'auto_lock_full_sessions' => true,
                'show_public_stats' => true,
                'require_approval' => true,
                'publish_scores' => false,
                'show_countdown' => true,
                'countdown_mode' => 'auto',
                'status' => 'draft',
                'description' => 'Đăng ký dự thi Olympic Tiếng Anh trên Internet cấp trường.',
            ]);
    }
}
