<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\Ranking;
use App\Models\StudentScore;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * RankingService – Xếp hạng theo điểm và thời gian làm bài.
 *
 * Nguyên tắc:
 * 1. Điểm cao hơn xếp trên.
 * 2. Bằng điểm → thời gian ngắn hơn xếp trên.
 * 3. Cùng điểm + cùng thời gian → cùng hạng (competition ranking: 1,1,3,4...).
 */
class RankingService
{
    /**
     * Chạy xếp hạng cho toàn bộ kỳ thi theo phạm vi (scope).
     *
     * @param  string  $scope  'school' | 'ward' | 'province' | 'national'
     * @param  int|null  $gradeNumber  Xếp hạng riêng từng khối, null = tất cả
     * @return int Số học sinh được xếp hạng
     */
    public function run(Exam $exam, string $scope = 'school', ?int $gradeNumber = null): int
    {
        $query = StudentScore::where('exam_id', $exam->id)
            ->whereIn('status', ['submitted', 'locked'])
            ->where('exclude_from_awards', false)
            ->whereNotNull('score');

        if ($gradeNumber !== null) {
            $query->where('grade_number', $gradeNumber);
        }

        $scores = $query->get();

        if ($scores->isEmpty()) {
            return 0;
        }

        // Xóa ranking cũ trong cùng scope
        Ranking::where('exam_id', $exam->id)
            ->where('scope', $scope)
            ->when($gradeNumber !== null, fn ($q) => $q->where('grade_number', $gradeNumber))
            ->delete();

        // Nhóm theo khối nếu không chỉ định
        $gradeGroups = $gradeNumber !== null
            ? [$gradeNumber => $scores]
            : $scores->groupBy('grade_number');

        $total = 0;
        foreach ($gradeGroups as $grade => $gradeScores) {
            $total += $this->rankGroup($exam, $scope, (int) $grade, $gradeScores);
        }

        // Đánh dấu điểm đã xếp hạng
        StudentScore::where('exam_id', $exam->id)
            ->whereIn('status', ['submitted', 'locked'])
            ->update(['status' => 'ranked', 'needs_rerank' => false]);

        return $total;
    }

    /**
     * Xếp hạng một nhóm học sinh cùng khối.
     */
    private function rankGroup(Exam $exam, string $scope, int $gradeNumber, Collection $scores): int
    {
        // Sắp xếp: điểm DESC, thời gian ASC (ngắn hơn xếp trên), null thời gian xuống cuối
        $sorted = $scores->sortWith(function (StudentScore $a, StudentScore $b): int {
            if ($a->score != $b->score) {
                return $b->score <=> $a->score; // Điểm cao hơn đứng trước
            }

            // Bằng điểm → thời gian làm bài ngắn hơn đứng trước
            $aTime = $a->duration_seconds ?? PHP_INT_MAX;
            $bTime = $b->duration_seconds ?? PHP_INT_MAX;

            return $aTime <=> $bTime;
        })->values();

        $now  = Carbon::now();
        $rank = 1;
        $count = 0;

        foreach ($sorted as $i => $score) {
            // Competition ranking: cùng điểm + thời gian → cùng hạng
            if ($i > 0) {
                $prev = $sorted[$i - 1];
                $sameScore = (string) $score->score === (string) $prev->score;
                $sameTime  = $score->duration_seconds === $prev->duration_seconds;

                if (! ($sameScore && $sameTime)) {
                    $rank = $i + 1;
                }
            }

            Ranking::updateOrCreate(
                [
                    'exam_id'          => $exam->id,
                    'student_score_id' => $score->id,
                    'scope'            => $scope,
                ],
                [
                    'student_id'      => $score->student_id,
                    'grade_number'    => $gradeNumber,
                    'rank'            => $rank,
                    'score'           => $score->score,
                    'duration_seconds' => $score->duration_seconds,
                    'award_rule_id'   => null,
                    'award_name'      => null,
                    'award_code'      => null,
                    'is_highest_award' => false,
                    'generated_at'    => $now,
                ]
            );

            $count++;
        }

        return $count;
    }
}
