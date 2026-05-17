<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LiveScreen extends Model
{
    protected $fillable = [
        'exam_id', 'exam_session_id', 'token', 'is_enabled',
        'scope_type', 'display_title',
        'admin_override_hide', 'admin_override_show', 'force_ended_at',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'admin_override_hide' => 'boolean',
            'admin_override_show' => 'boolean',
            'force_ended_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (! $model->token) {
                $model->token = Str::random(48);
            }
        });
    }

    // ── Relations ────────────────────────────────────────────────────────────
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class, 'exam_session_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────
    public function liveUrl(): string
    {
        return route('live.show', $this->token);
    }

    public function stateUrl(): string
    {
        return route('live.state', $this->token);
    }

    /** Kỳ thi có force_ended_at chưa tới không */
    public function isForceEnded(): bool
    {
        return $this->force_ended_at !== null && $this->force_ended_at->isPast();
    }
}
