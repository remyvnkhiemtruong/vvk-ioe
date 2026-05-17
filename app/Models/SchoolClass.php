<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolClass extends Model
{
    protected $fillable = [
        'class_code',
        'identity_code',
        'class_name',
        'grade',
        'grade_id',
        'homeroom_teacher',
        'homeroom_teacher_id',
        'homeroom_teacher_resolution_status',
        'study_shift',
        'foreign_language_1',
        'foreign_language_2',
        'track',
        'is_specialized',
        'has_vocational_students',
        'is_combined',
        'combined_into_class',
        'is_boarding',
        'weekly_sessions',
        'school_year',
        'academic_year_id',
        'import_batch_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'grade' => 'integer',
            'is_specialized' => 'boolean',
            'has_vocational_students' => 'boolean',
            'is_combined' => 'boolean',
            'is_boarding' => 'boolean',
            'weekly_sessions' => 'integer',
        ];
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function gradeModel(): BelongsTo
    {
        return $this->belongsTo(Grade::class, 'grade_id');
    }

    public function homeroomTeacher(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'homeroom_teacher_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
