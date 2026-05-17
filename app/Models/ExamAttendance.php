<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamAttendance extends Model
{
    protected $table = 'exam_attendance';

    protected $fillable = ['exam_registration_id', 'seat_assignment_id', 'status', 'checked_in_at', 'checked_by', 'note'];

    protected function casts(): array
    {
        return ['checked_in_at' => 'datetime'];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(ExamRegistration::class, 'exam_registration_id');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(SeatAssignment::class, 'seat_assignment_id');
    }
}
