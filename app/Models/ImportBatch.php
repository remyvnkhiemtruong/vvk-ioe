<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportBatch extends Model
{
    protected $fillable = ['type', 'file_name', 'status', 'total_rows', 'valid_rows', 'invalid_rows', 'mapping', 'preview_rows', 'errors', 'report', 'created_by'];

    protected function casts(): array
    {
        return [
            'mapping' => 'array',
            'preview_rows' => 'array',
            'errors' => 'array',
            'report' => 'array',
        ];
    }
}
