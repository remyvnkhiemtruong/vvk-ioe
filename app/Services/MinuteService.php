<?php

namespace App\Services;

use App\Models\ExamMinute;
use App\Models\ExamRoom;
use App\Models\ExamSession;

class MinuteService
{
    public function ensureForRoomSession(ExamSession $session, ExamRoom $room): ExamMinute
    {
        return ExamMinute::firstOrCreate(
            [
                'exam_id' => $session->exam_id,
                'exam_room_id' => $room->id,
                'exam_session_id' => $session->id,
                'exam_time_window_id' => null,
            ],
            ['status' => 'not_generated'],
        );
    }
}
