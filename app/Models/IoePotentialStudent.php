<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IoePotentialStudent extends Model
{
    protected $fillable = ['student_id', 'full_name', 'class_name', 'ioe_id', 'self_practice_round', 'school_result', 'recommend_next_round', 'note', 'updated_by'];

    protected function casts(): array
    {
        return ['recommend_next_round' => 'boolean'];
    }
}
