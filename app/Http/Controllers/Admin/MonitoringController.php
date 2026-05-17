<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamChecklist;
use App\Models\ExamMinute;
use App\Models\ExamRoom;
use App\Models\ExamSession;
use App\Models\VideoEvidence;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MonitoringController extends Controller
{
    public function index(Request $request): View
    {
        $exam = Exam::where('level', 'school')->latest()->first();

        return view('admin.monitoring.index', [
            'exam' => $exam,
            'sessions' => ExamSession::with('room')->when($exam, fn ($query) => $query->where('exam_id', $exam->id))->orderBy('exam_date')->orderBy('start_time')->get(),
            'rooms' => ExamRoom::orderBy('room_name')->get(),
            'checklists' => ExamChecklist::with(['exam', 'session', 'room'])->latest()->paginate(10, ['*'], 'checklists_page'),
            'minutes' => ExamMinute::with(['exam'])->latest()->paginate(10, ['*'], 'minutes_page'),
            'videos' => VideoEvidence::latest()->paginate(10, ['*'], 'videos_page'),
        ]);
    }

    public function checklist(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'exam_id' => ['required', 'exists:exams,id'],
            'exam_session_id' => ['required', 'exists:exam_sessions,id'],
            'exam_room_id' => ['required', 'exists:exam_rooms,id'],
            'internet_ok' => ['nullable', 'boolean'],
            'computers_ok' => ['nullable', 'boolean'],
            'headsets_ok' => ['nullable', 'boolean'],
            'camera_ok' => ['nullable', 'boolean'],
            'time_zone_ok' => ['nullable', 'boolean'],
            'backup_power_network_ready' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        ExamChecklist::updateOrCreate(
            [
                'exam_room_id' => $data['exam_room_id'],
                'exam_session_id' => $data['exam_session_id'],
                'exam_time_window_id' => null,
            ],
            [
                ...$data,
                'internet_ok' => $request->boolean('internet_ok'),
                'computers_ok' => $request->boolean('computers_ok'),
                'headsets_ok' => $request->boolean('headsets_ok'),
                'camera_ok' => $request->boolean('camera_ok'),
                'time_zone_ok' => $request->boolean('time_zone_ok'),
                'backup_power_network_ready' => $request->boolean('backup_power_network_ready'),
                'checked_by' => $request->user()->id,
                'checked_at' => now(),
            ],
        );

        return back()->with('status', 'Đã lưu checklist giám sát phòng/ca.');
    }

    public function minute(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'exam_id' => ['required', 'exists:exams,id'],
            'exam_session_id' => ['required', 'exists:exam_sessions,id'],
            'exam_room_id' => ['required', 'exists:exam_rooms,id'],
            'status' => ['required', 'in:not_generated,generated,printed,signed,uploaded,approved,rejected'],
            'signed_scan_path' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        ExamMinute::updateOrCreate(
            [
                'exam_room_id' => $data['exam_room_id'],
                'exam_session_id' => $data['exam_session_id'],
                'exam_time_window_id' => null,
            ],
            [
                ...$data,
                'approved_by' => $data['status'] === 'approved' ? $request->user()->id : null,
                'approved_at' => $data['status'] === 'approved' ? now() : null,
            ],
        );

        return back()->with('status', 'Đã lưu trạng thái biên bản thi.');
    }

    public function video(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'exam_id' => ['required', 'exists:exams,id'],
            'exam_session_id' => ['required', 'exists:exam_sessions,id'],
            'exam_room_id' => ['required', 'exists:exam_rooms,id'],
            'video_url' => ['required', 'url', 'max:1000'],
            'storage_provider' => ['required', 'in:google_drive,youtube,other'],
            'visibility_checked' => ['nullable', 'boolean'],
            'quality_status' => ['required', 'in:pending,ok,not_ok'],
            'duration_note' => ['nullable', 'string', 'max:255'],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        VideoEvidence::create([
            ...$data,
            'visibility_checked' => $request->boolean('visibility_checked'),
            'submitted_by' => $request->user()->id,
            'submitted_at' => now(),
        ]);

        return back()->with('status', 'Đã lưu minh chứng video giám sát.');
    }
}
