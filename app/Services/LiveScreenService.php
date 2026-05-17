<?php

namespace App\Services;

use App\Models\ExamCode;
use App\Models\ExamSession;
use App\Models\ExamTimeWindow;
use App\Models\LiveScreen;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

/**
 * LiveScreenService – Tính trạng thái trang /live theo server time.
 *
 * Rules bảo mật mã ca thi:
 * - API KHÔNG trả code khi show_code = false.
 * - Chỉ trả code khi: reveal_at <= now < hide_at.
 * - Mã được lấy từ exam_codes, không tự sinh.
 */
class LiveScreenService
{
    // ── Statuses ─────────────────────────────────────────────────────────────
    public const STATUS_NO_SLOTS = 'no_slots';
    public const STATUS_ALL_FINISHED = 'all_finished';
    public const STATUS_WAITING_NEXT = 'waiting_next_slot';
    public const STATUS_CODE_VISIBLE_BEFORE = 'code_visible_before_start';
    public const STATUS_CODE_VISIBLE_AFTER = 'code_visible_after_start';
    public const STATUS_MISSING_CODE = 'missing_code';
    public const STATUS_INVALID_SCHEDULE = 'invalid_schedule';
    public const STATUS_DISABLED = 'disabled';
    public const STATUS_FORCE_ENDED = 'force_ended';

