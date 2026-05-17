<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamFormField extends Model
{
    protected $fillable = ['exam_id', 'field_key', 'label', 'help_text', 'type', 'is_enabled', 'is_required', 'options', 'metadata', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'is_required' => 'boolean',
            'options' => 'array',
            'metadata' => 'array',
        ];
    }
}
