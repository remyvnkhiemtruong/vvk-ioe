<?php

namespace App\Services;

use App\Models\ExamSession;
use App\Models\ExamTimeWindow;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class ExamScheduleService
{
    public function validateSessionWindow(CarbonInterface $startsAt, CarbonInterface $endsAt): void
    {
        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw ValidationException::withMessages([
                'ends_at' => 'Thời điểm kết thúc phải sau thời điểm bắt đầu.',
            ]);
        }
    }

    public function validateSlotInsideSession(ExamTimeWindow $slot): void
    {
        $session = $slot->session;

        if (! $session || ! $session->starts_at || ! $session->ends_at || ! $slot->starts_at || ! $slot->ends_at) {
            return;
        }

        if ($slot->starts_at->lt($session->starts_at) || $slot->ends_at->gt($session->ends_at)) {
            throw ValidationException::withMessages([
                'starts_at' => 'Khung giờ thi phải nằm trong ca thi.',
            ]);
        }
    }

    public function recalculateSlotStudentCount(ExamTimeWindow $slot): ExamTimeWindow
    {
        $count = $slot->assignedStudents()
            ->whereNotIn('status', ['cancelled'])
            ->count();

        if ($count > 0) {
            $slot->forceFill([
                'student_count' => $count,
                'has_students' => true,
            ])->save();
        }

        return $slot->refresh();
    }

    public function recalculateSessionSlots(ExamSession $session): void
    {
        $session->timeWindows()->each(fn (ExamTimeWindow $slot) => $this->recalculateSlotStudentCount($slot));
    }
}
