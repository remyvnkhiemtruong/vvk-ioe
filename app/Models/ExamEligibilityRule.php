<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamEligibilityRule extends Model
{
    protected $fillable = [
        'exam_id', 'exam_level_id', 'grade_number', 'min_self_training_round',
        'require_verified_account', 'require_previous_exam_result',
        'previous_exam_level_id', 'min_previous_score', 'min_previous_score_percent',
        'max_score', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'require_verified_account' => 'boolean',
            'require_previous_exam_result' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function examLevel(): BelongsTo
    {
        return $this->belongsTo(ExamLevel::class);
    }

    public function previousExamLevel(): BelongsTo
    {
        return $this->belongsTo(ExamLevel::class, 'previous_exam_level_id');
    }
}
