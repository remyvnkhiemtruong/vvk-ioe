<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AwardRuleItem extends Model
{
    protected $fillable = [
        'award_rule_id', 'award_name', 'award_code',
        'ratio_percent', 'max_quantity', 'sort_order',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AwardRule::class, 'award_rule_id');
    }
}
