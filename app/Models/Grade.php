<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grade extends Model
{
    protected $fillable = ['grade_number', 'numeric_level', 'education_stage', 'name', 'status'];

    protected function casts(): array
    {
        return ['grade_number' => 'integer'];
    }

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }
}
