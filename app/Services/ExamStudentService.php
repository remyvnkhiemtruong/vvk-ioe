<?php

namespace App\Services;

use App\Models\ExamStudent;
use App\Models\ExamTimeWindow;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ExamStudentService
{
    public function __construct(private readonly ExamScheduleService $scheduleService) {}

    public function assignToSlot(ExamStudent $examStudent, ExamTimeWindow $slot): ExamStudent
    {
        return DB::transaction(function () use ($examStudent, $slot): ExamStudent {
            $previousSlot = $examStudent->assignedTimeSlot;

            $examStudent->update([
                'assigned_time_slot_id' => $slot->id,
                'status' => 'assigned_to_slot',
            ]);

            if ($previousSlot) {
                $this->scheduleService->recalculateSlotStudentCount($previousSlot);
            }

            $this->scheduleService->recalculateSlotStudentCount($slot);

            return $examStudent->refresh();
        });
    }

    public function markRegisteredOnIoe(ExamStudent $examStudent, ?User $actor = null): ExamStudent
    {
        $examStudent->update([
            'registered_on_ioe' => true,
            'registered_on_ioe_at' => now(),
            'status' => 'registered_on_ioe',
            'selected_by' => $examStudent->selected_by ?: $actor?->id,
            'selected_at' => $examStudent->selected_at ?: now(),
        ]);

        return $examStudent->refresh();
    }
}
