<?php

namespace App\Services;

use App\Models\AwardRule;
use App\Models\AwardRuleItem;
use App\Models\Exam;
use App\Models\Ranking;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * AwardService – Phân bổ giải theo award_rules.
 *
 * Quy tắc mặc định cấp trường:
 * - Toàn quốc: >= 80% điểm tối đa
 * - Địa phương/trường: >= 50%, TOP 20% học sinh, phân bổ 10%/20%/30%/40%
 *
 * Hỗ trợ một học sinh đạt nhiều giải, đánh dấu giải cao nhất theo priority.
 */
class AwardService
{
    // Priority scope: quốc gia > tỉnh > xã > trường
    private const SCOPE_PRIORITY = ['national' => 1, 'province' => 2, 'ward' => 3, 'school' => 4];

    /**
     * Chạy xếp giải cho toàn kỳ thi theo award_rules đã cấu hình.
     */
    public function run(Exam $exam, ?int $gradeNumber = null): int
    {
        $rules = AwardRule::where('exam_id', $exam->id)
            ->where('is_active', true)
            ->when($gradeNumber !== null, fn ($q) => $q->where(function ($q2) use ($gradeNumber) {
                $q2->where('grade_number', $gradeNumber)->orWhereNull('grade_number');
            }))
            ->orderBy('priority_order')
            ->with('items')
            ->get();

        if ($rules->isEmpty()) {
            return 0;
        }

        $awarded = 0;

        foreach ($rules as $rule) {
            $awarded += $this->applyRule($exam, $rule);
        }

        // Đánh dấu giải cao nhất cho mỗi học sinh
        $this->markHighestAwards($exam);

        return $awarded;
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function applyRule(Exam $exam, AwardRule $rule): int
    {
        // Lấy ranking theo scope + grade đã xếp hạng
        $rankingsQuery = Ranking::where('exam_id', $exam->id)
            ->where('scope', $rule->scope)
            ->when($rule->grade_number, fn ($q) => $q->where('grade_number', $rule->grade_number))
            ->orderBy('rank')
            ->with('studentScore');

        // Lọc theo ngưỡng điểm tối thiểu
        if ($rule->min_score !== null) {
            $rankingsQuery->where('score', '>=', $rule->min_score);
        }

        /** @var Collection<Ranking> $eligible */
        $eligible = $rankingsQuery->get()->filter(function (Ranking $r) use ($rule) {
            if ($rule->min_score_percent && $r->studentScore) {
                $percent = $r->studentScore->scorePercent();

                return $percent >= $rule->min_score_percent;
            }

            return true;
        });

        if ($eligible->isEmpty()) {
            return 0;
        }

        // Áp dụng TOP percent nếu có
        if ($rule->top_percent) {
            $topCount = max(1, (int) ceil($eligible->count() * ($rule->top_percent / 100)));
            $eligible = $eligible->take($topCount);
        }

        if ($rule->max_awards) {
            $eligible = $eligible->take($rule->max_awards);
        }

        // Phân bổ giải theo items
        $items = $rule->items;
        if ($items->isEmpty()) {
            return 0;
        }

        $totalEligible = $eligible->count();
        $offset        = 0;
        $awarded       = 0;
        $now           = Carbon::now();

        foreach ($items as $item) {
            if ($offset >= $totalEligible) {
                break;
            }

            // Tính số lượng giải
            $qty = $this->resolveQuantity($item, $totalEligible, $rule);
            if ($qty <= 0) {
                continue;
            }

            $slice = $eligible->slice($offset, $qty);

            foreach ($slice as $ranking) {
                $ranking->update([
                    'award_rule_id' => $rule->id,
                    'award_name'    => $item->award_name,
                    'award_code'    => $item->award_code,
                    'generated_at'  => $now,
                ]);
                $awarded++;
            }

            $offset += $qty;
        }

        return $awarded;
    }

    private function resolveQuantity(AwardRuleItem $item, int $totalEligible, AwardRule $rule): int
    {
        if ($item->max_quantity !== null) {
            // Số lượng cố định từ item
            return $item->max_quantity;
        }

        if ($item->ratio_percent !== null) {
            // Tỷ lệ phần trăm trong tổng giải
            return max(1, (int) ceil($totalEligible * ($item->ratio_percent / 100)));
        }

        return 0;
    }

    private function markHighestAwards(Exam $exam): void
    {
        // Reset tất cả
        Ranking::where('exam_id', $exam->id)->update(['is_highest_award' => false]);

        // Lấy tất cả ranking có giải
        $rankings = Ranking::where('exam_id', $exam->id)
            ->whereNotNull('award_code')
            ->orderBy('student_id')
            ->get()
            ->groupBy('student_id');

        foreach ($rankings as $studentRankings) {
            // Chọn giải cao nhất theo priority scope
            $highest = $studentRankings->sortBy(function (Ranking $r) {
                return self::SCOPE_PRIORITY[$r->scope] ?? 99;
            })->first();

            if ($highest) {
                $highest->update(['is_highest_award' => true]);
            }
        }
    }
}
