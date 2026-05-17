<?php

namespace App\Policies;

use App\Models\ExamScore;
use App\Models\User;
use App\Services\ProctorScope;

class ExamScorePolicy
{
    public function view(User $user, ExamScore $score): bool
    {
        if ($user->isAdmin() || $user->isTeacher()) {
            return true;
        }

        if ($user->student_id === $score->registration->student_id) {
            return (bool) $score->registration->exam->publish_scores;
        }

        return app(ProctorScope::class)->canAccessRegistration($user, $score->registration);
    }

    public function update(User $user, ExamScore $score): bool
    {
        if ($score->score_status === 'locked') {
            return $user->can('scores.unlock');
        }

        return $user->can('scores.enter');
    }

    public function lock(User $user): bool
    {
        return $user->can('scores.lock');
    }
}
