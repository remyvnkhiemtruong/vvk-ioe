<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SeatAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_registration_id',
        'exam_session_id',
        'exam_room_id',
        'seat_type',
        'computer_id',
        'computer_number',
        'backup_computer_id',
        'candidate_number',
        'assignment_method',
        'status',
        'assigned_by',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(ExamRegistration::class, 'exam_registration_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class, 'exam_session_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(ExamRoom::class, 'exam_room_id');
    }

    public function computer(): BelongsTo
    {
        return $this->belongsTo(RoomComputer::class, 'computer_id');
    }

    public function backupComputer(): BelongsTo
    {
        return $this->belongsTo(RoomComputer::class, 'backup_computer_id');
    }

    public function checkin(): HasOne
    {
        return $this->hasOne(Checkin::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }
}
