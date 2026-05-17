<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IoeResearchDocument extends Model
{
    protected $fillable = ['title', 'level', 'school_year', 'issued_date', 'source_url', 'file_path', 'note', 'updated_by'];

    protected function casts(): array
    {
        return ['issued_date' => 'date'];
    }
}
