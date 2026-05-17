<?php

namespace App\Services;

use App\Models\ExamCode;
use App\Models\ExamTimeWindow;
use App\Models\Grade;
use Carbon\CarbonInterface;

class ExamCodeService
{
    public const SOURCE_MANUAL_FROM_IOE = 'manual_from_ioe';

    public function resolveForSlot(ExamTimeWindow $slot): ?ExamCode
    {
        $code = ExamCode::where('exam_time_slot_id', $slot->id)
            ->where('is_active', true)
            ->first();

        if ($code) {
            return $code;
        }

        $gradeNumbers = $this->gradeNumbersForSlot($slot);

        return ExamCode::where('exam_session_id', $slot->exam_session_id)
            ->whereNull('exam_time_slot_id')
            ->where('is_active', true)
            ->orderByDesc('updated_at')
            ->get()
            ->first(function (ExamCode $candidate) use ($gradeNumbers): bool {
                if (! is_array($candidate->applied_grade_ids) || $candidate->applied_grade_ids === []) {
                    return true;
                }

                return array_intersect($candidate->applied_grade_ids, $gradeNumbers) !== [];
            });
    }

    public function visibleCodeForSlot(ExamTimeWindow $slot, CarbonInterface $now): ?ExamCode
    {
        if (! $this->isInVisibleWindow($slot, $now)) {
            return null;
        }

        return $this->resolveForSlot($slot);
    }

    public function isInVisibleWindow(ExamTimeWindow $slot, CarbonInterface $now): bool
    {
        if (! $slot->starts_at) {
            return false;
        }

        return $now->greaterThanOrEqualTo($slot->revealAt())
            && $now->lessThan($slot->hideAt());
    }

    public function gradeNumbersForSlot(ExamTimeWindow $slot): array
    {
        $numbers = [];

        foreach (($slot->grade_ids ?? []) as $grade) {
            if (is_numeric($grade)) {
                $numbers[] = (int) $grade;
            }
        }

        if ($slot->grade_id) {
            $numbers[] = (int) (Grade::whereKey($slot->grade_id)->value('grade_number') ?? $slot->grade_id);
        }

        return array_values(array_unique(array_filter($numbers)));
    }
}
