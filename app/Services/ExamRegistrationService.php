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

            $session = $this->lockedSession($input['exam_session_id'] ?? null);
            $this->availability->assertAvailable($session, $student, $exam);

            try {
                $registration = ExamRegistration::create([
                    ...$this->payload($student, $input),
                    'student_id' => $student->id,
                    'exam_id' => $exam->id,
                    'exam_session_id' => $session->id,
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
            $this->availability->refreshFullStatus($session->refresh());

            return $registration;
        });
    }

    public function update(ExamRegistration $registration, array $input): ExamRegistration
    {
        $exam = $registration->exam;
        $student = $registration->student;

        return DB::transaction(function () use ($registration, $exam, $student, $input) {
            $sessionId = (int) ($input['exam_session_id'] ?? $registration->exam_session_id);

            if ((int) $registration->exam_session_id !== $sessionId && ! $exam->allow_student_session_change) {
                throw ValidationException::withMessages([
                    'exam_session_id' => 'Nhà trường chưa cho phép học sinh đổi ca thi.',
                ]);
            }

            $oldSession = $registration->chosenSession;
            $session = $this->lockedSession($sessionId);
            $this->availability->assertAvailable($session, $student, $exam, $registration->id);

            try {
                $registration->update([
                    ...$this->payload($student, $input),
                    'exam_session_id' => $session->id,
                    'personal_computer_status' => $this->personalComputerStatus($input),
                ]);
            } catch (QueryException) {
                throw ValidationException::withMessages([
                    'ioe_id' => 'ID IOE hoặc CCCD/mã định danh đã được đăng ký trong kỳ thi này.',
                ]);
            }

            $this->syncStudentContact($student, $input);
            $this->availability->refreshFullStatus($session->refresh());
            if ($oldSession && $oldSession->isNot($session)) {
                $this->availability->refreshFullStatus($oldSession->refresh());
            }

            return $registration->refresh();
        });
    }

    private function lockedSession(int|string|null $sessionId): ExamSession
    {
        if (! $sessionId) {
            throw ValidationException::withMessages(['exam_session_id' => 'Vui lòng chọn ca thi.']);
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
