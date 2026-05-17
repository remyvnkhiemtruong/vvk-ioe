<?php

namespace App\Models;

use App\Services\StudentGradeResolver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'full_name',
        'grade',
        'class_name',
        'student_code',
        'identity_number',
        'date_of_birth',
        'gender',
        'phone',
        'email',
        'address',
        'note',
        'import_batch_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'grade' => 'integer',
        ];
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(ExamRegistration::class);
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function maskedIdentity(): string
    {
        if (! $this->identity_number) {
            return '';
        }

        return str_repeat('*', max(strlen($this->identity_number) - 4, 0)).substr($this->identity_number, -4);
    }

    public function resolvedGrade(): ?int
    {
        return app(StudentGradeResolver::class)->resolve($this);
    }
}