    public function getCurrentLiveState(LiveScreen $screen, Carbon $now): array
    {
        // ── Guard: màn hình bị tắt ─────────────────────────────────────────
        if (! $screen->is_enabled) {
            return $this->buildResponse(self::STATUS_DISABLED, $now, [
                'show_code' => false,
                'message'   => 'Màn hình live đã bị tắt bởi quản trị viên.',
            ]);
        }

        // ── Guard: force ended ─────────────────────────────────────────────
        if ($screen->isForceEnded()) {
            return $this->buildResponse(self::STATUS_FORCE_ENDED, $now, [
                'show_code' => false,
                'message'   => 'Tất cả ca thi đã kết thúc (kết thúc thủ công).',
            ]);
        }

        $exam = $screen->exam()->with(['examLevel'])->first();

        // ── Lấy danh sách slots có học sinh, chưa hủy, sắp xếp tăng dần ──
        $query = ExamTimeWindow::query()
            ->where('has_students', true)
            ->where('status', '!=', 'cancelled')
            ->where('student_count', '>', 0)
            ->orderBy('starts_at', 'asc');

        if ($screen->exam_session_id) {
            // Scope theo ca thi cụ thể
            $query->where('exam_session_id', $screen->exam_session_id);
        } else {
            // Scope theo toàn kỳ thi
            $sessionIds = ExamSession::where('exam_id', $screen->exam_id)
                ->where('status', '!=', 'cancelled')
                ->pluck('id');
            $query->whereIn('exam_session_id', $sessionIds);
        }

        /** @var Collection<ExamTimeWindow> $slots */
        $slots = $query->get();

        // ── Không có slot nào ─────────────────────────────────────────────
        if ($slots->isEmpty()) {
            return $this->buildResponse(self::STATUS_NO_SLOTS, $now, [
                'show_code' => false,
                'message'   => 'Không có ca thi nào được cấu hình có học sinh dự thi.',
                'exam'      => $exam ? ['name' => $exam->name, 'level' => $exam?->examLevel?->name] : null,
            ]);
        }

        // ── Admin override: tạm ẩn mã ─────────────────────────────────────
        if ($screen->admin_override_hide) {
            return $this->buildResponse(self::STATUS_WAITING_NEXT, $now, [
                'show_code' => false,
                'message'   => 'Mã ca thi đang được ẩn tạm thời bởi quản trị viên.',
                'exam'      => $exam ? ['name' => $exam->name] : null,
            ]);
        }

        // ── Duyệt qua từng slot theo thứ tự thời gian ────────────────────
        return $this->resolveSlotState($screen, $exam, $slots, $now);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Core resolver
    // ─────────────────────────────────────────────────────────────────────────

    private function resolveSlotState(LiveScreen $screen, $exam, Collection $slots, Carbon $now): array
    {
        $examMeta = $exam ? ['id' => $exam->id, 'name' => $exam->name, 'level' => $exam?->examLevel?->name] : null;

        foreach ($slots as $index => $slot) {
            /** @var ExamTimeWindow $slot */
            if (! $slot->starts_at || ! $slot->ends_at) {
                continue; // Bỏ qua slot thiếu thông tin thời gian
            }

            $revealAt = $slot->revealAt();
            $hideAt   = $slot->hideAt();

            // Validate thời gian hợp lệ
            if ($revealAt->greaterThanOrEqualTo($hideAt)) {
                return $this->buildResponse(self::STATUS_INVALID_SCHEDULE, $now, [
                    'show_code' => false,
                    'message'   => 'Cấu hình thời gian hiển thị/ẩn mã không hợp lệ cho ca thi "'.$this->slotLabel($slot).'".',
                ]);
            }

            // ── Trước reveal_at của slot này ──────────────────────────────
            if ($now->lt($revealAt)) {
                $nextSlot = $slot; // Đây chính là ca tiếp theo
                $prevSlot = $index > 0 ? $slots[$index - 1] : null;

                return $this->buildResponse(self::STATUS_WAITING_NEXT, $now, [
                    'show_code'        => false,
                    'exam'             => $examMeta,
                    'next_slot'        => $this->slotMeta($nextSlot),
                    'countdown_target' => $nextSlot->starts_at->toIso8601String(),
                    'countdown_seconds' => $now->diffInSeconds($nextSlot->starts_at),
                    'reveal_at'        => $revealAt->toIso8601String(),
                    'message'          => 'Đang đếm ngược đến ca thi: '.$this->slotLabel($nextSlot),
                ]);
            }

            // ── Từ reveal_at đến trước starts_at: HIỆN MÃ ────────────────
            if ($now->gte($revealAt) && $now->lt($slot->starts_at)) {
                $code = $this->resolveCode($slot);

                if ($code === null) {
                    return $this->buildResponse(self::STATUS_MISSING_CODE, $now, [
                        'show_code'  => false,
                        'exam'       => $examMeta,
                        'slot'       => $this->slotMeta($slot),
                        'message'    => 'ĐÃ ĐẾN GIỜ HIỂN THỊ MÃ nhưng ca thi "'.$this->slotLabel($slot).'" chưa được nhập mã. Vui lòng liên hệ quản trị viên.',
                    ]);
                }

                // ── Admin override: hiện mã sớm hơn ───────────────────────
                return $this->buildResponse(self::STATUS_CODE_VISIBLE_BEFORE, $now, [
                    'show_code'        => true,
                    'code'             => $code->code,  // CHỈ trả code khi show_code = true
                    'exam'             => $examMeta,
                    'current_slot'     => $this->slotMeta($slot),
                    'countdown_target' => $slot->starts_at->toIso8601String(),
                    'countdown_seconds' => $now->diffInSeconds($slot->starts_at),
                    'message'          => 'Mã ca thi đã được công bố – Ca thi sắp bắt đầu.',
                ]);
            }

            // ── Từ starts_at đến hide_at: VẪN HIỆN MÃ ───────────────────
            if ($now->gte($slot->starts_at) && $now->lt($hideAt)) {
                $code = $this->resolveCode($slot);

                if ($code === null) {
                    return $this->buildResponse(self::STATUS_MISSING_CODE, $now, [
                        'show_code' => false,
                        'exam'      => $examMeta,
                        'slot'      => $this->slotMeta($slot),
                        'message'   => 'Ca thi đã bắt đầu nhưng chưa có mã. Vui lòng liên hệ quản trị viên.',
                    ]);
                }

                return $this->buildResponse(self::STATUS_CODE_VISIBLE_AFTER, $now, [
                    'show_code'        => true,
                    'code'             => $code->code,
                    'exam'             => $examMeta,
                    'current_slot'     => $this->slotMeta($slot),
                    'countdown_target' => $hideAt->toIso8601String(),
                    'countdown_seconds' => $now->diffInSeconds($hideAt),
                    'message'          => 'Ca thi đã bắt đầu – Mã ca thi sẽ tự ẩn sau ít phút.',
                ]);
            }

            // ── Sau hide_at: Ca này đã qua, tiếp tục vòng lặp ────────────
            // (loop sẽ xét slot tiếp theo tự động)
        }

        // ── Đã qua tất cả slot ───────────────────────────────────────────
        return $this->buildResponse(self::STATUS_ALL_FINISHED, $now, [
            'show_code' => false,
            'exam'      => $examMeta,
            'message'   => 'Tất cả ca thi đã kết thúc. Cảm ơn quý vị đã tham dự!',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /** Lấy mã ca thi active cho slot (chỉ trả về object, không lộ code ra ngoài) */
    private function resolveCode(ExamTimeWindow $slot): ?ExamCode
    {
        // Ưu tiên: mã gắn trực tiếp với time_slot
        $code = ExamCode::where('exam_time_slot_id', $slot->id)
            ->where('is_active', true)
            ->first();

        if ($code) {
            return $code;
        }

        // Fallback: mã gắn với session và applied_grade_ids chứa grade của slot
        $gradeNumbers = array_merge(
            $slot->grade_ids ?? [],
            $slot->grade_id ? [$slot->grade_id] : []
        );

        return ExamCode::where('exam_session_id', $slot->exam_session_id)
            ->whereNull('exam_time_slot_id')
            ->where('is_active', true)
            ->get()
            ->first(function (ExamCode $c) use ($gradeNumbers) {
                if (! $c->applied_grade_ids) {
                    return true; // Áp dụng cho tất cả khối
                }

                return ! empty(array_intersect($c->applied_grade_ids, $gradeNumbers));
            });
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
            'id'            => $slot->id,
            'name'          => $slot->name,
            'grade_label'   => $slot->gradeLabel(),
            'starts_at'     => $slot->starts_at?->toIso8601String(),
            'ends_at'       => $slot->ends_at?->toIso8601String(),
            'student_count' => $slot->student_count,
            'reveal_at'     => $slot->revealAt()->toIso8601String(),
            'hide_at'       => $slot->hideAt()->toIso8601String(),
        ];
    }

    private function buildResponse(string $status, Carbon $now, array $extra = []): array
    {
        return array_merge([
            'status'       => $status,
            'server_time'  => $now->toIso8601String(),
            'show_code'    => false,
            // 'code' KHÔNG có ở đây – chỉ thêm trong $extra khi show_code = true
        ], $extra);
    }
}
