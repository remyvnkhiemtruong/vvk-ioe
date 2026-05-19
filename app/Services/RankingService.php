<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\Ranking;
use App\Models\StudentScore;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
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
     * @return array<string, mixed> Báo cáo xếp hạng
     */
    public function run(Exam $exam, string $scope = 'school', ?int $gradeNumber = null): array
    {
        return DB::transaction(function () use ($exam, $scope, $gradeNumber): array {
            $generatedAt = Carbon::now();
            $query = StudentScore::where('exam_id', $exam->id)
                ->whereIn('status', ['submitted', 'locked', 'ranked'])
                ->where('exclude_from_awards', false)
                ->whereNotNull('score')
                ->whereNotNull('grade_number');

            if ($gradeNumber !== null) {
                $query->where('grade_number', $gradeNumber);
            }

            $scores = $query->get();

            Ranking::where('exam_id', $exam->id)
                ->where('scope', $scope)
                ->when($gradeNumber !== null, fn ($q) => $q->where('grade_number', $gradeNumber))
                ->delete();

            $rankedByGrade = [];
            $rankedScoreIds = [];

            $gradeGroups = $gradeNumber !== null
                ? [$gradeNumber => $scores]
                : $scores->groupBy('grade_number');

            foreach ($gradeGroups as $grade => $gradeScores) {
                $result = $this->rankGroup($exam, $scope, (int) $grade, $gradeScores, $generatedAt);
                $rankedByGrade[(int) $grade] = $result['count'];
                $rankedScoreIds = array_merge($rankedScoreIds, $result['score_ids']);
            }

            if ($rankedScoreIds !== []) {
                StudentScore::whereIn('id', $rankedScoreIds)
                    ->update(['status' => 'ranked', 'needs_rerank' => false]);
            }

            return [
                'exam_id' => $exam->id,
                'scope' => $scope,
                'grade_number' => $gradeNumber,
                'total_ranked' => array_sum($rankedByGrade),
                'ranked_by_grade' => $rankedByGrade,
                'generated_at' => $generatedAt,
            ];
        });
    }

    /**
     * Xếp hạng một nhóm học sinh cùng khối.
     */
    private function rankGroup(Exam $exam, string $scope, int $gradeNumber, Collection $scores, Carbon $generatedAt): array
    {
        // Sắp xếp: điểm DESC, thời gian ASC (ngắn hơn xếp trên), null thời gian xuống cuối
        $sorted = $scores->sort(function (StudentScore $a, StudentScore $b): int {
            if ((float) $a->score !== (float) $b->score) {
                return (float) $b->score <=> (float) $a->score; // Điểm cao hơn đứng trước
            }

            // Bằng điểm → thời gian làm bài ngắn hơn đứng trước
            $aTime = $a->duration_seconds ?? PHP_INT_MAX;
            $bTime = $b->duration_seconds ?? PHP_INT_MAX;

            if ($aTime !== $bTime) {
                return $aTime <=> $bTime;
            }

            return $a->student_id <=> $b->student_id;
        })->values();

        $rank = 1;
        $count = 0;
        $scoreIds = [];

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
                    'grade_number'     => $gradeNumber,
                ],
                [
                    'student_id'      => $score->student_id,
                    'rank'            => $rank,
                    'score'           => $score->score,
                    'duration_seconds' => $score->duration_seconds,
                    'award_rule_id'   => null,
                    'award_name'      => null,
                    'award_code'      => null,
                    'is_highest_award' => false,
                    'generated_at'    => $generatedAt,
                ]
            );

            $count++;
            $scoreIds[] = $score->id;
        }

        return ['count' => $count, 'score_ids' => $scoreIds];
    }
}
