<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamScore extends Model
{
    protected $fillable = [
        'exam_registration_id',
        'seat_assignment_id',
        'official_score',
        'completion_time_seconds',
        'correct_answers',
        'exam_status',
        'score_status',
        'entered_by',
        'entered_at',
        'verified_by',
        'verified_at',
        'locked_by',
        'locked_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'official_score' => 'decimal:2',
            'entered_at' => 'datetime',
            'verified_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(ExamRegistration::class, 'exam_registration_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ScoreLog::class);
    }
}
