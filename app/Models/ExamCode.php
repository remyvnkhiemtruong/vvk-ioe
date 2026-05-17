<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ExamCode – Mã ca thi do admin nhập từ ioe.vn.
 * Hệ thống KHÔNG tự sinh mã thay ioe.vn.
 * source mặc định: manual_from_ioe
 */
class ExamCode extends Model
{
    protected $fillable = [
        'exam_id', 'exam_session_id', 'exam_time_slot_id',
        'code', 'label', 'applied_grade_ids', 'source',
        'is_active', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'applied_grade_ids' => 'array',
            'is_active' => 'boolean',
        ];
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

    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(ExamTimeWindow::class, 'exam_time_slot_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
