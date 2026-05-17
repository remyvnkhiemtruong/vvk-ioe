<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomComputer extends Model
{
    use HasFactory;

    protected $fillable = ['exam_room_id', 'computer_label', 'computer_number', 'type', 'status', 'note'];

    public function room(): BelongsTo
    {
        return $this->belongsTo(ExamRoom::class, 'exam_room_id');
    }
}
