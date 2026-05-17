<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamRoom extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'room_code',
        'room_name',
        'location',
        'capacity',
        'computer_count',
        'headset_count',
        'camera_available',
        'internet_status',
        'usable_computers',
        'backup_computers',
        'note',
        'status',
    ];

    protected function casts(): array
    {
        return ['camera_available' => 'boolean'];
    }

    public function computers(): HasMany
    {
        return $this->hasMany(RoomComputer::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ExamSession::class);
    }
}
