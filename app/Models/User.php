<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'username', 'phone', 'avatar_path', 'password', 'role', 'status', 'student_id', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function staffProfile(): HasOne
    {
        return $this->hasOne(StaffProfile::class);
    }

    public function proctorAssignments(): HasMany
    {
        return $this->hasMany(ProctorAssignment::class);
    }

    public function createdSessions(): HasMany
    {
        return $this->hasMany(ExamSession::class, 'created_by');
    }

    public function avatarUrl(): string
    {
        if ($this->avatar_path) {
            return asset('storage/'.$this->avatar_path);
        }

        $name = urlencode($this->name ?: 'U');

        return "https://ui-avatars.com/api/?name={$name}&background=059669&color=fff&size=128";
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin', 'exam_admin'], true)
            || $this->hasAnyRole(['admin', 'super_admin', 'exam_admin']);
    }

    public function isTeacher(): bool
    {
        return $this->role === 'teacher' || $this->hasRole('teacher');
    }

    public function isProctor(): bool
    {
        return $this->role === 'proctor' || $this->hasRole('proctor');
    }

    public function isStudent(): bool
    {
        return $this->role === 'student' || $this->hasRole('student');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
