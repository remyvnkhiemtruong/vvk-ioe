<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AwardRule extends Model
{
    protected $fillable = [
        'exam_id', 'name', 'scope', 'grade_number',
        'min_score', 'min_score_percent', 'top_percent',
        'max_awards', 'priority_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(AwardRuleItem::class)->orderBy('sort_order');
    }

    public function rankings(): HasMany
    {
        return $this->hasMany(Ranking::class);
    }
}
