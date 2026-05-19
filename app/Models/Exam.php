<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Exam extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'school_year',
        'academic_year_id',
        'exam_level_id',
        'level',
        'template_type',
        'external_platform_name',
        'registration_mode',
        'organizer_scope',
        'registration_opens_at',
        'registration_closes_at',
        'registration_start_at',
        'registration_end_at',
        'exam_date',
        'exam_time',
        'exam_start_at',
        'exam_end_at',
        'target_grades',
        'target_classes',
        'max_score_rule',
        'result_source',
        'source',
        'has_imported_results',
        'imported_results_count',
        'settings',
        'timezone',
        'created_by',
        'updated_by',
        'allow_student_edit',
        'allow_student_session_change',
        'require_session_choice',
        'allow_personal_computer',
        'auto_lock_full_sessions',
        'show_public_stats',
        'require_approval',
        'publish_scores',
        'show_countdown',
        'countdown_mode',
        'status',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'registration_opens_at' => 'datetime',
            'registration_closes_at' => 'datetime',
            'registration_start_at' => 'datetime',
            'registration_end_at' => 'datetime',
            'exam_date' => 'date',
            'exam_time' => 'datetime:H:i',
            'exam_start_at' => 'datetime',
            'exam_end_at' => 'datetime',
            'target_grades' => 'array',
            'target_classes' => 'array',
            'max_score_rule' => 'array',
            'settings' => 'array',
            'has_imported_results' => 'boolean',
            'allow_student_edit' => 'boolean',
            'allow_student_session_change' => 'boolean',
            'require_session_choice' => 'boolean',
            'allow_personal_computer' => 'boolean',
            'auto_lock_full_sessions' => 'boolean',
            'show_public_stats' => 'boolean',
            'require_approval' => 'boolean',
            'publish_scores' => 'boolean',
            'show_countdown' => 'boolean',
        ];
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(ExamRegistration::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ExamSession::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function examLevel(): BelongsTo
    {
        return $this->belongsTo(ExamLevel::class);
    }

    public function examStudents(): HasMany
    {
        return $this->hasMany(ExamStudent::class);
    }

    public function studentScores(): HasMany
    {
        return $this->hasMany(StudentScore::class);
    }

    public function rankings(): HasMany
    {
        return $this->hasMany(Ranking::class);
    }

    public function awardRules(): HasMany
    {
        return $this->hasMany(AwardRule::class);
    }

    public function formFields(): HasMany
    {
        return $this->hasMany(ExamFormField::class);
    }

    public function isRegistrationOpen(): bool
    {
        $opensAt = $this->registration_start_at ?? $this->registration_opens_at;
        $closesAt = $this->registration_end_at ?? $this->registration_closes_at;

        return in_array($this->level, ['school', 'truong'], true)
            && $this->status === 'open'
            && (! $opensAt || now()->greaterThanOrEqualTo($opensAt))
            && (! $closesAt || now()->lessThanOrEqualTo($closesAt));
    }

    public function requiresSessionChoice(): bool
    {
        return ($this->registration_mode ?? null) === 'student_select_session'
            || ((bool) $this->require_session_choice && blank($this->registration_mode));
    }

    public function allowsBackupAccount(): bool
    {
        return (bool) data_get($this->settings, 'allow_backup_account', false);
    }

    public function examDateTime(): ?Carbon
    {
        if (! $this->exam_date) {
            return null;
        }

        $date = $this->exam_date->copy()->startOfDay();

        if ($this->exam_time) {
            $time = $this->exam_time instanceof Carbon
                ? $this->exam_time
                : Carbon::parse($this->exam_time);

            return $date->setTime((int) $time->format('H'), (int) $time->format('i'));
        }

        return $date;
    }
}
