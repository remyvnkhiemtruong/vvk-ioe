<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamChecklist extends Model
{
    protected $fillable = [
        'exam_id',
        'exam_room_id',
        'exam_session_id',
        'exam_time_window_id',
        'checked_by',
        'internet_ok',
        'computers_ok',
        'headsets_ok',
        'camera_ok',
        'time_zone_ok',
        'backup_power_network_ready',
        'notes',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'internet_ok' => 'boolean',
            'computers_ok' => 'boolean',
            'headsets_ok' => 'boolean',
            'camera_ok' => 'boolean',
            'time_zone_ok' => 'boolean',
            'backup_power_network_ready' => 'boolean',
            'checked_at' => 'datetime',
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

    public function session(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class, 'exam_session_id');
    }
}
