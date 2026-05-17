<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamRegistration;
use App\Models\ExamSession;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ExamSessionAvailabilityService
{
    public const VALID_REGISTRATION_STATUSES = ['submitted', 'pending', 'approved'];

    public function __construct(private readonly StudentGradeResolver $grades) {}

    /**
     * @return Collection<int, ExamSession>
     */
    public function availableForStudent(Student $student, Exam $exam, ?ExamRegistration $currentRegistration = null): Collection
    {
        return ExamSession::query()
            ->with(['room'])
            ->where('exam_id', $exam->id)
            ->whereIn('status', ['open', 'full'])
            ->orderBy('exam_date')
            ->orderBy('start_time')
            ->get()
            ->filter(function (ExamSession $session) use ($student, $exam, $currentRegistration) {
                $isCurrent = $currentRegistration
                    && (int) $currentRegistration->exam_session_id === (int) $session->id;

                if ($isCurrent) {
                    $session->setAttribute('remaining_slots', $this->remainingSlots($session, $currentRegistration->id));
                    $session->setAttribute('is_current_choice', true);

                    return $this->isExamTargetForStudent($exam, $student)
                        && $this->isTargetForStudent($session, $student);
                }

                $error = $this->availabilityError($session, $student, $exam, $currentRegistration?->id);
                $session->setAttribute('remaining_slots', $this->remainingSlots($session, $currentRegistration?->id));
                $session->setAttribute('availability_error', $error);

                return $error === null;
            })
            ->values();
    }

    public function availabilityError(ExamSession $session, Student|ExamRegistration $studentOrRegistration, Exam $exam, ?int $ignoreRegistrationId = null): ?string
    {
        if ((int) $session->exam_id !== (int) $exam->id) {
            return 'Ca thi không thuộc kỳ đăng ký hiện tại.';
        }

        if (! $this->isExamTargetForStudent($exam, $studentOrRegistration)) {
            return 'Lớp/khối của bạn không thuộc đối tượng đăng ký kỳ thi này.';
        }

        if (! in_array($session->status, ['open', 'full'], true)) {
            return 'Ca thi này chưa mở hoặc đã bị khóa.';
        }

        if ($session->status === 'full' && $this->remainingSlots($session, $ignoreRegistrationId) <= 0) {
            return 'Ca thi này đã đủ số lượng. Vui lòng chọn ca thi khác.';
        }

        if (! $this->isTargetForStudent($session, $studentOrRegistration)) {
            return 'Bạn không được chọn ca thi không thuộc khối/lớp của mình.';
        }

        if ($this->remainingSlots($session, $ignoreRegistrationId) <= 0) {
            return 'Ca thi này đã đủ số lượng. Vui lòng chọn ca thi khác.';
        }

        return null;
    }

    public function assertAvailable(ExamSession $session, Student|ExamRegistration $studentOrRegistration, Exam $exam, ?int $ignoreRegistrationId = null): void
    {
        $error = $this->availabilityError($session, $studentOrRegistration, $exam, $ignoreRegistrationId);

        if ($error) {
            throw ValidationException::withMessages(['exam_session_id' => $error]);
        }
    }

    public function isTargetForStudent(ExamSession $session, Student|ExamRegistration $studentOrRegistration): bool
    {
        $studentGrade = $this->grades->resolve($studentOrRegistration);
        $studentClass = trim((string) ($studentOrRegistration->class_name ?? ''));

        if ($session->target_grade && (int) $session->target_grade !== (int) $studentGrade) {
            return false;
        }

        $targetClasses = collect($session->target_classes ?? [])
            ->filter()
            ->map(fn ($class) => trim((string) $class))
            ->values();

        if ($targetClasses->isNotEmpty() && ! $targetClasses->contains($studentClass)) {
            return false;
        }

        return true;
    }

    public function isExamTargetForStudent(Exam $exam, Student|ExamRegistration $studentOrRegistration): bool
    {
        $studentGrade = $this->grades->resolve($studentOrRegistration);
        $studentClass = trim((string) ($studentOrRegistration->class_name ?? ''));

        $targetGrades = collect($exam->target_grades ?? [])
            ->filter(fn ($grade) => $grade !== null && $grade !== '')
            ->map(fn ($grade) => (int) $grade)
            ->values();

        if ($targetGrades->isNotEmpty() && ! $targetGrades->contains((int) $studentGrade)) {
            return false;
        }

        $targetClasses = collect($exam->target_classes ?? [])
            ->filter()
            ->map(fn ($class) => trim((string) $class))
            ->values();

        if ($targetClasses->isNotEmpty() && ! $targetClasses->contains($studentClass)) {
            return false;
        }

        return true;
    }

    public function remainingSlots(ExamSession $session, ?int $ignoreRegistrationId = null): int
    {
        return max((int) $session->max_candidates - $this->usedSlots($session, $ignoreRegistrationId), 0);
    }

    public function usedSlots(ExamSession $session, ?int $ignoreRegistrationId = null): int
    {
        return $this->validRegistrationsQuery($session->id, $ignoreRegistrationId)->count();
    }

    public function validRegistrationsQuery(int $sessionId, ?int $ignoreRegistrationId = null): Builder
    {
        return ExamRegistration::query()
            ->where('exam_session_id', $sessionId)
            ->whereIn('status', self::VALID_REGISTRATION_STATUSES)
            ->when($ignoreRegistrationId, fn (Builder $query) => $query->whereKeyNot($ignoreRegistrationId));
    }

    public function refreshFullStatus(ExamSession $session): void
    {
        if (! $session->exam?->auto_lock_full_sessions) {
            return;
        }

        if ($session->status === 'open' && $this->remainingSlots($session) <= 0) {
            $session->forceFill(['status' => 'full'])->save();
        }

        if ($session->status === 'full' && $this->remainingSlots($session) > 0) {
            $session->forceFill(['status' => 'open'])->save();
        }
    }
}
