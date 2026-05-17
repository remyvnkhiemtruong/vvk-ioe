<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamMinute extends Model
{
    protected $fillable = [
        'exam_id',
        'exam_room_id',
        'exam_session_id',
        'exam_time_window_id',
        'generated_file_path',
        'signed_scan_path',
        'status',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }
}
