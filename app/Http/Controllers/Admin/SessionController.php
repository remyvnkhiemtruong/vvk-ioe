<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamRoom;
use App\Models\ExamSession;
use App\Services\ExamSessionAvailabilityService;
use App\Support\SchoolClassOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function index(ExamSessionAvailabilityService $availability): View
    {
        $sessions = ExamSession::with(['exam', 'room'])
            ->withCount(['registrations as valid_registrations_count' => fn ($query) => $query->whereIn('status', ExamSessionAvailabilityService::VALID_REGISTRATION_STATUSES)])
            ->latest()
            ->paginate(15);

        $sessions->getCollection()->each(function (ExamSession $session) use ($availability): void {
            $session->setAttribute('remaining_slots', $availability->remainingSlots($session));
        });

        return view('admin.sessions.index', [
            'sessions' => $sessions,
            'exams' => Exam::where('level', 'school')->latest()->get(),
            'rooms' => ExamRoom::orderBy('room_name')->get(),
            'classes' => SchoolClassOptions::names(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        ExamSession::create($this->payload($request));

        return back()->with('status', 'Đã tạo ca thi.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'exam_id' => ['required', 'exists:exams,id'],
            'exam_room_id' => ['required', 'exists:exam_rooms,id'],
            'exam_date' => ['required', 'date'],
            'first_start_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'min:10', 'max:180'],
            'break_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'session_count' => ['required', 'integer', 'min:1', 'max:48'],
            'max_candidates' => ['required', 'integer', 'min:1'],
            'target_grade' => ['nullable', 'integer', 'in:10,11,12'],
            'target_classes_text' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'in:draft,open,closed,full,locked,completed'],
        ]);

        $start = Carbon::createFromFormat('H:i', $data['first_start_time']);
        $classes = $this->targetClasses($data['target_classes_text'] ?? null);

        for ($i = 1; $i <= $data['session_count']; $i++) {
            $end = $start->copy()->addMinutes((int) $data['duration_minutes']);
            ExamSession::create([
                'exam_id' => $data['exam_id'],
                'exam_room_id' => $data['exam_room_id'],
                'name' => 'Ca '.$i,
                'exam_date' => $data['exam_date'],
                'start_time' => $start->format('H:i'),
                'end_time' => $end->format('H:i'),
                'target_grade' => $data['target_grade'] ?? null,
                'target_classes' => $classes,
                'max_candidates' => $data['max_candidates'],
                'status' => $data['status'],
            ]);
            $start = $end->addMinutes((int) $data['break_minutes']);
        }

        return back()->with('status', 'Đã tạo nhanh '.$data['session_count'].' ca thi.');
    }

    public function duplicate(ExamSession $session): RedirectResponse
    {
        $copy = $session->replicate(['status']);
        $copy->name = $session->name.' - bản sao';
        $copy->status = 'draft';
        $copy->save();

        return back()->with('status', 'Đã nhân bản ca thi.');
    }

    public function destroy(ExamSession $session): RedirectResponse
    {
        if ($session->registrations()->exists() || $session->assignments()->exists()) {
            return back()->withErrors(['session' => 'Không thể xóa ca thi đã có đăng ký hoặc phân phòng. Hãy khóa ca nếu không còn sử dụng.']);
        }

        $session->delete();

        return back()->with('status', 'Đã xóa ca thi.');
    }

    private function payload(Request $request): array
    {
        $data = $request->validate([
            'exam_id' => ['required', 'exists:exams,id'],
            'exam_room_id' => ['nullable', 'exists:exam_rooms,id'],
            'name' => ['required', 'string', 'max:255'],
            'exam_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'target_grade' => ['nullable', 'integer', 'in:10,11,12'],
            'target_classes_text' => ['nullable', 'string', 'max:1000'],
            'max_candidates' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:draft,open,closed,full,locked,completed'],
            'note' => ['nullable', 'string'],
        ]);

        $data['target_classes'] = $this->targetClasses($data['target_classes_text'] ?? null);
        unset($data['target_classes_text']);

        return $data;
    }

    private function targetClasses(?string $text): ?array
    {
        $classes = collect(explode(',', (string) $text))
            ->map(fn ($class) => trim($class))
            ->filter()
            ->values()
            ->all();

        return $classes === [] ? null : $classes;
    }
}
