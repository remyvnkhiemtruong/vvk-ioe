<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IoeChecklist extends Model
{
    protected $fillable = ['title', 'level', 'description', 'due_date', 'assigned_to', 'is_completed', 'completed_at'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'is_completed' => 'boolean', 'completed_at' => 'datetime'];
    }
}
