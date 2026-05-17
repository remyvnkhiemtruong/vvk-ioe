<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamRoom extends Model
{
    use HasFactory;

    protected $fillable = ['room_code', 'room_name', 'location', 'usable_computers', 'backup_computers', 'note', 'status'];

    public function computers(): HasMany
    {
        return $this->hasMany(RoomComputer::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ExamSession::class);
    }
}
