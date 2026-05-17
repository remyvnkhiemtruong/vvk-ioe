<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamRegistration;
use App\Models\ExamSession;
use Illuminate\Support\Carbon;

class LandingStateService
{
    public function __construct(private readonly ExamSessionAvailabilityService $availability) {}

    public function state(?Exam $exam): array
    {
        if (! $exam) {
            return [
                'phase' => 'not_configured',
                'headline' => 'Nhà trường chưa mở đăng ký.',
                'countdown_label' => 'Chưa cấu hình',
                'target_at' => null,
                'button_active' => false,
                'button_label' => 'Chưa mở đăng ký',
                'button_reason' => 'Chưa có kỳ đăng ký IOE cấp trường.',
                'status_label' => 'Chưa cấu hình',
            ];
        }

        $now = now();
        $examDateTime = $exam->examDateTime();
        $phase = $this->phase($exam, $now, $examDateTime);
        $target = $this->target($exam, $phase, $examDateTime);
        $sessions = $exam->sessions()->with('room')->get();
        $openSessions = $sessions->filter(fn (ExamSession $session) => $session->status === 'open');
        $hasCapacity = $openSessions->contains(fn (ExamSession $session) => $this->availability->remainingSlots($session) > 0);

        return [
            'phase' => $phase,
            'headline' => match ($phase) {
                'before_open' => 'Đăng ký sẽ mở sau',
                'open' => 'Thời gian đăng ký còn lại',
                'closed_before_exam' => 'Kỳ thi sẽ diễn ra sau',
                'after_exam' => 'Kỳ thi đã diễn ra',
                default => 'Nhà trường chưa mở đăng ký',
            },
            'countdown_label' => match ($phase) {
                'before_open' => 'Đăng ký sẽ mở sau',
                'open' => 'Thời gian đăng ký còn lại',
                'closed_before_exam' => 'Kỳ thi sẽ diễn ra sau',
                'after_exam' => 'Kỳ thi đã diễn ra',
                default => 'Chưa cấu hình',
            },
            'target_at' => $target,
            'button_active' => $phase === 'open' && $hasCapacity,
            'button_label' => match (true) {
                $phase === 'before_open' => 'Đăng ký chưa mở',
                $phase === 'open' && ! $hasCapacity => 'Chưa có ca còn chỗ',
                $phase === 'open' => 'Đăng ký dự thi',
                $phase === 'closed_before_exam' => 'Đăng ký đã đóng',
                $phase === 'after_exam' && $exam->publish_scores => 'Xem kết quả sau đăng nhập',
                default => 'Đăng ký chưa mở',
            },
            'button_reason' => match (true) {
                $phase === 'before_open' => 'Kỳ đăng ký sẽ mở từ '.($exam->registration_opens_at?->format('d/m/Y H:i') ?? 'Chưa cấu hình').'.',
                $phase === 'open' && ! $hasCapacity => 'Hiện chưa có ca thi đang mở và còn chỗ.',
                $phase === 'closed_before_exam' => 'Kỳ đăng ký đã kết thúc vào '.($exam->registration_closes_at?->format('d/m/Y H:i') ?? 'Chưa cấu hình').'.',
                $phase === 'after_exam' => 'Kỳ thi đã qua ngày dự kiến.',
                default => 'Nhà trường chưa mở đăng ký.',
            },
            'status_label' => $this->examStatusLabel($exam),
        ];
    }

    public function stats(?Exam $exam): array
    {
        if (! $exam) {
            return [
                'registrations' => 0,
                'classes' => 0,
                'sessions' => 0,
                'sessions_open' => 0,
                'sessions_full' => 0,
                'sessions_locked' => 0,
            ];
        }

        $sessions = $exam->sessions()->get();

        return [
            'registrations' => ExamRegistration::where('exam_id', $exam->id)
                ->whereIn('status', ExamSessionAvailabilityService::VALID_REGISTRATION_STATUSES)
                ->count(),
            'classes' => ExamRegistration::where('exam_id', $exam->id)
                ->whereIn('status', ExamSessionAvailabilityService::VALID_REGISTRATION_STATUSES)
                ->distinct('class_name')
                ->count('class_name'),
            'sessions' => $sessions->count(),
            'sessions_open' => $sessions->filter(fn (ExamSession $session) => $session->status === 'open' && $this->availability->remainingSlots($session) > 0)->count(),
            'sessions_full' => $sessions->filter(fn (ExamSession $session) => $this->availability->remainingSlots($session) <= 0 || $session->status === 'full')->count(),
            'sessions_locked' => $sessions->where('status', 'locked')->count(),
        ];
    }

    public function availableSessionsForGuest(?Exam $exam): array
    {
        if (! $exam) {
            return [];
        }

        return $exam->sessions()
            ->with('room')
            ->orderBy('exam_date')
            ->orderBy('start_time')
            ->get()
            ->map(fn (ExamSession $session) => [
                'name' => $session->name,
                'date' => $session->exam_date?->format('d/m/Y'),
                'time' => $session->start_time.'-'.$session->end_time,
                'room' => $session->room?->room_name ?? 'Chưa cấu hình',
                'target' => $session->targetLabel(),
                'remaining' => $this->availability->remainingSlots($session),
                'max' => $session->max_candidates,
                'status' => $session->status,
            ])
            ->all();
    }

    private function phase(Exam $exam, Carbon $now, ?Carbon $examDateTime): string
    {
        if ($exam->registration_opens_at && $now->lt($exam->registration_opens_at)) {
            return 'before_open';
        }

        if ($exam->isRegistrationOpen()) {
            return 'open';
        }

        if ($examDateTime && $now->lt($examDateTime)) {
            return 'closed_before_exam';
        }

        if ($examDateTime && $now->gte($examDateTime)) {
            return 'after_exam';
        }

        return $exam->status === 'open' ? 'open' : 'not_configured';
    }

    private function target(Exam $exam, string $phase, ?Carbon $examDateTime): ?Carbon
    {
        if (! $exam->show_countdown) {
            return null;
        }

        return match ($exam->countdown_mode ?: 'auto') {
            'open' => $exam->registration_opens_at,
            'close' => $exam->registration_closes_at,
            'exam' => $examDateTime,
            default => match ($phase) {
                'before_open' => $exam->registration_opens_at,
                'open' => $exam->registration_closes_at,
                'closed_before_exam' => $examDateTime,
                default => null,
            },
        };
    }

    private function examStatusLabel(Exam $exam): string
    {
        return [
            'draft' => 'Nháp',
            'open' => 'Đang mở đăng ký',
            'closed' => 'Đã đóng đăng ký',
            'assigning' => 'Đang phân phòng',
            'locked' => 'Đã khóa danh sách',
            'in_progress' => 'Đang thi',
            'completed' => 'Đã hoàn thành',
        ][$exam->status] ?? 'Chưa cấu hình';
    }
}
