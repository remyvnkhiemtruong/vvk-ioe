<?php

namespace App\Services;

use App\Models\ExamSession;
use App\Models\ExamTimeWindow;
use App\Models\LiveScreen;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class LiveScreenService
{
    public const STATUS_NO_SLOTS = 'no_slots';
    public const STATUS_ALL_FINISHED = 'all_finished';
    public const STATUS_WAITING_NEXT = 'waiting_next_slot';
    public const STATUS_CODE_VISIBLE_BEFORE = 'code_visible_before_start';
    public const STATUS_CODE_VISIBLE_AFTER = 'code_visible_after_start';
    public const STATUS_EXAM_RUNNING_CODE_HIDDEN = 'exam_running_code_hidden';
    public const STATUS_MISSING_CODE = 'missing_code';
    public const STATUS_INVALID_SCHEDULE = 'invalid_schedule';
    public const STATUS_DISABLED = 'disabled';
    public const STATUS_FORCE_ENDED = 'force_ended';

    public function __construct(private readonly ExamCodeService $codeService) {}

    public function getCurrentLiveState(LiveScreen $screen, Carbon $now): array
    {
        if (! $screen->is_enabled) {
            return $this->buildResponse(self::STATUS_DISABLED, $now, [
                'show_code' => false,
                'message' => 'Màn hình live đã bị tắt bởi quản trị viên.',
            ]);
        }

        if ($screen->isForceEnded()) {
            return $this->buildResponse(self::STATUS_FORCE_ENDED, $now, [
                'show_code' => false,
                'message' => 'Tất cả ca thi đã kết thúc (kết thúc thủ công).',
            ]);
        }

        $exam = $screen->exam()->with(['examLevel'])->first();
        $slots = $this->slotsForScreen($screen);
        $examMeta = $exam ? ['id' => $exam->id, 'name' => $exam->name, 'level' => $exam?->examLevel?->name] : null;

        if ($slots->isEmpty()) {
            return $this->buildResponse(self::STATUS_NO_SLOTS, $now, [
                'show_code' => false,
                'message' => 'Không có ca thi nào được cấu hình có học sinh dự thi.',
                'exam' => $examMeta,
            ]);
        }

        if ($screen->admin_override_hide) {
            return $this->buildResponse(self::STATUS_WAITING_NEXT, $now, [
                'show_code' => false,
                'message' => 'Mã ca thi đang được ẩn tạm thời bởi quản trị viên.',
                'exam' => $examMeta,
            ]);
        }

        return $this->resolveSlotState($examMeta, $slots, $now);
    }

    private function slotsForScreen(LiveScreen $screen): Collection
    {
        $query = ExamTimeWindow::query()
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'cancelled');
            })
            ->where(function ($q) {
                $q->where('has_students', true)
                    ->orWhere('student_count', '>', 0)
                    ->orWhereHas('assignedStudents', fn ($students) => $students->whereNotIn('status', ['cancelled']));
            })
            ->orderBy('starts_at');

        if ($screen->exam_session_id) {
            $query->where('exam_session_id', $screen->exam_session_id);
        } else {
            $sessionIds = ExamSession::where('exam_id', $screen->exam_id)
                ->where(function ($q) {
                    $q->whereNull('status')->orWhere('status', '!=', 'cancelled');
                })
                ->pluck('id');
            $query->whereIn('exam_session_id', $sessionIds);
        }

        return $query->get();
    }

    private function resolveSlotState(?array $examMeta, Collection $slots, Carbon $now): array
    {
        foreach ($slots as $slot) {
            /** @var ExamTimeWindow $slot */
            if (! $slot->starts_at || ! $slot->ends_at || $slot->ends_at->lessThanOrEqualTo($slot->starts_at)) {
                return $this->buildResponse(self::STATUS_INVALID_SCHEDULE, $now, [
                    'show_code' => false,
                    'exam' => $examMeta,
                    'slot' => $this->slotMeta($slot),
                    'message' => 'Cấu hình thời gian của khung giờ thi không hợp lệ.',
                ]);
            }

            $revealAt = $slot->revealAt();
            $hideAt = $slot->hideAt();

            if ($revealAt->greaterThanOrEqualTo($hideAt)) {
                return $this->buildResponse(self::STATUS_INVALID_SCHEDULE, $now, [
                    'show_code' => false,
                    'exam' => $examMeta,
                    'slot' => $this->slotMeta($slot),
                    'message' => 'Cấu hình thời gian hiển thị/ẩn mã không hợp lệ cho ca thi "'.$this->slotLabel($slot).'".',
                ]);
            }

            if ($now->lt($revealAt)) {
                return $this->buildResponse(self::STATUS_WAITING_NEXT, $now, [
                    'show_code' => false,
                    'exam' => $examMeta,
                    'next_slot' => $this->slotMeta($slot),
                    'countdown_target' => $slot->starts_at->toIso8601String(),
                    'countdown_seconds' => $now->diffInSeconds($slot->starts_at),
                    'reveal_at' => $revealAt->toIso8601String(),
                    'message' => 'Đang đếm ngược đến ca thi: '.$this->slotLabel($slot),
                ]);
            }

            if ($now->gte($revealAt) && $now->lt($slot->starts_at)) {
                return $this->codeVisibleState(
                    self::STATUS_CODE_VISIBLE_BEFORE,
                    $examMeta,
                    $slot,
                    $now,
                    $slot->starts_at,
                    'Mã ca thi đã được công bố. Ca thi sắp bắt đầu.'
                );
            }

            if ($now->gte($slot->starts_at) && $now->lt($hideAt)) {
                return $this->codeVisibleState(
                    self::STATUS_CODE_VISIBLE_AFTER,
                    $examMeta,
                    $slot,
                    $now,
                    $hideAt,
                    'Ca thi đã bắt đầu. Mã ca thi sẽ tự ẩn sau ít phút.'
                );
            }

            if ($now->gte($hideAt) && $now->lt($slot->ends_at)) {
                return $this->buildResponse(self::STATUS_EXAM_RUNNING_CODE_HIDDEN, $now, [
                    'show_code' => false,
                    'exam' => $examMeta,
                    'current_slot' => $this->slotMeta($slot),
                    'countdown_target' => $slot->ends_at->toIso8601String(),
                    'countdown_seconds' => $now->diffInSeconds($slot->ends_at),
                    'message' => 'Mã ca thi đã ẩn. Đang đếm ngược đến hết ca thi hiện tại.',
                ]);
            }
        }

        return $this->buildResponse(self::STATUS_ALL_FINISHED, $now, [
            'show_code' => false,
            'exam' => $examMeta,
            'message' => 'Tất cả ca thi đã kết thúc.',
        ]);
    }

    private function codeVisibleState(string $status, ?array $examMeta, ExamTimeWindow $slot, Carbon $now, Carbon $target, string $message): array
    {
        $code = $this->codeService->resolveForSlot($slot);

        if ($code === null) {
            return $this->buildResponse(self::STATUS_MISSING_CODE, $now, [
                'show_code' => false,
                'exam' => $examMeta,
                'slot' => $this->slotMeta($slot),
                'message' => 'Đã đến giờ hiển thị mã nhưng ca thi "'.$this->slotLabel($slot).'" chưa được nhập mã.',
            ]);
        }

        return $this->buildResponse($status, $now, [
            'show_code' => true,
            'code' => $code->code,
            'exam' => $examMeta,
            'current_slot' => $this->slotMeta($slot),
            'countdown_target' => $target->toIso8601String(),
            'countdown_seconds' => $now->diffInSeconds($target),
            'message' => $message,
        ]);
    }

    private function slotLabel(ExamTimeWindow $slot): string
    {
        $label = $slot->name ?: $slot->gradeLabel();
        if ($slot->starts_at) {
            $label .= ' ('.$slot->starts_at->format('H:i').')';
        }

        return $label;
    }

    private function slotMeta(ExamTimeWindow $slot): array
    {
        return [
            'id' => $slot->id,
            'name' => $slot->name,
            'grade_label' => $slot->gradeLabel(),
            'starts_at' => $slot->starts_at?->toIso8601String(),
            'ends_at' => $slot->ends_at?->toIso8601String(),
            'student_count' => $slot->student_count,
            'reveal_at' => $slot->starts_at ? $slot->revealAt()->toIso8601String() : null,
            'hide_at' => $slot->starts_at ? $slot->hideAt()->toIso8601String() : null,
        ];
    }

    private function buildResponse(string $status, Carbon $now, array $extra = []): array
    {
        return array_merge([
            'status' => $status,
            'server_time' => $now->toIso8601String(),
            'show_code' => false,
        ], $extra);
    }
}
