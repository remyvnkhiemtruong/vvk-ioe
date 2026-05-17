<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoEvidence extends Model
{
    protected $table = 'video_evidence';

    protected $fillable = [
        'exam_id',
        'exam_room_id',
        'exam_session_id',
        'exam_time_window_id',
        'video_url',
        'storage_provider',
        'visibility_checked',
        'quality_status',
        'duration_note',
        'submitted_by',
        'submitted_at',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'visibility_checked' => 'boolean',
            'submitted_at' => 'datetime',
        ];
    }
}
