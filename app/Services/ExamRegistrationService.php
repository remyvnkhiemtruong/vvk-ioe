<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamRegistration;
use App\Models\ExamSession;
use App\Models\Student;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExamRegistrationService
{
    public function __construct(private readonly ExamSessionAvailabilityService $availability) {}

    public function create(Student $student, Exam $exam, array $input): ExamRegistration
    {
        if ($student->status !== 'active') {
            throw ValidationException::withMessages([
                'student' => 'Tài khoản của bạn đang bị khóa, vui lòng liên hệ giáo viên phụ trách.',
            ]);
        }

        return DB::transaction(function () use ($student, $exam, $input) {
            if ($student->registrations()->where('exam_id', $exam->id)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['exam' => 'Bạn đã đăng ký kỳ thi này.']);
            }

            $session = $this->lockedSessionIfNeeded($exam, $input['exam_session_id'] ?? null);
            if ($session) {
                $this->availability->assertAvailable($session, $student, $exam);
            } elseif (! $this->availability->isExamTargetForStudent($exam, $student)) {
                throw ValidationException::withMessages(['exam' => 'Lớp/khối của bạn không thuộc đối tượng đăng ký kỳ thi này.']);
            }

            try {
                $registration = ExamRegistration::create([
                    ...$this->payload($student, $input),
                    'student_id' => $student->id,
                    'exam_id' => $exam->id,
                    'exam_session_id' => $session?->id,
                    'grade_id' => $student->grade_id,
                    'school_class_id' => $student->school_class_id,
                    'primary_external_account_id' => trim((string) $input['ioe_id']),
                    'primary_external_username' => $input['primary_external_username'] ?? null,
                    'backup_external_account_id' => $this->backupAccount($exam, $input, 'backup_external_account_id'),
                    'backup_external_username' => $this->backupAccount($exam, $input, 'backup_external_username'),
                    'requested_by_user_id' => auth()->id(),
                    'eligibility_snapshot' => $this->eligibilitySnapshot($student, $exam),
                    'registration_code' => $this->registrationCode(),
                    'status' => $exam->require_approval ? 'submitted' : 'approved',
                    'personal_computer_status' => $this->personalComputerStatus($input),
                    'registered_at' => now(),
                ]);
            } catch (QueryException) {
                throw ValidationException::withMessages([
                    'ioe_id' => 'ID IOE hoặc CCCD/mã định danh đã được đăng ký trong kỳ thi này.',
                ]);
            }

            $this->syncStudentContact($student, $input);
            if ($session) {
                $this->availability->refreshFullStatus($session->refresh());
            }

            return $registration;
        });
    }

    public function update(ExamRegistration $registration, array $input): ExamRegistration
    {
        $exam = $registration->exam;
        $student = $registration->student;

        return DB::transaction(function () use ($registration, $exam, $student, $input) {
            $sessionId = $input['exam_session_id'] ?? $registration->exam_session_id;

            if ($sessionId && (int) $registration->exam_session_id !== (int) $sessionId && ! $exam->allow_student_session_change) {
                throw ValidationException::withMessages([
                    'exam_session_id' => 'Nhà trường chưa cho phép học sinh đổi ca thi.',
                ]);
            }

            $oldSession = $registration->chosenSession;
            $session = $this->lockedSessionIfNeeded($exam, $sessionId);
            if ($session) {
                $this->availability->assertAvailable($session, $student, $exam, $registration->id);
            } elseif (! $this->availability->isExamTargetForStudent($exam, $student)) {
                throw ValidationException::withMessages(['exam' => 'Lớp/khối của bạn không thuộc đối tượng đăng ký kỳ thi này.']);
            }

            try {
                $registration->update([
                    ...$this->payload($student, $input),
                    'exam_session_id' => $session?->id,
                    'primary_external_account_id' => trim((string) $input['ioe_id']),
                    'primary_external_username' => $input['primary_external_username'] ?? $registration->primary_external_username,
                    'backup_external_account_id' => $this->backupAccount($exam, $input, 'backup_external_account_id'),
                    'backup_external_username' => $this->backupAccount($exam, $input, 'backup_external_username'),
                    'personal_computer_status' => $this->personalComputerStatus($input),
                ]);
            } catch (QueryException) {
                throw ValidationException::withMessages([
                    'ioe_id' => 'ID IOE hoặc CCCD/mã định danh đã được đăng ký trong kỳ thi này.',
                ]);
            }

            $this->syncStudentContact($student, $input);
            if ($session) {
                $this->availability->refreshFullStatus($session->refresh());
            }
            if ($oldSession && $oldSession->isNot($session)) {
                $this->availability->refreshFullStatus($oldSession->refresh());
            }

            return $registration->refresh();
        });
    }

    private function lockedSessionIfNeeded(Exam $exam, int|string|null $sessionId): ?ExamSession
    {
        if (! $sessionId) {
            if ($exam->requiresSessionChoice()) {
                throw ValidationException::withMessages(['exam_session_id' => 'Vui lòng chọn ca thi.']);
            }

            return null;
        }

        return ExamSession::whereKey($sessionId)->lockForUpdate()->firstOrFail();
    }

    private function payload(Student $student, array $input): array
    {
        $dateOfBirth = $student->date_of_birth ?: ($input['date_of_birth'] ?? null);
        $gender = $student->gender ?: ($input['gender'] ?? null);
        $identity = $student->identity_number ?: ($input['identity_number'] ?? null);
        $address = $input['address'] ?? $student->address;
        $phone = $input['phone'] ?? $student->phone;
        $email = $input['email'] ?? $student->email;

        if (! $dateOfBirth || ! $gender || ! $identity || ! $address || ! $phone || ! $email) {
            throw ValidationException::withMessages([
                'profile' => 'Hồ sơ còn thiếu ngày sinh, giới tính, CCCD/mã định danh, địa chỉ, số điện thoại hoặc email. Vui lòng cập nhật hoặc liên hệ giáo viên phụ trách.',
            ]);
        }

        $usesPersonalComputer = (bool) ($input['uses_personal_computer'] ?? false);

        return [
            'full_name' => $student->full_name,
            'ioe_id' => trim((string) $input['ioe_id']),
            'date_of_birth' => $dateOfBirth,
            'gender' => $gender,
            'identity_number' => $identity,
            'class_name' => $student->class_name,
            'address' => $address,
            'phone' => $phone,
            'email' => $email,
            'note' => $input['note'] ?? null,
            'custom_fields' => $input['custom_fields'] ?? null,
            'uses_personal_computer' => $usesPersonalComputer,
            'device_type' => $usesPersonalComputer ? ($input['device_type'] ?? null) : null,
            'device_os' => $usesPersonalComputer ? ($input['device_os'] ?? null) : null,
            'has_charger' => $usesPersonalComputer ? (bool) ($input['has_charger'] ?? false) : null,
            'device_note' => $usesPersonalComputer ? ($input['device_note'] ?? null) : null,
            'device_commitment' => $usesPersonalComputer && (bool) ($input['device_commitment'] ?? false),
        ];
    }

    private function backupAccount(Exam $exam, array $input, string $field): ?string
    {
        if (! $exam->allowsBackupAccount()) {
            return null;
        }

        return blank($input[$field] ?? null) ? null : trim((string) $input[$field]);
    }

    private function eligibilitySnapshot(Student $student, Exam $exam): array
    {
        return [
            'student_code' => $student->student_code,
            'grade' => $student->resolvedGrade(),
            'class_name' => $student->class_name,
            'exam_target_grades' => $exam->target_grades,
            'exam_target_classes' => $exam->target_classes,
            'registration_mode' => $exam->registration_mode ?? 'admin_assign_session',
            'captured_at' => now()->toIso8601String(),
        ];
    }

    private function personalComputerStatus(array $input): string
    {
        return (bool) ($input['uses_personal_computer'] ?? false) ? 'pending' : 'not_applicable';
    }

    private function syncStudentContact(Student $student, array $input): void
    {
        $student->fill([
            'phone' => $input['phone'] ?? $student->phone,
            'email' => $input['email'] ?? $student->email,
            'address' => $input['address'] ?? $student->address,
        ])->save();
    }

    private function registrationCode(): string
    {
        do {
            $code = 'IOE-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        } while (ExamRegistration::where('registration_code', $code)->exists());

        return $code;
    }
}
