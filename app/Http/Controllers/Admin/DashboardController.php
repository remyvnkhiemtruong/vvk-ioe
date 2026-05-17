<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\AcademicYearStudent;
use App\Models\AwardRecord;
use App\Models\ExamCode;
use App\Models\ExamChecklist;
use App\Models\ExamStudent;
use App\Models\ExamMinute;
use App\Models\ExamRegistration;
use App\Models\ExamScore;
use App\Models\ExamSession;
use App\Models\ExamTimeWindow;
use App\Models\Incident;
use App\Models\LiveScreen;
use App\Models\RoomComputer;
use App\Models\SchoolClass;
use App\Models\SeatAssignment;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\StudentScore;
use App\Models\VideoEvidence;
use App\Services\ExamSessionAvailabilityService;
use App\Services\SystemSettingService;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(ExamSessionAvailabilityService $availability): View
    {
        $exam = Exam::where('school_year', app(SystemSettingService::class)->schoolYear())
            ->whereIn('status', SystemSettingService::ACTIVE_LANDING_STATUSES)
            ->latest('id')
            ->first();
        $examId = $exam?->id;
        $registrations = ExamRegistration::query()->when($examId, fn ($q) => $q->where('exam_id', $examId), fn ($q) => $q->whereRaw('1 = 0'));
        $sessions = $examId ? ExamSession::where('exam_id', $examId)->get() : collect();
        $internalStudents = ExamStudent::query()->when($examId, fn ($q) => $q->where('exam_id', $examId), fn ($q) => $q->whereRaw('1 = 0'));
        $studentScores = StudentScore::query()->when($examId, fn ($q) => $q->where('exam_id', $examId), fn ($q) => $q->whereRaw('1 = 0'));
        $sessionIds = $examId ? ExamSession::where('exam_id', $examId)->pluck('id') : collect();

        $wrongSessions = ExamRegistration::with(['exam', 'student', 'chosenSession'])
            ->when($examId, fn ($q) => $q->where('exam_id', $examId), fn ($q) => $q->whereRaw('1 = 0'))
            ->whereNotNull('exam_session_id')
            ->get()
            ->filter(fn (ExamRegistration $registration) => $registration->chosenSession && (
                ! $availability->isExamTargetForStudent($registration->exam, $registration)
                || ! $availability->isTargetForStudent($registration->chosenSession, $registration)
            ))
            ->count();

        return view('admin.dashboard', [
            'exam' => $exam,
            'stats' => [
                'students' => Student::count(),
                'staff' => StaffProfile::count(),
                'classes' => SchoolClass::count(),
                'registrations' => (clone $registrations)->count(),
                'approved' => (clone $registrations)->where('status', 'approved')->count(),
                'pending' => (clone $registrations)->whereIn('status', ['submitted', 'pending'])->count(),
                'rejected' => (clone $registrations)->where('status', 'rejected')->count(),
                'byod_pending' => (clone $registrations)->where('uses_personal_computer', true)->where('personal_computer_status', 'pending')->count(),
                'byod_approved' => (clone $registrations)->where('uses_personal_computer', true)->where('personal_computer_status', 'approved')->count(),
                'assigned' => SeatAssignment::when($examId, fn ($q) => $q->whereHas('registration', fn ($r) => $r->where('exam_id', $examId)))->count(),
                'unassigned' => max((clone $registrations)->where('status', 'approved')->count() - SeatAssignment::when($examId, fn ($q) => $q->whereHas('registration', fn ($r) => $r->where('exam_id', $examId)))->distinct('exam_registration_id')->count('exam_registration_id'), 0),
                'incidents' => Incident::when($examId, fn ($q) => $q->whereHas('registration', fn ($r) => $r->where('exam_id', $examId)))->count(),
                'scores_entered' => ExamScore::when($examId, fn ($q) => $q->whereHas('registration', fn ($r) => $r->where('exam_id', $examId)))->whereIn('score_status', ['entered', 'verified', 'locked'])->count(),
                'scores_missing' => max((clone $registrations)->count() - ExamScore::when($examId, fn ($q) => $q->whereHas('registration', fn ($r) => $r->where('exam_id', $examId)))->count(), 0),
                'sessions_grade_10' => $sessions->where('target_grade', 10)->count(),
                'sessions_grade_11' => $sessions->where('target_grade', 11)->count(),
                'sessions_grade_12' => $sessions->where('target_grade', 12)->count(),
                'sessions_open' => $sessions->filter(fn (ExamSession $session) => $session->status === 'open' && $availability->remainingSlots($session) > 0)->count(),
                'sessions_full' => $sessions->filter(fn (ExamSession $session) => $availability->remainingSlots($session) <= 0 || $session->status === 'full')->count(),
                'sessions_locked' => $sessions->where('status', 'locked')->count(),
                'missing_session' => (clone $registrations)->whereNull('exam_session_id')->count(),
                'wrong_session' => $wrongSessions,
                'broken_computers' => RoomComputer::whereIn('status', ['broken', 'maintenance'])->count(),
                'missing_checklists' => $examId ? $this->missingByRoomSession($examId, ExamChecklist::class) : 0,
                'missing_minutes' => $examId ? $this->missingByRoomSession($examId, ExamMinute::class) : 0,
                'missing_videos' => $examId ? $this->missingByRoomSession($examId, VideoEvidence::class) : 0,
                'internal_students' => (clone $internalStudents)->count(),
                'internal_eligible' => (clone $internalStudents)->where('eligibility_status', 'eligible')->count(),
                'internal_ineligible' => (clone $internalStudents)->where('eligibility_status', 'ineligible')->count(),
                'registered_on_ioe' => (clone $internalStudents)->where('registered_on_ioe', true)->count(),
                'assigned_to_slot' => (clone $internalStudents)->whereNotNull('assigned_time_slot_id')->count(),
                'time_slots_with_students' => $sessionIds->isEmpty() ? 0 : ExamTimeWindow::whereIn('exam_session_id', $sessionIds)
                    ->where(fn ($q) => $q->where('has_students', true)->orWhere('student_count', '>', 0))
                    ->count(),
                'v2_scores_entered' => (clone $studentScores)->whereNotNull('score')->count(),
                'v2_scores_locked' => (clone $studentScores)->where('status', 'locked')->count(),
                'award_records' => $examId ? AwardRecord::where('exam_id', $examId)->count() : AwardRecord::count(),
                'live_screens' => $examId ? LiveScreen::where('exam_id', $examId)->count() : 0,
                'rollover_2026_2027' => AcademicYearStudent::whereHas('academicYear', fn ($q) => $q->where('code', '2026-2027'))->count(),
            ],
            'gradeCounts' => ExamRegistration::selectRaw('class_name, count(*) as total')
                ->when($examId, fn ($q) => $q->where('exam_id', $examId), fn ($q) => $q->whereRaw('1 = 0'))
                ->groupBy('class_name')
                ->orderBy('class_name')
                ->get(),
            'nearlyFullSessions' => $sessions->filter(fn (ExamSession $session) => $session->status === 'open' && $availability->remainingSlots($session) <= 3)->values(),
            'latestInternalExam' => $exam,
            'topScores' => $examId
                ? StudentScore::where('exam_id', $examId)
                    ->whereNotNull('score')
                    ->with('student')
                    ->orderByDesc('score')
                    ->orderBy('duration_seconds')
                    ->limit(8)
                    ->get()
                : collect(),
            'awardCounts' => AwardRecord::select('award_scope', DB::raw('count(*) as total'))
                ->groupBy('award_scope')
                ->orderBy('award_scope')
                ->get(),
            'rolloverCounts' => AcademicYearStudent::whereHas('academicYear', fn ($q) => $q->where('code', '2026-2027'))
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status'),
            'slotWarnings' => $sessionIds->isEmpty()
                ? collect()
                : ExamTimeWindow::whereIn('exam_session_id', $sessionIds)
                    ->where(fn ($q) => $q->where('has_students', true)->orWhere('student_count', '>', 0))
                    ->with('session')
                    ->orderBy('starts_at')
                    ->get()
                    ->filter(fn (ExamTimeWindow $slot) => ! ExamCode::where('is_active', true)
                        ->where(fn ($q) => $q->where('exam_time_slot_id', $slot->id)
                            ->orWhere(fn ($fallback) => $fallback
                                ->where('exam_session_id', $slot->exam_session_id)
                                ->whereNull('exam_time_slot_id')))
                        ->exists())
                    ->take(8)
                    ->values(),
        ]);
    }

    private function missingByRoomSession(int $examId, string $modelClass): int
    {
        $scopes = SeatAssignment::whereHas('registration', fn ($query) => $query->where('exam_id', $examId))
            ->select('exam_session_id', 'exam_room_id')
            ->distinct()
            ->get();

        $existing = $modelClass::query()
            ->where('exam_id', $examId)
            ->get(['exam_session_id', 'exam_room_id'])
            ->map(fn ($item) => $item->exam_session_id.'|'.$item->exam_room_id)
            ->all();

        return $scopes
            ->filter(fn ($scope) => ! in_array($scope->exam_session_id.'|'.$scope->exam_room_id, $existing, true))
            ->count();
    }
}
