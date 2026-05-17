<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function view(User $user, Student $student): bool
    {
        return $user->isAdmin() || $user->isTeacher() || $user->student_id === $student->id;
    }

    public function viewSensitiveIdentity(User $user): bool
    {
        return $user->can('students.view_sensitive');
    }
}
