<?php

namespace App\Services;

use App\Models\AwardRule;
use App\Models\AwardRuleItem;
use App\Models\Exam;
use App\Models\Ranking;
use App\Models\StudentScore;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AwardService
{
    private const SCOPE_PRIORITY = ['national' => 1, 'province' => 2, 'ward' => 3, 'school' => 4];

    private const AWARD_PRIORITY = [
        'first' => 1,
        'gold' => 1,
        'second' => 2,
        'silver' => 2,
        'third' => 3,
        'bronze' => 3,
        'encouragement' => 4,
    ];

    public function run(Exam $exam, ?int $gradeNumber = null, ?string $scope = null): array
    {
        return DB::transaction(function () use ($exam, $gradeNumber, $scope): array {
            $rules = AwardRule::where('exam_id', $exam->id)
                ->where('is_active', true)
                ->when($scope, fn ($q) => $q->where('scope', $scope))
                ->when($gradeNumber !== null, fn ($q) => $q->where(function ($q2) use ($gradeNumber) {
                    $q2->where('grade_number', $gradeNumber)->orWhereNull('grade_number');
                }))
                ->orderBy('priority_order')
                ->orderBy('id')
                ->with('items')
                ->get();

            if ($rules->isEmpty()) {
                return $this->report($exam, $scope, $gradeNumber, [], []);
            }

            $this->assertRankingsReady($exam, $rules, $gradeNumber);
            $this->clearAwards($exam, $scope, $gradeNumber);

            $awardedByGrade = [];
            $awardedByCode = [];

            foreach ($rules as $rule) {
                foreach ($this->gradesForRule($exam, $rule, $gradeNumber) as $grade) {
                    $result = $this->applyRuleToGrade($exam, $rule, (int) $grade);

                    if ($result['total'] === 0) {
                        continue;
                    }

                    $awardedByGrade[(int) $grade] = ($awardedByGrade[(int) $grade] ?? 0) + $result['total'];

                    foreach ($result['by_code'] as $code => $count) {
                        $awardedByCode[$code] = ($awardedByCode[$code] ?? 0) + $count;
                    }
                }
            }

            $this->markHighestAwards($exam);

            return $this->report($exam, $scope, $gradeNumber, $awardedByGrade, $awardedByCode);
        });
    }

    private function applyRuleToGrade(Exam $exam, AwardRule $rule, int $gradeNumber): array
    {
        $rankingsQuery = Ranking::where('exam_id', $exam->id)
            ->where('scope', $rule->scope)
            ->where('grade_number', $gradeNumber)
            ->whereNull('award_code')
            ->orderBy('rank')
            ->orderByDesc('score')
            ->orderByRaw('CASE WHEN duration_seconds IS NULL THEN 1 ELSE 0 END')
            ->orderBy('duration_seconds')
            ->orderBy('student_id')
            ->with('studentScore');

        if ($rule->min_score !== null) {
            $rankingsQuery->where('score', '>=', $rule->min_score);
        }

        /** @var Collection<int, Ranking> $eligible */
        $eligible = $rankingsQuery->get()
            ->filter(function (Ranking $ranking) use ($rule): bool {
                if ($rule->min_score_percent && $ranking->studentScore) {
                    return $ranking->studentScore->scorePercent() >= $rule->min_score_percent;
                }

                return true;
            })
            ->values();

        if ($eligible->isEmpty()) {
            return ['total' => 0, 'by_code' => []];
        }

        if ($rule->top_percent) {
            $topCount = max(1, (int) ceil($eligible->count() * ($rule->top_percent / 100)));
            $eligible = $this->takeWithTies($eligible, $topCount);
        }

        if ($rule->max_awards) {
            $eligible = $this->takeWithTies($eligible, (int) $rule->max_awards);
        }

        $items = $rule->items;
        if ($items->isEmpty()) {
            return ['total' => 0, 'by_code' => []];
        }

        $totalEligible = $eligible->count();
        $offset = 0;
        $awarded = 0;
        $byCode = [];
        $now = Carbon::now();

        foreach ($items as $item) {
            if ($offset >= $totalEligible) {
                break;
            }

            $qty = $this->resolveQuantity($item, $totalEligible);
            if ($qty <= 0) {
                continue;
            }

            $slice = $this->sliceWithTies($eligible, $offset, $qty);

            foreach ($slice as $ranking) {
                $ranking->update([
                    'award_rule_id' => $rule->id,
                    'award_name' => $item->award_name,
                    'award_code' => $item->award_code,
                    'generated_at' => $now,
                ]);

                $awarded++;
                $byCode[$item->award_code] = ($byCode[$item->award_code] ?? 0) + 1;
            }

            $offset += $slice->count();
        }

        return ['total' => $awarded, 'by_code' => $byCode];
    }

    private function resolveQuantity(AwardRuleItem $item, int $totalEligible): int
    {
        if ($item->max_quantity !== null) {
            return (int) $item->max_quantity;
        }

        if ($item->ratio_percent !== null) {
            return max(1, (int) ceil($totalEligible * ($item->ratio_percent / 100)));
        }

        return 0;
    }

    private function assertRankingsReady(Exam $exam, Collection $rules, ?int $gradeNumber): void
    {
        $required = collect();

        foreach ($rules as $rule) {
            foreach ($this->gradesForRule($exam, $rule, $gradeNumber) as $grade) {
                $required->push([
                    'scope' => $rule->scope,
                    'grade_number' => (int) $grade,
                ]);
            }
        }

        $required = $required
            ->unique(fn (array $item) => $item['scope'].'-'.$item['grade_number'])
            ->values();

        if ($required->isEmpty()) {
            throw ValidationException::withMessages([
                'ranking' => 'Vui lòng chạy xếp hạng trước khi xếp giải.',
            ]);
        }

        foreach ($required as $item) {
            $hasRanking = Ranking::where('exam_id', $exam->id)
                ->where('scope', $item['scope'])
                ->where('grade_number', $item['grade_number'])
                ->exists();

            if (! $hasRanking) {
                throw ValidationException::withMessages([
                    'ranking' => 'Vui lòng chạy xếp hạng trước khi xếp giải.',
                ]);
            }

            $needsRerank = StudentScore::where('exam_id', $exam->id)
                ->where('grade_number', $item['grade_number'])
                ->where('needs_rerank', true)
                ->exists();

            if ($needsRerank) {
                throw ValidationException::withMessages([
                    'ranking' => 'Điểm đã thay đổi, vui lòng chạy lại xếp hạng trước khi xếp giải.',
                ]);
            }
        }
    }

    private function clearAwards(Exam $exam, ?string $scope, ?int $gradeNumber): void
    {
        Ranking::where('exam_id', $exam->id)
            ->when($scope, fn ($q) => $q->where('scope', $scope))
            ->when($gradeNumber !== null, fn ($q) => $q->where('grade_number', $gradeNumber))
            ->update([
                'award_rule_id' => null,
                'award_name' => null,
                'award_code' => null,
                'is_highest_award' => false,
            ]);
    }

    private function gradesForRule(Exam $exam, AwardRule $rule, ?int $requestedGrade): Collection
    {
        if ($requestedGrade !== null) {
            return collect([$requestedGrade]);
        }

        if ($rule->grade_number !== null) {
            return collect([(int) $rule->grade_number]);
        }

        return Ranking::where('exam_id', $exam->id)
            ->where('scope', $rule->scope)
            ->whereNotNull('grade_number')
            ->distinct()
            ->orderBy('grade_number')
            ->pluck('grade_number');
    }

    private function markHighestAwards(Exam $exam): void
    {
        Ranking::where('exam_id', $exam->id)->update(['is_highest_award' => false]);

        Ranking::where('exam_id', $exam->id)
            ->whereNotNull('award_code')
            ->with('awardRule')
            ->orderBy('student_id')
            ->get()
            ->groupBy('student_id')
            ->each(function (Collection $studentRankings): void {
                $highest = $studentRankings->sortBy(function (Ranking $ranking): string {
                    return sprintf(
                        '%02d-%02d-%04d-%08d-%08d',
                        self::SCOPE_PRIORITY[$ranking->scope] ?? 99,
                        self::AWARD_PRIORITY[$ranking->award_code] ?? 99,
                        $ranking->awardRule?->priority_order ?? 99,
                        $ranking->rank,
                        $ranking->id
                    );
                })->first();

                $highest?->update(['is_highest_award' => true]);
            });
    }

    private function takeWithTies(Collection $items, int $limit): Collection
    {
        return $this->sliceWithTies($items, 0, $limit);
    }

    private function sliceWithTies(Collection $items, int $offset, int $limit): Collection
    {
        if ($limit <= 0 || $offset >= $items->count()) {
            return collect();
        }

        $slice = $items->slice($offset, $limit)->values();
        $last = $slice->last();

        if (! $last) {
            return $slice;
        }

        $cursor = $offset + $slice->count();

        while ($cursor < $items->count()) {
            $candidate = $items->get($cursor);

            if (! $candidate || ! $this->samePerformance($last, $candidate)) {
                break;
            }

            $slice->push($candidate);
            $cursor++;
        }

        return $slice;
    }

    private function samePerformance(Ranking $a, Ranking $b): bool
    {
        return (string) $a->score === (string) $b->score
            && $a->duration_seconds === $b->duration_seconds;
    }

    private function report(Exam $exam, ?string $scope, ?int $gradeNumber, array $awardedByGrade, array $awardedByCode): array
    {
        ksort($awardedByGrade);
        ksort($awardedByCode);

        return [
            'exam_id' => $exam->id,
            'scope' => $scope,
            'grade_number' => $gradeNumber,
            'total_awarded' => array_sum($awardedByGrade),
            'awarded_by_grade' => $awardedByGrade,
            'awarded_by_code' => $awardedByCode,
        ];
    }
}
