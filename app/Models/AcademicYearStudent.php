<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicYearStudent extends Model
{
    protected $fillable = [
        'academic_year_id',
        'student_id',
        'previous_academic_year_id',
        'previous_status',
        'carry_over_reason',
        'current_grade_id',
        'previous_grade_id',
        'current_grade_number',
        'previous_grade_number',
        'school_id',
        'class_name',
        'status',
        'eligibility_status',
        'registration_status',
        'score_status',
        'award_status',
        'note',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function previousAcademicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'previous_academic_year_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
