<?php

namespace App\Services;

use App\Models\ExamRegistration;
use App\Models\ExamResult;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExamResultService
{
    public function enter(ExamRegistration $registration, array $data, User $actor): ExamResult
    {
        $maxScore = (float) ($data['max_score'] ?? data_get($registration->exam->max_score_rule, $registration->student?->resolvedGrade().'.max', 1000));
        $score = (float) ($data['score'] ?? 0);

        if ($score < 0 || $score > $maxScore) {
            throw ValidationException::withMessages(['score' => 'Điểm thi phải nằm trong khoảng 0 đến '.$maxScore.'.']);
        }

        if (($data['external_account_type'] ?? 'primary') === 'backup'
            && ! Incident::where('exam_registration_id', $registration->id)->exists()) {
            throw ValidationException::withMessages(['external_account_type' => 'Dùng tài khoản dự phòng phải có biên bản sự cố đi kèm.']);
        }

        return DB::transaction(function () use ($registration, $data, $actor, $maxScore): ExamResult {
            $existing = ExamResult::where('exam_registration_id', $registration->id)->lockForUpdate()->first();

            if ($existing?->locked_at && ! $actor->can('results.lock')) {
                throw ValidationException::withMessages(['score' => 'Điểm đã khóa, người dùng hiện tại không được sửa.']);
            }

            return ExamResult::updateOrCreate(
                ['exam_registration_id' => $registration->id],
                [
                    'exam_id' => $registration->exam_id,
                    'student_id' => $registration->student_id,
                    'grade_id' => $registration->grade_id,
                    'external_account_type' => $data['external_account_type'] ?? 'primary',
                    'score' => $data['score'],
                    'max_score' => $maxScore,
                    'duration_seconds' => $data['duration_seconds'] ?? null,
                    'started_at' => $data['started_at'] ?? null,
                    'submitted_at' => $data['submitted_at'] ?? null,
                    'result_status' => $data['result_status'] ?? 'pending_review',
                    'source' => $data['source'] ?? 'manual_entry',
                    'entered_by' => $actor->id,
                    'notes' => $data['notes'] ?? null,
                ],
            );
        });
    }

    public function review(ExamResult $result, User $actor): ExamResult
    {
        if (! $actor->can('results.review')) {
            throw ValidationException::withMessages(['result' => 'Bạn không có quyền rà soát điểm.']);
        }

        $result->update([
            'result_status' => 'valid',
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ]);

        return $result;
    }

    public function lock(ExamResult $result, User $actor): ExamResult
    {
        if (! $actor->can('results.lock')) {
            throw ValidationException::withMessages(['result' => 'Bạn không có quyền khóa điểm.']);
        }

        $result->update(['locked_at' => now()]);

        return $result;
    }
}
