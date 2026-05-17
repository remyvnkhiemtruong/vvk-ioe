<?php

namespace App\Models;

use App\Services\ExamSessionAvailabilityService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'exam_room_id',
        'code',
        'name',
        'session_name',
        'session_date',
        'session_period',
        'starts_at',
        'ends_at',
        'exam_date',
        'start_time',
        'end_time',
        'target_grade',
        'target_classes',
        'allowed_grades',
        'session_code',
        'code_visible_from',
        'max_candidates',
        'status',
        'source',
        'mapping_status',
        'official_reference_session_id',
        'import_note',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'exam_date' => 'date',
            'session_date' => 'date',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'target_classes' => 'array',
            'allowed_grades' => 'array',
            'code_visible_from' => 'datetime',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(ExamRoom::class, 'exam_room_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(SeatAssignment::class);
    }

    public function proctors(): HasMany
    {
        return $this->hasMany(ProctorAssignment::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(ExamRegistration::class);
    }

    public function validRegistrations(): HasMany
    {
        return $this->registrations()->whereIn('status', ['submitted', 'pending', 'approved']);
    }

    public function timeWindows(): HasMany
    {
        return $this->hasMany(ExamTimeWindow::class, 'exam_session_id');
    }

    public function officialReferenceSession(): BelongsTo
    {
        return $this->belongsTo(self::class, 'official_reference_session_id');
    }

    public function remainingSlots(?int $ignoreRegistrationId = null): int
    {
        return app(ExamSessionAvailabilityService::class)
            ->remainingSlots($this, $ignoreRegistrationId);
    }

    public function isFull(?int $ignoreRegistrationId = null): bool
    {
        return $this->remainingSlots($ignoreRegistrationId) <= 0;
    }

    public function isAvailableForStudent(Student|ExamRegistration $studentOrRegistration, ?int $ignoreRegistrationId = null): bool
    {
        return app(ExamSessionAvailabilityService::class)
            ->availabilityError($this, $studentOrRegistration, $this->exam, $ignoreRegistrationId) === null;
    }

    public function targetLabel(): string
    {
        if (is_array($this->target_classes) && $this->target_classes !== []) {
            return 'Lớp '.implode(', ', $this->target_classes);
        }

        return $this->target_grade ? 'Khối '.$this->target_grade : 'Tất cả khối/lớp';
    }
}
