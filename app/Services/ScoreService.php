<?php

namespace App\Services;

use App\Models\ExamScore;
use App\Models\ScoreLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScoreService
{
    public function enter(ExamScore $score, array $data, User $actor): ExamScore
    {
        if ($score->score_status === 'locked') {
            throw ValidationException::withMessages(['score' => 'Điểm đã khóa, giám thị không được chỉnh sửa.']);
        }

        return DB::transaction(function () use ($score, $data, $actor) {
            $old = $score->only(['official_score', 'score_status']);

            $score->fill([
                'official_score' => $data['official_score'] ?? $score->official_score,
                'completion_time_seconds' => $data['completion_time_seconds'] ?? null,
                'correct_answers' => $data['correct_answers'] ?? null,
                'exam_status' => $data['exam_status'] ?? 'completed',
                'score_status' => 'entered',
                'entered_by' => $actor->id,
                'entered_at' => now(),
                'note' => $data['note'] ?? null,
            ])->save();

            if (($old['official_score'] ?? null) !== null && (string) $old['official_score'] !== (string) $score->official_score) {
                $this->log($score, $actor, $old, 'Sửa điểm');
            }

            return $score;
        });
    }

    public function verify(ExamScore $score, User $actor): ExamScore
    {
        if (! $actor->can('scores.verify')) {
            throw ValidationException::withMessages(['score' => 'Bạn không có quyền xác nhận điểm.']);
        }

        $score->update([
            'score_status' => 'verified',
            'verified_by' => $actor->id,
            'verified_at' => now(),
        ]);

        return $score;
    }

    public function lock(ExamScore $score, User $actor): ExamScore
    {
        if (! $actor->can('scores.lock')) {
            throw ValidationException::withMessages(['score' => 'Bạn không có quyền khóa điểm.']);
        }

        $score->update([
            'score_status' => 'locked',
            'locked_by' => $actor->id,
            'locked_at' => now(),
        ]);

        return $score;
    }

    public function unlockAndChange(ExamScore $score, array $data, ?string $reason, User $actor): ExamScore
    {
        if (! $actor->can('scores.unlock')) {
            throw ValidationException::withMessages(['score' => 'Bạn không có quyền mở khóa/sửa điểm đã khóa.']);
        }

        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Vui lòng nhập lý do khi sửa điểm đã khóa.']);
        }

        return DB::transaction(function () use ($score, $data, $reason, $actor) {
            $old = $score->only(['official_score', 'score_status']);
            $score->update([
                'official_score' => $data['official_score'],
                'completion_time_seconds' => $data['completion_time_seconds'] ?? $score->completion_time_seconds,
                'correct_answers' => $data['correct_answers'] ?? $score->correct_answers,
                'exam_status' => $data['exam_status'] ?? $score->exam_status,
                'score_status' => 'entered',
                'entered_by' => $actor->id,
                'entered_at' => now(),
                'note' => $data['note'] ?? $score->note,
            ]);
            $this->log($score, $actor, $old, $reason);

            return $score;
        });
    }

    private function log(ExamScore $score, User $actor, array $old, string $reason): void
    {
        ScoreLog::create([
            'exam_score_id' => $score->id,
            'changed_by' => $actor->id,
            'old_score' => $old['official_score'] ?? null,
            'new_score' => $score->official_score,
            'old_status' => $old['score_status'] ?? null,
            'new_status' => $score->score_status,
            'reason' => $reason,
            'changed_at' => now(),
        ]);
    }
}
