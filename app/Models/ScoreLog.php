<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoreLog extends Model
{
    protected $fillable = ['exam_score_id', 'changed_by', 'old_score', 'new_score', 'old_status', 'new_status', 'reason', 'changed_at'];

    protected function casts(): array
    {
        return [
            'old_score' => 'decimal:2',
            'new_score' => 'decimal:2',
            'changed_at' => 'datetime',
        ];
    }

    public function score(): BelongsTo
    {
        return $this->belongsTo(ExamScore::class, 'exam_score_id');
    }
}
