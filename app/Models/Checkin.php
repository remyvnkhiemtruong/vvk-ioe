<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Checkin extends Model
{
    protected $fillable = [
        'seat_assignment_id',
        'status',
        'checked_in_at',
        'checked_by',
        'personal_device_present',
        'charger_present',
        'network_ok',
        'ioe_login_ok',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
            'personal_device_present' => 'boolean',
            'charger_present' => 'boolean',
            'network_ok' => 'boolean',
            'ioe_login_ok' => 'boolean',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(SeatAssignment::class, 'seat_assignment_id');
    }
}
