<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExamStudent extends Model
{
    // Bảng: exam_students
    protected $fillable = [
        'exam_id', 'student_id', 'grade_number', 'school_id', 'class_name',
        'ioe_username', 'ioe_account_id', 'ioe_account_verified',
        'self_training_round', 'status', 'eligibility_status', 'ineligible_reasons',
        'registered_on_ioe', 'registered_on_ioe_at',
        'assigned_time_slot_id', 'selected_by', 'selected_at', 'note',
    ];

    protected function casts(): array
    {
        return [
            'ineligible_reasons' => 'array',
            'ioe_account_verified' => 'boolean',
            'registered_on_ioe' => 'boolean',
            'selected_at' => 'datetime',
            'registered_on_ioe_at' => 'datetime',
        ];
    }

    // ── Statuses ────────────────────────────────────────────────────────────
    public const STATUSES = [
        'draft', 'eligible', 'ineligible', 'selected',
        'registered_on_ioe', 'assigned_to_slot',
        'completed_exam', 'score_entered', 'ranked', 'cancelled',
    ];

    public const ELIGIBILITY_STATUSES = ['eligible', 'ineligible', 'pending'];

    // ── Relations ────────────────────────────────────────────────────────────
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function assignedTimeSlot(): BelongsTo
    {
        return $this->belongsTo(ExamTimeWindow::class, 'assigned_time_slot_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function selectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'selected_by');
    }

    public function studentScore(): HasOne
    {
        return $this->hasOne(StudentScore::class, 'exam_student_id');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────
    public function isEligible(): bool
    {
        return $this->eligibility_status === 'eligible';
    }

    public function isIneligible(): bool
    {
        return $this->eligibility_status === 'ineligible';
    }

    public function displayIneligibleReasons(): string
    {
        if (! is_array($this->ineligible_reasons) || empty($this->ineligible_reasons)) {
            return '—';
        }

        return implode('; ', $this->ineligible_reasons);
    }
}
