<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AwardRecord extends Model
{
    protected $fillable = [
        'academic_year_id',
        'exam_id',
        'student_id',
        'student_score_id',
        'grade_number',
        'school_id',
        'award_scope',
        'award_name',
        'award_code',
        'score',
        'duration_seconds',
        'raw_duration_text',
        'raw_award_text',
        'source_key',
        'mapping_status',
        'imported_from_file',
        'is_highest_award',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_highest_award' => 'boolean',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function studentScore(): BelongsTo
    {
        return $this->belongsTo(StudentScore::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
