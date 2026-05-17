<?php

namespace App\Policies;

use App\Models\ExamRegistration;
use App\Models\User;
use App\Services\ProctorScope;

class ExamRegistrationPolicy
{
    public function view(User $user, ExamRegistration $registration): bool
    {
        return $user->isAdmin()
            || $user->isTeacher()
            || $user->student_id === $registration->student_id
            || $this->isAssignedProctor($user, $registration);
    }

    public function update(User $user, ExamRegistration $registration): bool
    {
        if ($user->isAdmin() || $user->can('registrations.update')) {
            return true;
        }

        return $user->student_id === $registration->student_id
            && $registration->exam->allow_student_edit
            && $registration->exam->isRegistrationOpen();
    }

    public function approve(User $user): bool
    {
        return $user->can('registrations.approve');
    }

    private function isAssignedProctor(User $user, ExamRegistration $registration): bool
    {
        $assignment = $registration->seatAssignment;

        if (! $assignment) {
            return false;
        }

        return app(ProctorScope::class)->canAccessAssignment($user, $assignment);
    }
}
