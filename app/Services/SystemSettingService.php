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
    public const ACTIVE_LANDING_STATUSES = [
        'preparing',
        'student_list_ready',
        'live_ready',
        'open',
        'running',
        'in_progress',
        'score_entering',
    ];

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
        return $this->text('site.info', 'site_name', 'IOE nội bộ');
    }

    public function contestName(): string
    {
        return $this->text('site.info', 'contest_name', 'IOE nội bộ Trường THPT Võ Văn Kiệt');
    }

    public function schoolYear(): string
    {
        return $this->text('site.info', 'school_year', '2026-2027');
    }

    public function contact(): array
    {
        return $this->get('site.contact', [
            'teacher_name' => 'Thầy Huỳnh Thanh Hào',
            'teacher_title' => 'Giáo viên tiếng Anh, phụ trách tổ chức thi IOE',
            'teacher_phone' => '',
            'teacher_email' => 'huynhthanhhaota@gmail.com',
            'support_name' => 'Trương Minh Khiêm',
            'support_title' => 'Cựu học sinh, học viên Trường Sĩ quan Thông tin',
            'support_phone' => '0385844458',
            'support_email' => 'truongminhkhiemvta@gmail.com',
            'developer_name' => 'Trương Minh Khiêm',
            'note' => 'Học sinh liên hệ giáo viên phụ trách hoặc bộ phận hỗ trợ khi cần mã học sinh, tài khoản hoặc thông tin ca thi.',
        ]);
    }

    public function accountOptions(): array
    {
        return $this->get('account.options', [
            'student_registration_enabled' => true,
            'student_code_lookup_url' => '',
            'student_code_help' => 'Nếu chưa biết mã học sinh, học sinh có thể liên hệ Trương Minh Khiêm để được hỗ trợ hoặc dùng link tra cứu khi nhà trường công bố.',
        ]);
    }

    public function studentAccountRegistrationEnabled(): bool
    {
        return (bool) ($this->accountOptions()['student_registration_enabled'] ?? true);
    }

    public function logoUrl(): ?string
    {
        $setting = $this->get('school.logo_path');
        $disk = is_array($setting) ? ($setting['disk'] ?? 'public') : 'public';
        $path = is_array($setting) ? ($setting['path'] ?? null) : null;

        if (! $path) {
            return null;
        }

        return $disk === 'public'
            ? '/storage/'.ltrim($path, '/')
            : Storage::disk($disk)->url($path);
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
        return Exam::where('level', 'school')
            ->where('school_year', $this->schoolYear())
            ->latest()
            ->first()
            ?: Exam::where('level', 'school')
                ->whereIn('status', self::ACTIVE_LANDING_STATUSES)
                ->latest()
                ->first()
            ?: new Exam([
                'name' => 'IOE nội bộ năm học '.$this->schoolYear(),
                'school_year' => $this->schoolYear(),
                'level' => 'school',
                'template_type' => 'truong',
                'external_platform_name' => 'IOE',
                'registration_mode' => 'admin_assign_session',
                'organizer_scope' => 'school',
                'target_grades' => [10, 11, 12],
                'max_score_rule' => [
                    'default_max_score' => 1000,
                    'award_min_score_percent' => 50,
                    'award_top_percent' => 50,
                ],
                'allow_student_edit' => true,
                'allow_student_session_change' => true,
                'require_session_choice' => false,
                'allow_personal_computer' => true,
                'auto_lock_full_sessions' => true,
                'show_public_stats' => true,
                'require_approval' => true,
                'publish_scores' => false,
                'show_countdown' => true,
                'countdown_mode' => 'auto',
                'status' => 'draft',
                'timezone' => 'Asia/Ho_Chi_Minh',
                'source' => 'admin_configured',
                'description' => 'Kỳ thi IOE nội bộ do nhà trường cấu hình cho năm học '.$this->schoolYear().'.',
            ]);
    }
}
