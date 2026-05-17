<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffProfile extends Model
{
    protected $fillable = [
        'staff_code',
        'identity_number',
        'ministry_identifier',
        'full_name',
        'date_of_birth',
        'gender',
        'ethnicity',
        'employment_status',
        'staff_type',
        'position_group',
        'contract_type',
        'qualification',
        'subject',
        'suggested_role',
        'role_approved_by',
        'role_approved_at',
        'user_id',
        'import_batch_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'role_approved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function maskedIdentity(): string
    {
        if (! $this->identity_number) {
            return '';
        }

        return str_repeat('*', max(strlen($this->identity_number) - 4, 0)).substr($this->identity_number, -4);
    }
}
