<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamCode;
use App\Models\ExamSession;
use App\Models\ExamTimeWindow;
use App\Models\LiveScreen;
use App\Services\LiveScreenService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LiveScreenController extends Controller
{
    public function __construct(private readonly LiveScreenService $liveService) {}

    public function index(Exam $exam): View
    {
        $screens = LiveScreen::where('exam_id', $exam->id)
            ->with(['session', 'createdBy'])
            ->orderByDesc('created_at')
            ->get();

        $sessions = ExamSession::where('exam_id', $exam->id)->get();

        // Preview trạng thái hiện tại của từng screen
        $previews = $screens->mapWithKeys(function (LiveScreen $screen) {
            $state = $this->liveService->getCurrentLiveState($screen, Carbon::now('Asia/Ho_Chi_Minh'));

            return [$screen->id => $state];
        });

        // Cảnh báo: slots có học sinh nhưng thiếu mã
        $sessionIds = ExamSession::where('exam_id', $exam->id)->pluck('id');
        $slotsWithoutCode = ExamTimeWindow::whereIn('exam_session_id', $sessionIds)
            ->where('has_students', true)
            ->where('student_count', '>', 0)
            ->where('status', '!=', 'cancelled')
            ->get()
            ->filter(fn ($slot) => ! ExamCode::where(function ($q) use ($slot) {
                $q->where('exam_time_slot_id', $slot->id)
                    ->orWhere('exam_session_id', $slot->exam_session_id);
            })->where('is_active', true)->exists());

        return view('admin.live-screens.index', compact('exam', 'screens', 'sessions', 'previews', 'slotsWithoutCode'));
    }

    public function store(Request $request, Exam $exam)
    {
        $data = $request->validate([
            'exam_session_id' => 'nullable|exists:exam_sessions,id',
            'display_title'   => 'nullable|string|max:255',
        ]);

        $screen = LiveScreen::create(array_merge($data, [
            'exam_id'    => $exam->id,
            'is_enabled' => true,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]));

        return back()->with('success', 'Đã tạo link live: '.$screen->liveUrl());
    }

    public function destroy(Exam $exam, LiveScreen $liveScreen)
    {
        $liveScreen->delete();

        return back()->with('success', 'Đã xóa link live.');
    }

    public function toggle(Exam $exam, LiveScreen $liveScreen)
    {
        $liveScreen->update(['is_enabled' => ! $liveScreen->is_enabled]);

        return back()->with('success', $liveScreen->is_enabled ? 'Đã bật màn hình live.' : 'Đã tắt màn hình live.');
    }

    public function override(Request $request, Exam $exam, LiveScreen $liveScreen)
    {
        $action = $request->validate(['action' => 'required|in:hide,show,end,reset'])['action'];

        match ($action) {
            'hide'  => $liveScreen->update(['admin_override_hide' => true, 'admin_override_show' => false]),
            'show'  => $liveScreen->update(['admin_override_show' => true, 'admin_override_hide' => false]),
            'end'   => $liveScreen->update(['force_ended_at' => now()]),
            'reset' => $liveScreen->update(['admin_override_hide' => false, 'admin_override_show' => false, 'force_ended_at' => null]),
        };

        return back()->with('success', 'Đã cập nhật trạng thái live.');
    }
}
