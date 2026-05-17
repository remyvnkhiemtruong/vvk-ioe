<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExamController extends Controller
{
    public function index(): View
    {
        return view('admin.exams.index', [
            'exams' => Exam::where('level', 'school')->withCount(['sessions', 'registrations'])->latest()->paginate(15),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Exam::create($this->payload($request));

        return back()->with('status', 'Đã tạo kỳ đăng ký cấp trường.');
    }

    public function update(Request $request, Exam $exam): RedirectResponse
    {
        $exam->update($this->payload($request));

        return back()->with('status', 'Đã cập nhật kỳ đăng ký.');
    }

    public function open(Exam $exam): RedirectResponse
    {
        $exam->update(['status' => 'open']);

        return back()->with('status', 'Đã mở đăng ký IOE cấp trường.');
    }

    public function close(Exam $exam): RedirectResponse
    {
        $exam->update(['status' => 'closed']);

        return back()->with('status', 'Đã đóng đăng ký.');
    }

    public function lock(Exam $exam): RedirectResponse
    {
        $exam->update(['status' => 'locked']);

        return back()->with('status', 'Đã khóa danh sách đăng ký.');
    }

    public function publishScores(Request $request, Exam $exam): RedirectResponse
    {
        $exam->update(['publish_scores' => ! $exam->publish_scores]);

        return back()->with('status', $exam->publish_scores ? 'Đã công bố điểm.' : 'Đã tắt công bố điểm.');
    }

    private function payload(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'school_year' => ['required', 'string', 'max:20'],
            'registration_opens_at' => ['nullable', 'date'],
            'registration_closes_at' => ['nullable', 'date', 'after_or_equal:registration_opens_at'],
            'exam_date' => ['nullable', 'date'],
            'exam_time' => ['nullable', 'date_format:H:i'],
            'target_grades' => ['nullable', 'array'],
            'registration_mode' => ['required', 'in:admin_assign_session,student_select_session'],
            'external_platform_name' => ['nullable', 'string', 'max:100'],
            'template_type' => ['nullable', 'in:truong,xa_phuong,tinh,quoc_gia,khac'],
            'organizer_scope' => ['nullable', 'in:school,cluster,district,province,national,custom'],
            'countdown_mode' => ['nullable', 'in:auto,open,close,exam'],
            'status' => ['required', 'in:draft,open,closed,assigning,locked,in_progress,completed'],
            'description' => ['nullable', 'string'],
        ]);

        return [
            ...$data,
            'level' => 'school',
            'template_type' => $data['template_type'] ?? 'truong',
            'external_platform_name' => $data['external_platform_name'] ?? 'IOE',
            'organizer_scope' => $data['organizer_scope'] ?? 'school',
            'target_grades' => collect($request->input('target_grades', [10, 11, 12]))->map(fn ($grade) => (int) $grade)->values()->all(),
            'allow_student_edit' => $request->boolean('allow_student_edit'),
            'allow_student_session_change' => $request->boolean('allow_student_session_change'),
            'require_session_choice' => ($data['registration_mode'] ?? 'admin_assign_session') === 'student_select_session',
            'allow_personal_computer' => $request->boolean('allow_personal_computer'),
            'auto_lock_full_sessions' => $request->boolean('auto_lock_full_sessions'),
            'show_public_stats' => $request->boolean('show_public_stats'),
            'require_approval' => $request->boolean('require_approval'),
            'publish_scores' => $request->boolean('publish_scores'),
            'show_countdown' => $request->boolean('show_countdown'),
            'countdown_mode' => $data['countdown_mode'] ?? 'auto',
        ];
    }
}
