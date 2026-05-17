<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ranking extends Model
{
    protected $fillable = [
        'exam_id', 'award_rule_id', 'student_score_id', 'student_id',
        'grade_number', 'scope', 'scope_id', 'rank',
        'score', 'duration_seconds', 'award_name', 'award_code',
        'is_highest_award', 'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'is_highest_award' => 'boolean',
            'generated_at' => 'datetime',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function awardRule(): BelongsTo
    {
        return $this->belongsTo(AwardRule::class);
    }

    public function studentScore(): BelongsTo
    {
        return $this->belongsTo(StudentScore::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** Nhãn giải theo tiếng Việt */
    public function awardLabel(): string
    {
        return match ($this->award_code) {
            'first', 'gold'       => 'Giải Nhất / Huy chương Vàng',
            'second', 'silver'    => 'Giải Nhì / Huy chương Bạc',
            'third', 'bronze'     => 'Giải Ba / Huy chương Đồng',
            'encouragement'       => 'Giải Khuyến khích',
            default               => $this->award_name ?? 'Chưa xếp giải',
        };
    }
}
