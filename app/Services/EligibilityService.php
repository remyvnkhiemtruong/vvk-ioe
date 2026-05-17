<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamEligibilityRule;
use App\Models\ExamLevel;
use App\Models\ExamStudent;
use App\Models\Student;
use App\Models\StudentScore;

/**
 * EligibilityService – Kiểm tra điều kiện dự thi học sinh.
 *
 * Trả về kết quả đủ/thiếu điều kiện và lý do cụ thể.
 * Không xóa dữ liệu học sinh khi thiếu điều kiện, chỉ đánh dấu.
 */
class EligibilityService
{
    /**
     * Kiểm tra điều kiện và cập nhật ExamStudent record.
     *
     * @return array{eligible: bool, reasons: string[]}
     */
    public function check(ExamStudent $examStudent): array
    {
        $exam     = $examStudent->exam()->with('examLevel')->first();
        $student  = $examStudent->student;
        $reasons  = [];

        if (! $exam || ! $student) {
            return ['eligible' => false, 'reasons' => ['Không tìm thấy thông tin kỳ thi hoặc học sinh.']];
        }

        $level   = $exam->examLevel;
        $grade   = $examStudent->grade_number ?? $student->grade;

        // ── 1. Kiểm tra khối có được phép dự thi ─────────────────────────
        if ($level && ! $level->allowsGrade((int) $grade)) {
            $reasons[] = sprintf('Khối %d không được phép dự thi %s.', $grade, $level->name);
        }

        // ── 2. Lấy rule điều kiện cụ thể ────────────────────────────────
        $rule = $this->getRule($exam, $level, (int) $grade);

        // ── 3. Kiểm tra vòng tự luyện ───────────────────────────────────
        $minRound = $rule?->min_self_training_round ?? $level?->min_self_training_round ?? 0;
        if ($minRound > 0 && $examStudent->self_training_round < $minRound) {
            $reasons[] = sprintf(
                'Chưa hoàn thành vòng tự luyện thứ %d (hiện tại: vòng %d).',
                $minRound,
                $examStudent->self_training_round
            );
        }

        // ── 4. Kiểm tra xác thực tài khoản IOE ──────────────────────────
        $requireVerified = $rule?->require_verified_account ?? $level?->require_verified_account ?? true;
        if ($requireVerified && ! $examStudent->ioe_account_verified) {
            $reasons[] = 'Tài khoản IOE chưa được xác thực thành công.';
        }

        // ── 5. Cấp quốc gia: yêu cầu điểm cấp tỉnh ─────────────────────
        $requirePrev = $rule?->require_previous_exam_result ?? $level?->require_previous_level_result ?? false;
        if ($requirePrev) {
            $prevResult = $this->getPreviousLevelScore(
                $student,
                $level,
                $exam,
                (int) $grade,
                $rule
            );

            if (! $prevResult) {
                $prevLevelName = $level->previous_level_code
                    ? (ExamLevel::findByCode($level->previous_level_code)?->name ?? $level->previous_level_code)
                    : 'cấp trước';
                $reasons[] = sprintf('Chưa có điểm kết quả %s đủ điều kiện dự thi %s.', $prevLevelName, $level->name);
            }
        }

        // ── 6. Kiểm tra đã tồn tại trong danh sách ──────────────────────
        // (Đã xử lý bởi unique constraint, nhưng thêm message thân thiện)

        $eligible = empty($reasons);

        // Cập nhật ExamStudent
        $examStudent->update([
            'eligibility_status'  => $eligible ? 'eligible' : 'ineligible',
            'ineligible_reasons'  => $eligible ? null : $reasons,
            'status'              => $eligible
                ? (in_array($examStudent->status, ['draft', 'ineligible'], true) ? 'eligible' : $examStudent->status)
                : 'ineligible',
        ]);

        return ['eligible' => $eligible, 'reasons' => $reasons];
    }

    /**
     * Kiểm tra hàng loạt tất cả ExamStudent của một kỳ thi.
     *
     * @return array{total: int, eligible: int, ineligible: int}
     */
    public function checkAll(Exam $exam): array
    {
        $total     = 0;
        $eligible  = 0;
        $ineligible = 0;

        ExamStudent::where('exam_id', $exam->id)
            ->with(['student', 'exam.examLevel'])
            ->each(function (ExamStudent $es) use (&$total, &$eligible, &$ineligible): void {
                $total++;
                $result = $this->check($es);
                $result['eligible'] ? $eligible++ : $ineligible++;
            });

        return compact('total', 'eligible', 'ineligible');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function getRule(Exam $exam, ?ExamLevel $level, int $gradeNumber): ?ExamEligibilityRule
    {
        if (! $level) {
            return null;
        }

        // Rule cụ thể cho grade + level trong exam
        return ExamEligibilityRule::where('exam_id', $exam->id)
            ->where('exam_level_id', $level->id)
            ->where(function ($q) use ($gradeNumber) {
                $q->where('grade_number', $gradeNumber)->orWhereNull('grade_number');
            })
            ->where('is_active', true)
            ->orderByRaw('grade_number IS NULL ASC') // Ưu tiên rule cụ thể hơn rule tổng
            ->first();
    }

    private function getPreviousLevelScore(
        Student $student,
        ExamLevel $currentLevel,
        Exam $currentExam,
        int $gradeNumber,
        ?ExamEligibilityRule $rule
    ): ?StudentScore {
        $prevLevelCode = $currentLevel->previous_level_code;
        if (! $prevLevelCode) {
            return null;
        }

        $prevLevel = ExamLevel::findByCode($prevLevelCode);
        if (! $prevLevel) {
            return null;
        }

        // Tìm điểm cấp trước trong cùng academic_year
        $academicYearId = $currentExam->academic_year_id;

        $score = StudentScore::whereHas('exam', function ($q) use ($prevLevel, $academicYearId) {
            $q->where('exam_level_id', $prevLevel->id);
            if ($academicYearId) {
                $q->where('academic_year_id', $academicYearId);
            }
        })
            ->where('student_id', $student->id)
            ->where('grade_number', $gradeNumber)
            ->whereIn('status', ['submitted', 'locked', 'ranked'])
            ->first();

        if (! $score) {
            return null;
        }

        // Kiểm tra đạt ngưỡng điểm tối thiểu
        $minPercent = $rule?->min_previous_score_percent ?? $currentLevel->min_previous_score_percent ?? 50;
        if ($minPercent > 0 && $score->scorePercent() < $minPercent) {
            return null; // Có điểm nhưng chưa đạt ngưỡng
        }

        return $score;
    }
}
