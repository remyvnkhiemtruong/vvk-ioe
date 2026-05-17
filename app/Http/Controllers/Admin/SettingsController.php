<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SystemSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(SystemSettingService $settings): View
    {
        return view('admin.settings.index', [
            'settings' => $settings,
            'allSettings' => $settings->all(),
            'exam' => $settings->currentSchoolExam(),
        ]);
    }

    public function update(Request $request, SystemSettingService $settings): RedirectResponse
    {
        $data = $request->validate([
            'school.name' => ['required', 'string', 'max:255'],
            'school.address' => ['nullable', 'string', 'max:255'],
            'school.website' => ['nullable', 'url', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'site.site_name' => ['required', 'string', 'max:255'],
            'site.contest_name' => ['required', 'string', 'max:255'],
            'site.school_year' => ['required', 'string', 'max:20'],
            'site.home_description' => ['nullable', 'string', 'max:1000'],
            'site.guide_content' => ['nullable', 'string'],
            'contact.teacher_name' => ['nullable', 'string', 'max:255'],
            'contact.phone' => ['nullable', 'string', 'max:30'],
            'contact.email' => ['nullable', 'email', 'max:255'],
            'contact.note' => ['nullable', 'string', 'max:1000'],
            'exam.name' => ['required', 'string', 'max:255'],
            'exam.school_year' => ['required', 'string', 'max:20'],
            'exam.registration_opens_at' => ['nullable', 'date'],
            'exam.registration_closes_at' => ['nullable', 'date', 'after_or_equal:exam.registration_opens_at'],
            'exam.exam_date' => ['nullable', 'date'],
            'exam.exam_time' => ['nullable', 'date_format:H:i'],
            'exam.countdown_mode' => ['required', 'in:auto,open,close,exam'],
            'exam.registration_mode' => ['nullable', 'in:admin_assign_session,student_select_session'],
            'exam.status' => ['required', 'in:draft,open,closed,assigning,locked,in_progress,completed'],
            'exam.description' => ['nullable', 'string', 'max:1000'],
            'mail.host' => ['nullable', 'string', 'max:255'],
            'mail.port' => ['nullable', 'integer', 'min:1'],
            'mail.username' => ['nullable', 'string', 'max:255'],
            'mail.password' => ['nullable', 'string', 'max:255'],
            'mail.encryption' => ['nullable', 'in:tls,ssl,null'],
            'mail.from_address' => ['nullable', 'email', 'max:255'],
            'mail.from_name' => ['nullable', 'string', 'max:255'],
            'security.auto_logout_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'security.max_login_attempts' => ['nullable', 'integer', 'min:3', 'max:20'],
        ]);

        $settings->set('school.info', $data['school']);
        $settings->set('site.info', $data['site']);
        $settings->set('site.contact', $data['contact'] ?? []);
        $settings->set('security.options', $data['security'] ?? []);
        $settings->saveMailSettings($data['mail'] ?? []);

        if ($request->hasFile('logo')) {
            $settings->storeLogo($request->file('logo'));
        }

        $exam = $settings->currentSchoolExam();
        $exam->update([
            ...$data['exam'],
            'level' => 'school',
            'template_type' => 'truong',
            'external_platform_name' => 'IOE',
            'organizer_scope' => 'school',
            'registration_mode' => $request->input('exam.registration_mode', 'admin_assign_session'),
            'target_grades' => [10, 11, 12],
            'allow_student_edit' => $request->boolean('options.allow_student_edit'),
            'allow_student_session_change' => $request->boolean('options.allow_student_session_change'),
            'require_session_choice' => $request->input('exam.registration_mode', 'admin_assign_session') === 'student_select_session',
            'allow_personal_computer' => $request->boolean('options.allow_personal_computer'),
            'auto_lock_full_sessions' => $request->boolean('options.auto_lock_full_sessions'),
            'show_public_stats' => $request->boolean('options.show_public_stats'),
            'require_approval' => $request->boolean('options.require_approval'),
            'publish_scores' => $request->boolean('score.publish_scores'),
            'show_countdown' => $request->boolean('options.show_countdown'),
        ]);

        $settings->set('score.options', [
            'publish_scores' => $request->boolean('score.publish_scores'),
            'show_ranking' => $request->boolean('score.show_ranking'),
            'ranking_scope' => $request->input('score.ranking_scope', 'grade'),
            'public_scoreboard' => $request->boolean('score.public_scoreboard'),
        ]);

        return back()->with('status', 'Đã cập nhật cài đặt hệ thống.');
    }

    public function testMail(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'test_email' => ['required', 'email'],
        ]);

        try {
            Mail::raw('Email kiểm tra từ hệ thống IOE cấp trường Trường THPT Võ Văn Kiệt.', function ($message) use ($data) {
                $message->to($data['test_email'])->subject('Kiểm tra SMTP IOE cấp trường');
            });
        } catch (\Throwable $exception) {
            return back()->withErrors(['test_email' => 'Không gửi được email kiểm tra: '.$exception->getMessage()]);
        }

        return back()->with('status', 'Đã gửi email kiểm tra SMTP.');
    }
}
