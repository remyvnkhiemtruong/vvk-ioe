<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExamRegistration extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'exam_id',
        'exam_session_id',
        'grade_id',
        'school_class_id',
        'full_name',
        'ioe_id',
        'primary_external_account_id',
        'primary_external_username',
        'backup_external_account_id',
        'backup_external_username',
        'date_of_birth',
        'gender',
        'identity_number',
        'class_name',
        'address',
        'phone',
        'email',
        'note',
        'custom_fields',
        'uses_personal_computer',
        'device_type',
        'device_os',
        'has_charger',
        'device_note',
        'device_commitment',
        'personal_computer_status',
        'registration_code',
        'requested_by_user_id',
        'approved_by_user_id',
        'status',
        'rejection_reason',
        'eligibility_snapshot',
        'registered_at',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'uses_personal_computer' => 'boolean',
            'has_charger' => 'boolean',
            'device_commitment' => 'boolean',
            'custom_fields' => 'array',
            'eligibility_snapshot' => 'array',
            'registered_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function chosenSession(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class, 'exam_session_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function result(): HasOne
    {
        return $this->hasOne(ExamResult::class, 'exam_registration_id');
    }

    public function seatAssignment(): HasOne
    {
        return $this->hasOne(SeatAssignment::class);
    }

    public function score(): HasOne
    {
        return $this->hasOne(ExamScore::class);
    }

    public function maskedIdentity(): string
    {
        return str_repeat('*', max(strlen($this->identity_number) - 4, 0)).substr($this->identity_number, -4);
    }

    public function effectiveSession(): ?ExamSession
    {
        return $this->chosenSession ?: $this->seatAssignment?->session;
    }
}
