<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IoeResearchCalendarEvent extends Model
{
    protected $fillable = ['title', 'level', 'starts_at', 'ends_at', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }
}
