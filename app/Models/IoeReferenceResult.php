<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IoeReferenceResult extends Model
{
    protected $fillable = ['title', 'level', 'school_year', 'data', 'file_path', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }
}
