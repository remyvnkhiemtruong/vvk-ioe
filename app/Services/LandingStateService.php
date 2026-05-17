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
                'phase' => 'awaiting_new_year',
                'headline' => 'Đang chờ kế hoạch IOE năm học mới.',
                'countdown_label' => 'Chưa mở kỳ thi',
                'target_at' => null,
                'button_active' => false,
                'button_label' => 'Chưa mở đăng ký',
                'button_reason' => 'Các kỳ thi 2025-2026 đã lưu lịch sử; nhà trường chưa mở kỳ thi IOE 2026-2027 trên landing page.',
                'status_label' => 'Chờ thông báo',
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
                'open' => 'Đang mở đăng ký',
                'closed_before_exam' => 'Đã đóng đăng ký, chờ ngày thi',
                'running' => 'Kỳ thi đang diễn ra',
                'score_entering' => 'Đang nhập điểm sau thi',
                default => 'Đang chuẩn bị kỳ thi',
            },
            'countdown_label' => match ($phase) {
                'before_open' => 'Đăng ký sẽ mở sau',
                'open' => 'Thời gian đăng ký còn lại',
                'closed_before_exam' => 'Kỳ thi sẽ diễn ra sau',
                'running' => 'Kỳ thi đang diễn ra',
                default => 'Chưa cấu hình',
            },
            'target_at' => $target,
            'button_active' => $phase === 'open' && (! $exam->requiresSessionChoice() || $hasCapacity),
            'button_label' => match (true) {
                $phase === 'before_open' => 'Đăng ký chưa mở',
                $phase === 'open' && $exam->requiresSessionChoice() && ! $hasCapacity => 'Chưa có ca còn chỗ',
                $phase === 'open' => 'Đăng ký dự thi',
                $phase === 'closed_before_exam' => 'Đăng ký đã đóng',
                $phase === 'running' => 'Kỳ thi đang diễn ra',
                default => 'Đang chuẩn bị',
            },
            'button_reason' => match (true) {
                $phase === 'before_open' => 'Kỳ đăng ký sẽ mở từ '.($exam->registration_opens_at?->format('d/m/Y H:i') ?? 'thời điểm nhà trường công bố').'.',
                $phase === 'open' && $exam->requiresSessionChoice() && ! $hasCapacity => 'Hiện chưa có ca thi phù hợp còn chỗ.',
                $phase === 'closed_before_exam' => 'Kỳ đăng ký đã kết thúc vào '.($exam->registration_closes_at?->format('d/m/Y H:i') ?? 'thời điểm đã cấu hình').'.',
                $phase === 'running' => 'Kỳ thi đang diễn ra; học sinh theo hướng dẫn của giám thị.',
                $phase === 'score_entering' => 'Nhà trường đang nhập điểm và xếp giải sau thi.',
                default => 'Nhà trường đang chuẩn bị kỳ thi IOE năm học mới.',
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
        if (in_array($exam->status, ['running', 'in_progress'], true)) {
            return 'running';
        }

        if ($exam->status === 'score_entering') {
            return 'score_entering';
        }

        if ($exam->registration_opens_at && $now->lt($exam->registration_opens_at)) {
            return 'before_open';
        }

        if ($exam->isRegistrationOpen()) {
            return 'open';
        }

        if ($examDateTime && $now->lt($examDateTime)) {
            return 'closed_before_exam';
        }

        return 'preparing';
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
            'preparing' => 'Đang chuẩn bị',
            'student_list_ready' => 'Sẵn sàng danh sách',
            'live_ready' => 'Sẵn sàng live',
            'open' => 'Đang mở đăng ký',
            'closed' => 'Đã đóng đăng ký',
            'assigning' => 'Đang phân ca',
            'locked' => 'Đã khóa danh sách',
            'running' => 'Đang thi',
            'in_progress' => 'Đang thi',
            'score_entering' => 'Đang nhập điểm',
            'ranked' => 'Đã xếp giải',
            'finished' => 'Đã kết thúc',
            'completed' => 'Hoàn thành',
            'archived' => 'Lưu trữ',
        ][$exam->status] ?? 'Chưa cấu hình';
    }
}
