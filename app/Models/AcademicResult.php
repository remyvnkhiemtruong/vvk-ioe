<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicResult extends Model
{
    protected $fillable = [
        'student_id',
        'student_code',
        'ministry_identifier',
        'academic_year_id',
        'school_year',
        'grade',
        'class_name',
        'full_name',
        'status',
        'final_score',
        'semester',
        'stage',
        'academic_performance',
        'conduct',
        'title',
        'learning_result',
        'training_result',
        'external_summary_id',
        'import_batch_id',
    ];

    protected function casts(): array
    {
        return [
            'grade' => 'integer',
            'final_score' => 'decimal:2',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
