<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamMinute;
use App\Models\ExamRegistration;
use App\Models\ExamResult;
use App\Models\Incident;
use App\Models\SeatAssignment;
use App\Models\VideoEvidence;

class ReportService
{
    public function completeness(Exam $exam): array
    {
        $assignments = SeatAssignment::whereHas('registration', fn ($query) => $query->where('exam_id', $exam->id));

        return [
            'registrations' => ExamRegistration::where('exam_id', $exam->id)->count(),
            'assigned' => (clone $assignments)->count(),
            'missing_results' => ExamRegistration::where('exam_id', $exam->id)->whereDoesntHave('result')->count(),
            'minutes_missing' => max((clone $assignments)->select('exam_session_id', 'exam_room_id')->distinct()->count() - ExamMinute::where('exam_id', $exam->id)->count(), 0),
            'videos_missing' => max((clone $assignments)->select('exam_session_id', 'exam_room_id')->distinct()->count() - VideoEvidence::where('exam_id', $exam->id)->count(), 0),
            'incidents_open' => Incident::where('exam_id', $exam->id)->whereNull('solution')->count(),
            'locked_results' => ExamResult::where('exam_id', $exam->id)->whereNotNull('locked_at')->count(),
        ];
    }
}
