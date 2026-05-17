<?php

namespace App\Policies;

use App\Models\Incident;
use App\Models\User;
use App\Services\ProctorScope;

class IncidentPolicy
{
    public function view(User $user, Incident $incident): bool
    {
        return $user->isAdmin()
            || $user->isTeacher()
            || app(ProctorScope::class)->canAccessIncident($user, $incident);
    }

    public function create(User $user): bool
    {
        return $user->can('incidents.manage');
    }
}
