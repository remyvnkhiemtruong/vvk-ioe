<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ExamTimeWindow – model cho bảng exam_time_windows.
 * Trong nghiệp vụ v2 được alias là "exam_time_slots" (khung giờ thi).
 * Không tạo bảng mới để giữ tương thích ngược với code cũ.
 */
class ExamTimeWindow extends Model
{
    protected $table = 'exam_time_windows';

    protected $fillable = [
        'exam_session_id', 'code', 'grade_id', 'name', 'grade_ids',
        'grade_group', 'starts_at', 'ends_at', 'duration_minutes', 'max_duration_minutes',
        'code_reveal_before_minutes', 'code_hide_after_start_minutes',
        'has_students', 'student_count', 'status', 'source', 'mapping_status', 'note',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'grade_ids' => 'array',
            'has_students' => 'boolean',
        ];
    }

    // ── Relations ────────────────────────────────────────────────────────────
    public function session(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class, 'exam_session_id');
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function examCodes(): HasMany
    {
        return $this->hasMany(ExamCode::class, 'exam_time_slot_id');
    }

    public function assignedStudents(): HasMany
    {
        return $this->hasMany(ExamStudent::class, 'assigned_time_slot_id');
    }

    // ── Live screen helpers ──────────────────────────────────────────────────

    /** Thời điểm bắt đầu hiện mã (trước giờ thi X phút) */
    public function revealAt(): \Carbon\Carbon
    {
        return $this->starts_at->copy()->subMinutes($this->code_reveal_before_minutes ?? 5);
    }

    /** Thời điểm ẩn mã (sau giờ thi bắt đầu X phút) */
    public function hideAt(): \Carbon\Carbon
    {
        return $this->starts_at->copy()->addMinutes($this->code_hide_after_start_minutes ?? 5);
    }

    /** Lấy mã ca thi active cho slot này */
    public function activeCode(): ?ExamCode
    {
        return $this->examCodes()->where('is_active', true)->first();
    }

    /** Slot có học sinh thi không (dùng cho /live) */
    public function hasActiveStudents(): bool
    {
        return $this->has_students || $this->student_count > 0;
    }

    /** Slot đã bị hủy không */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /** Nhãn khối hiển thị */
    public function gradeLabel(): string
    {
        if (is_array($this->grade_ids) && ! empty($this->grade_ids)) {
            return 'Khối '.implode(', ', $this->grade_ids);
        }
        if ($this->grade_group) {
            return $this->grade_group;
        }

        return $this->grade ? 'Khối '.$this->grade->grade_number : 'Tất cả khối';
    }
}
