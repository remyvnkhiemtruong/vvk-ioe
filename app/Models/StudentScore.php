<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StudentScore extends Model
{
    protected $fillable = [
        'exam_id', 'exam_student_id', 'student_id', 'grade_number', 'school_id', 'class_name',
        'score', 'max_score', 'duration_seconds', 'raw_duration_text', 'raw_exam_taken_at',
        'exam_session_id', 'exam_time_slot_id', 'source_key', 'mapping_status',
        'mapping_note', 'imported_from_file',
        'entered_by', 'entered_at', 'locked_by', 'locked_at',
        'status', 'exclude_from_awards', 'exclude_reason', 'note', 'needs_rerank',
    ];

    protected function casts(): array
    {
        return [
            'entered_at' => 'datetime',
            'locked_at' => 'datetime',
            'raw_exam_taken_at' => 'datetime',
            'exclude_from_awards' => 'boolean',
            'needs_rerank' => 'boolean',
        ];
    }

    // Statuses
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_LOCKED = 'locked';
    public const STATUS_RANKED = 'ranked';

    // ── Relations ────────────────────────────────────────────────────────────
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function examStudent(): BelongsTo
    {
        return $this->belongsTo(ExamStudent::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class, 'exam_session_id');
    }

    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(ExamTimeWindow::class, 'exam_time_slot_id');
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function ranking(): HasOne
    {
        return $this->hasOne(Ranking::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────
    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public function isEligibleForAward(): bool
    {
        return ! $this->exclude_from_awards
            && in_array($this->status, [self::STATUS_SUBMITTED, self::STATUS_LOCKED, self::STATUS_RANKED], true)
            && $this->score !== null;
    }

    public function scorePercent(): float
    {
        if (! $this->max_score || $this->max_score == 0) {
            return 0;
        }

        return round(($this->score / $this->max_score) * 100, 2);
    }
}
