<?php

namespace App\Services;

use App\Models\ExamRegistration;
use App\Models\Incident;
use App\Models\SeatAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ProctorScope
{
    public function needsScope(User $user): bool
    {
        return $user->isProctor() && ! $user->isAdmin() && ! $user->isTeacher();
    }

    public function canAccessAssignment(User $user, ?SeatAssignment $assignment): bool
    {
        if (! $assignment) {
            return false;
        }

        if (! $this->needsScope($user)) {
            return true;
        }

        return $assignment->session
            ->proctors()
            ->where('user_id', $user->id)
            ->where('exam_room_id', $assignment->exam_room_id)
            ->exists();
    }

    public function canAccessRegistration(User $user, ExamRegistration $registration): bool
    {
        if (! $this->needsScope($user)) {
            return true;
        }

        return $this->canAccessAssignment($user, $registration->seatAssignment);
    }

    public function canAccessIncident(User $user, Incident $incident): bool
    {
        return $this->canAccessAssignment($user, $incident->assignment);
    }

    public function scopeAssignments(Builder $query, User $user): Builder
    {
        if (! $this->needsScope($user)) {
            return $query;
        }

        return $query->whereHas('session.proctors', function (Builder $proctors) use ($user) {
            $proctors->where('user_id', $user->id)
                ->whereColumn('proctor_assignments.exam_room_id', 'seat_assignments.exam_room_id');
        });
    }

    public function scopeRegistrations(Builder $query, User $user): Builder
    {
        if (! $this->needsScope($user)) {
            return $query;
        }

        return $query->whereHas('seatAssignment.session.proctors', function (Builder $proctors) use ($user) {
            $proctors->where('user_id', $user->id)
                ->whereColumn('proctor_assignments.exam_room_id', 'seat_assignments.exam_room_id');
        });
    }

    public function scopeIncidents(Builder $query, User $user): Builder
    {
        if (! $this->needsScope($user)) {
            return $query;
        }

        return $query->whereHas('assignment.session.proctors', function (Builder $proctors) use ($user) {
            $proctors->where('user_id', $user->id)
                ->whereColumn('proctor_assignments.exam_room_id', 'seat_assignments.exam_room_id');
        });
    }
}
