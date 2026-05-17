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
        'normalized_name',
        'grade',
        'grade_id',
        'class_name',
        'school_class_id',
        'academic_year_id',
        'school_id',
        'student_code',
        'ioe_account_id',
        'identity_number',
        'ministry_identifier',
        'date_of_birth',
        'gender',
        'ethnicity',
        'is_verified',
        'current_self_training_round',
        'imported_from_ioe',
        'source_academic_year',
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
            'is_verified' => 'boolean',
            'imported_from_ioe' => 'boolean',
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

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function selfTrainingProgress(): HasMany
    {
        return $this->hasMany(SelfTrainingProgress::class);
    }

    public function studentScores(): HasMany
    {
        return $this->hasMany(StudentScore::class);
    }

    public function awardRecords(): HasMany
    {
        return $this->hasMany(AwardRecord::class);
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
