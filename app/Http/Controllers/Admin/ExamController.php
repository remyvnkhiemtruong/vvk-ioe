<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\ExamLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExamController extends Controller
{
    public function index(): View
    {
        return view('admin.exams.index', [
            'exams' => Exam::with(['academicYear', 'examLevel'])
                ->withCount(['sessions', 'registrations', 'examStudents', 'studentScores'])
                ->latest('id')
                ->paginate(15),
            'academicYears' => AcademicYear::orderByDesc('code')->get(),
            'examLevels' => ExamLevel::orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Exam::create($this->payload($request));

        return back()->with('status', 'Đã tạo kỳ thi nội bộ.');
    }

    public function update(Request $request, Exam $exam): RedirectResponse
    {
        $exam->update($this->payload($request, $exam));

        return back()->with('status', 'Đã cập nhật kỳ thi nội bộ.');
    }

    public function open(Exam $exam): RedirectResponse
    {
        $exam->update(['status' => 'open']);

        return back()->with('status', 'Đã mở kỳ thi.');
    }

    public function close(Exam $exam): RedirectResponse
    {
        $exam->update(['status' => 'closed']);

        return back()->with('status', 'Đã đóng kỳ thi.');
    }

    public function lock(Exam $exam): RedirectResponse
    {
        $exam->update(['status' => 'locked']);

        return back()->with('status', 'Đã khóa danh sách.');
    }

    public function publishScores(Request $request, Exam $exam): RedirectResponse
    {
        $exam->update(['publish_scores' => ! $exam->publish_scores]);

        return back()->with('status', $exam->publish_scores ? 'Đã công bố điểm.' : 'Đã tắt công bố điểm.');
    }

    private function payload(Request $request, ?Exam $exam = null): array
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:100', Rule::unique('exams', 'code')->ignore($exam?->id)],
            'name' => ['required', 'string', 'max:255'],
            'school_year' => ['required', 'string', 'max:20'],
            'academic_year_id' => ['nullable', 'exists:academic_years,id'],
            'exam_level_id' => ['nullable', 'exists:exam_levels,id'],
            'level' => ['nullable', 'in:school,ward,province,national'],
            'registration_opens_at' => ['nullable', 'date'],
            'registration_closes_at' => ['nullable', 'date', 'after_or_equal:registration_opens_at'],
            'exam_date' => ['nullable', 'date'],
            'exam_time' => ['nullable', 'date_format:H:i'],
            'target_grades' => ['nullable', 'array'],
            'target_grades.*' => ['integer', 'min:1', 'max:12'],
            'registration_mode' => ['required', 'in:admin_assign_session,student_select_session'],
            'external_platform_name' => ['nullable', 'string', 'max:100'],
            'template_type' => ['nullable', 'in:truong,xa_phuong,tinh,quoc_gia,khac'],
            'organizer_scope' => ['nullable', 'in:school,cluster,district,province,national,custom'],
            'countdown_mode' => ['nullable', 'in:auto,open,close,exam'],
            'status' => ['required', 'in:draft,preparing,student_list_ready,live_ready,running,finished,score_entering,ranked,archived,open,closed,assigning,locked,in_progress,completed'],
            'timezone' => ['nullable', 'string', 'max:60'],
            'source' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        $examLevel = isset($data['exam_level_id']) ? ExamLevel::find($data['exam_level_id']) : null;
        $levelCode = $examLevel?->code ?? ($data['level'] ?? 'school');

        return [
            ...$data,
            'code' => $data['code'] ?? null,
            'level' => $levelCode,
            'template_type' => $data['template_type'] ?? match ($levelCode) {
                'ward' => 'xa_phuong',
                'province' => 'tinh',
                'national' => 'quoc_gia',
                default => 'truong',
            },
            'external_platform_name' => $data['external_platform_name'] ?? 'IOE',
            'organizer_scope' => $data['organizer_scope'] ?? $levelCode,
            'target_grades' => collect($request->input('target_grades', [10, 11, 12]))
                ->map(fn ($grade) => (int) $grade)
                ->values()
                ->all(),
            'timezone' => $data['timezone'] ?? 'Asia/Ho_Chi_Minh',
            'source' => $data['source'] ?? 'admin_configured',
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
