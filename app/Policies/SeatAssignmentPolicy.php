<?php

namespace App\Policies;

use App\Models\SeatAssignment;
use App\Models\User;
use App\Services\ProctorScope;

class SeatAssignmentPolicy
{
    public function view(User $user, SeatAssignment $assignment): bool
    {
        return $user->isAdmin()
            || $user->isTeacher()
            || app(ProctorScope::class)->canAccessAssignment($user, $assignment);
    }

    public function update(User $user, SeatAssignment $assignment): bool
    {
        return $user->can('assignments.manage')
            || app(ProctorScope::class)->canAccessAssignment($user, $assignment);
    }
}
