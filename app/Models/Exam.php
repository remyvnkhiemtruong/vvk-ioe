<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Exam extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'school_year',
        'level',
        'registration_opens_at',
        'registration_closes_at',
        'exam_date',
        'exam_time',
        'target_grades',
        'target_classes',
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
            'exam_date' => 'date',
            'exam_time' => 'datetime:H:i',
            'target_grades' => 'array',
            'target_classes' => 'array',
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

    public function formFields(): HasMany
    {
        return $this->hasMany(ExamFormField::class);
    }

    public function isRegistrationOpen(): bool
    {
        return $this->level === 'school'
            && $this->status === 'open'
            && (! $this->registration_opens_at || now()->greaterThanOrEqualTo($this->registration_opens_at))
            && (! $this->registration_closes_at || now()->lessThanOrEqualTo($this->registration_closes_at));
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
