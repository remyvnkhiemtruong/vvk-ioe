<?php

namespace App\Console\Commands;

use App\Models\ExamRegistration;
use App\Services\ExamSessionAvailabilityService;
use Illuminate\Console\Command;

class AuditSessionGradeCommand extends Command
{
    protected $signature = 'ioe:audit-session-grade {--exam_id= : Chỉ kiểm tra một kỳ thi}';

    protected $description = 'Rà soát học sinh được gán/chọn ca thi không phù hợp khối/lớp.';

    public function handle(ExamSessionAvailabilityService $availability): int
    {
        $registrations = ExamRegistration::with(['exam', 'chosenSession', 'seatAssignment.session'])
            ->when($this->option('exam_id'), fn ($query, $examId) => $query->where('exam_id', $examId))
            ->where(function ($query) {
                $query->whereNotNull('exam_session_id')
                    ->orWhereHas('seatAssignment');
            })
            ->get();

        $errors = [];

        foreach ($registrations as $registration) {
            $sessions = collect([$registration->chosenSession, $registration->seatAssignment?->session])->filter()->unique('id');

            foreach ($sessions as $session) {
                if (! $availability->isExamTargetForStudent($registration->exam, $registration)
                    || ! $availability->isTargetForStudent($session, $registration)) {
                    $errors[] = [
                        $registration->registration_code,
                        $registration->full_name,
                        $registration->class_name,
                        $session->name,
                        $session->targetLabel(),
                    ];
                }
            }
        }

        if ($errors === []) {
            $this->info('Không phát hiện học sinh chọn/gán ca sai khối/lớp.');

            return self::SUCCESS;
        }

        $this->error('Có '.count($errors).' học sinh đang được gán/chọn ca thi không phù hợp khối/lớp.');
        $this->table(['Mã đăng ký', 'Học sinh', 'Lớp', 'Ca thi', 'Đối tượng ca'], $errors);

        return self::FAILURE;
    }
}
