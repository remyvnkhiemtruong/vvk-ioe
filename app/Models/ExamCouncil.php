<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamCouncil extends Model
{
    protected $fillable = ['exam_id', 'name', 'type', 'school_name', 'location', 'chairperson', 'secretary', 'notes'];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }
}
