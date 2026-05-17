<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamLevel extends Model
{
    protected $fillable = [
        'code', 'name', 'sort_order', 'allowed_grades', 'min_self_training_round',
        'require_verified_account', 'require_previous_level_result', 'previous_level_code',
        'min_previous_score_percent', 'max_score_by_grade', 'award_rules_config', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'allowed_grades' => 'array',
            'max_score_by_grade' => 'array',
            'award_rules_config' => 'array',
            'require_verified_account' => 'boolean',
            'require_previous_level_result' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    public function eligibilityRules(): HasMany
    {
        return $this->hasMany(ExamEligibilityRule::class);
    }

    /** Điểm tối đa cho khối số @param int $gradeNumber */
    public function maxScoreForGrade(int $gradeNumber): int
    {
        return (int) data_get($this->max_score_by_grade, (string) $gradeNumber, 2000);
    }

    /** Kiểm tra khối có được phép dự thi ở cấp này không */
    public function allowsGrade(int $gradeNumber): bool
    {
        if (! is_array($this->allowed_grades)) {
            return true;
        }

        return in_array($gradeNumber, $this->allowed_grades, true);
    }

    public static function school(): ?self
    {
        return static::where('code', 'school')->first();
    }

    public static function findByCode(string $code): ?self
    {
        return static::where('code', $code)->first();
    }
}
