<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SelfTrainingProgress extends Model
{
    protected $table = 'self_training_progress';

    protected $fillable = [
        'academic_year_id',
        'student_id',
        'grade_number',
        'class_name',
        'round_number',
        'total_score',
        'total_duration_seconds',
        'source_key',
        'imported_from_file',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
