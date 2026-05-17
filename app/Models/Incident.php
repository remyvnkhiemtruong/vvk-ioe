<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Incident extends Model
{
    protected $fillable = [
        'seat_assignment_id',
        'exam_registration_id',
        'incident_type',
        'description',
        'solution',
        'old_computer_id',
        'new_computer_id',
        'reported_by',
        'reported_at',
    ];

    protected function casts(): array
    {
        return ['reported_at' => 'datetime'];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(SeatAssignment::class, 'seat_assignment_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(ExamRegistration::class, 'exam_registration_id');
    }
}
