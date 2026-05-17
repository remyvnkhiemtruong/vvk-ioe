<?php

namespace App\Services;

use App\Models\ExamRegistration;
use App\Models\ExamRoom;
use App\Models\ExamSession;
use App\Models\Incident;
use App\Models\RoomComputer;
use App\Models\SeatAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SeatAssignmentService
{
    public function __construct(private readonly ExamSessionAvailabilityService $availability) {}

    /**
     * @param  Collection<int, ExamRegistration>  $registrations
     */
    public function assign(ExamSession $session, ExamRoom $room, Collection $registrations, string $method, User $actor): int
    {
        $this->validateAssignmentInput($session, $room, $registrations);

        return DB::transaction(function () use ($session, $room, $registrations, $method, $actor) {
            $session = ExamSession::whereKey($session->id)->lockForUpdate()->firstOrFail();

            $usedComputerIds = SeatAssignment::where('exam_session_id', $session->id)
                ->where('exam_room_id', $room->id)
                ->whereNotNull('computer_id')
                ->pluck('computer_id');

            $computers = $room->computers()
                ->where('type', 'main')
                ->where('status', 'ready')
                ->whereNotIn('id', $usedComputerIds)
                ->orderBy('computer_number')
                ->get()
                ->values();

            $assigned = 0;
            $candidateNumber = (int) SeatAssignment::where('exam_session_id', $session->id)
                ->where('exam_room_id', $room->id)
                ->max('candidate_number');

            foreach ($this->sortRegistrations($registrations, $method) as $registration) {
                $candidateNumber++;
                $usesByod = $registration->uses_personal_computer && $registration->personal_computer_status === 'approved';
                $computer = $usesByod ? null : $computers->shift();

                if (! $usesByod && ! $computer) {
                    throw ValidationException::withMessages([
                        'capacity' => 'Không đủ máy chính sẵn sàng cho danh sách thí sinh đã chọn.',
                    ]);
                }

                try {
                    SeatAssignment::create([
                        'exam_registration_id' => $registration->id,
                        'exam_session_id' => $session->id,
                        'exam_room_id' => $room->id,
                        'seat_type' => $usesByod ? 'personal_computer' : 'school_computer',
                        'computer_id' => $computer?->id,
                        'computer_number' => $computer?->computer_number,
                        'candidate_number' => $candidateNumber,
                        'assignment_method' => $method,
                        'assigned_by' => $actor->id,
                    ]);

                    if (! $registration->exam_session_id) {
                        $registration->update(['exam_session_id' => $session->id]);
                    }
                } catch (QueryException) {
                    throw ValidationException::withMessages([
                        'assignment' => 'Không thể phân phòng vì có thí sinh, số báo danh hoặc máy đã được gán trong ca/phòng này.',
                    ]);
                }

                $assigned++;
            }

            return $assigned;
        });
    }

    public function moveToBackup(SeatAssignment $assignment, RoomComputer $newComputer, string $reason, User $actor): SeatAssignment
    {
        if ($newComputer->exam_room_id !== $assignment->exam_room_id || $newComputer->type !== 'backup') {
            throw ValidationException::withMessages(['new_computer_id' => 'Máy mới phải là máy dự phòng trong cùng phòng thi.']);
        }

        if ($newComputer->status !== 'ready') {
            throw ValidationException::withMessages(['new_computer_id' => 'Máy dự phòng đã chọn chưa sẵn sàng.']);
        }

        if (SeatAssignment::where('exam_session_id', $assignment->exam_session_id)->where('exam_room_id', $assignment->exam_room_id)->where('computer_id', $newComputer->id)->whereKeyNot($assignment->id)->exists()) {
            throw ValidationException::withMessages(['new_computer_id' => 'Máy dự phòng đã được gán cho thí sinh khác trong ca/phòng này.']);
        }

        return DB::transaction(function () use ($assignment, $newComputer, $reason, $actor) {
            $oldComputer = $assignment->computer_id;

            $assignment->update([
                'seat_type' => 'backup_computer',
                'backup_computer_id' => $newComputer->id,
                'computer_id' => $newComputer->id,
                'computer_number' => $newComputer->computer_number,
            ]);

            $newComputer->update(['status' => 'in_use']);

            Incident::create([
                'seat_assignment_id' => $assignment->id,
                'exam_registration_id' => $assignment->exam_registration_id,
                'incident_type' => 'Chuyển máy',
                'description' => $reason,
                'solution' => 'Chuyển sang '.$newComputer->computer_label,
                'old_computer_id' => $oldComputer,
                'new_computer_id' => $newComputer->id,
                'reported_by' => $actor->id,
                'reported_at' => now(),
            ]);

            return $assignment->refresh();
        });
    }

    private function validateAssignmentInput(ExamSession $session, ExamRoom $room, Collection $registrations): void
    {
        if ($session->exam_room_id && $session->exam_room_id !== $room->id) {
            throw ValidationException::withMessages(['exam_room_id' => 'Phòng thi không khớp với phòng đã cấu hình cho ca thi.']);
        }

        $existingCount = SeatAssignment::where('exam_session_id', $session->id)
            ->where('exam_room_id', $room->id)
            ->count();

        if ($existingCount + $registrations->count() > $session->max_candidates) {
            throw ValidationException::withMessages(['max_candidates' => 'Số thí sinh vượt quá số lượng tối đa của ca thi.']);
        }

        foreach ($registrations as $registration) {
            if ((int) $registration->exam_id !== (int) $session->exam_id) {
                throw ValidationException::withMessages(['registration_ids' => 'Danh sách thí sinh phải thuộc cùng kỳ thi với ca thi.']);
            }

            if ($registration->exam_session_id && (int) $registration->exam_session_id !== (int) $session->id) {
                throw ValidationException::withMessages(['registration_ids' => 'Không được tự động chuyển học sinh sang ca khác với ca đã chọn.']);
            }

            if (! $this->availability->isExamTargetForStudent($registration->exam, $registration)
                || ! $this->availability->isTargetForStudent($session, $registration)) {
                throw ValidationException::withMessages(['registration_ids' => 'Ca thi được chọn không phù hợp với khối/lớp của học sinh.']);
            }

            if ($registration->status !== 'approved') {
                throw ValidationException::withMessages(['registration_ids' => 'Chỉ được phân phòng cho đăng ký đã duyệt.']);
            }

            if ($registration->seatAssignment()->exists()) {
                throw ValidationException::withMessages(['registration_ids' => 'Có thí sinh trong danh sách đã được phân phòng.']);
            }

            if ($registration->uses_personal_computer && $registration->personal_computer_status !== 'approved') {
                throw ValidationException::withMessages(['registration_ids' => 'Có học sinh dùng máy cá nhân chưa được duyệt, cần duyệt hoặc từ chối trước khi phân phòng.']);
            }
        }
    }

    private function sortRegistrations(Collection $registrations, string $method): Collection
    {
        return match ($method) {
            'class' => $registrations->sortBy(['class_name', 'full_name'])->values(),
            'name' => $registrations->sortBy('full_name')->values(),
            'registered_at' => $registrations->sortBy('registered_at')->values(),
            'random' => $registrations->shuffle()->values(),
            default => $registrations->sortBy(['class_name', 'full_name'])->values(),
        };
    }
}
