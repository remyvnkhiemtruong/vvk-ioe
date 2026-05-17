<?php

namespace App\Models;

use App\Services\StudentGradeResolver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'full_name',
        'grade',
        'grade_id',
        'class_name',
        'school_class_id',
        'academic_year_id',
        'student_code',
        'identity_number',
        'ministry_identifier',
        'date_of_birth',
        'gender',
        'ethnicity',
        'phone',
        'email',
        'address',
        'note',
        'health_metadata',
        'import_batch_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'grade' => 'integer',
            'health_metadata' => 'array',
        ];
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(ExamRegistration::class);
    }

    public function academicResults(): HasMany
    {
        return $this->hasMany(AcademicResult::class);
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function gradeModel(): BelongsTo
    {
        return $this->belongsTo(Grade::class, 'grade_id');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function maskedIdentity(): string
    {
        if (! $this->identity_number) {
            return '';
        }

        return str_repeat('*', max(strlen($this->identity_number) - 4, 0)).substr($this->identity_number, -4);
    }

    public function resolvedGrade(): ?int
    {
        return app(StudentGradeResolver::class)->resolve($this);
    }
}
