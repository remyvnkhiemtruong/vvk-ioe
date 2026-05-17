<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamResult extends Model
{
    protected $fillable = [
        'exam_registration_id',
        'exam_id',
        'student_id',
        'grade_id',
        'external_account_type',
        'score',
        'max_score',
        'duration_seconds',
        'started_at',
        'submitted_at',
        'result_status',
        'source',
        'entered_by',
        'reviewed_by',
        'reviewed_at',
        'locked_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'max_score' => 'decimal:2',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(ExamRegistration::class, 'exam_registration_id');
    }
}
