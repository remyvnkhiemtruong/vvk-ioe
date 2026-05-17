<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExamRegistrationRequest;
use App\Models\Exam;
use App\Models\ExamRegistration;
use App\Services\ExamRegistrationService;
use App\Services\ExamSessionAvailabilityService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    public function dashboard(ExamSessionAvailabilityService $availability): View
    {
        $student = auth()->user()->student;
        $exam = Exam::where('level', 'school')->latest()->first();
        $registration = $student && $exam
            ? $student->registrations()
                ->where('exam_id', $exam->id)
                ->with(['exam', 'chosenSession.room', 'seatAssignment.session', 'seatAssignment.room', 'seatAssignment.computer', 'seatAssignment.checkin', 'score'])
                ->latest()
                ->first()
            : null;

        $availableSessions = $student && $exam && $exam->isRegistrationOpen() && ! $registration && $exam->requiresSessionChoice()
            ? $availability->availableForStudent($student, $exam)
            : collect();

        return view('student.dashboard', [
            'student' => $student,
            'exam' => $exam,
            'registration' => $registration,
            'availableSessions' => $availableSessions,
            'registrationBlockReason' => $this->registrationBlockReason($student, $exam, $registration, $availableSessions->count()),
        ]);
    }

    public function create(Exam $exam, ExamSessionAvailabilityService $availability): View
    {
        $this->ensureSchoolOpen($exam);
        $student = auth()->user()->student;

        return view('student.registrations.form', [
            'exam' => $exam,
            'student' => $student,
            'registration' => null,
            'availableSessions' => $exam->requiresSessionChoice() ? $availability->availableForStudent($student, $exam) : collect(),
        ]);
    }

    public function store(StoreExamRegistrationRequest $request, Exam $exam, ExamRegistrationService $registrations): RedirectResponse
    {
        $this->ensureSchoolOpen($exam);

        $registration = $registrations->create($request->user()->student, $exam, $request->validated());

        return redirect()
            ->route('student.registrations.show', $registration)
            ->with('status', 'Đăng ký dự thi IOE cấp trường thành công.');
    }

    public function show(ExamRegistration $registration): View
    {
        $this->authorizeOwner($registration);

        return view('student.registrations.show', [
            'registration' => $registration->load(['exam', 'chosenSession.room', 'seatAssignment.session', 'seatAssignment.room', 'seatAssignment.computer', 'seatAssignment.checkin', 'score']),
        ]);
    }

    public function edit(ExamRegistration $registration, ExamSessionAvailabilityService $availability): View
    {
        $this->authorizeOwner($registration);

        if (! $registration->exam->allow_student_edit || ! $registration->exam->isRegistrationOpen()) {
            abort(403, 'Đã hết hạn chỉnh sửa đăng ký.');
        }

        return view('student.registrations.form', [
            'exam' => $registration->exam,
            'student' => auth()->user()->student,
            'registration' => $registration,
            'availableSessions' => $availability->availableForStudent(auth()->user()->student, $registration->exam, $registration),
        ]);
    }

    public function update(StoreExamRegistrationRequest $request, ExamRegistration $registration, ExamRegistrationService $registrations): RedirectResponse
    {
        $this->authorizeOwner($registration);

        if (! $registration->exam->allow_student_edit || ! $registration->exam->isRegistrationOpen()) {
            abort(403, 'Đã hết hạn chỉnh sửa đăng ký.');
        }

        $registrations->update($registration, $request->validated());

        return redirect()->route('student.registrations.show', $registration)->with('status', 'Đã cập nhật đăng ký.');
    }

    public function ticket(ExamRegistration $registration): Response
    {
        $this->authorizeOwner($registration);
        $registration->load(['exam', 'chosenSession.room', 'seatAssignment.session', 'seatAssignment.room', 'seatAssignment.computer']);

        return Pdf::loadView('exports.ticket', compact('registration'))->download('phieu-du-thi-'.$registration->registration_code.'.pdf');
    }

    private function ensureSchoolOpen(Exam $exam): void
    {
        if (! in_array($exam->level, ['school', 'truong'], true) || ! $exam->isRegistrationOpen()) {
            abort(403, 'Kỳ đăng ký cấp trường chưa mở hoặc đã đóng.');
        }
    }

    private function authorizeOwner(ExamRegistration $registration): void
    {
        abort_unless(auth()->user()->student_id === $registration->student_id, 403);
    }

    private function registrationBlockReason($student, ?Exam $exam, ?ExamRegistration $registration, int $availableSessionCount): ?string
    {
        if (! $student) {
            return 'Tài khoản chưa liên kết hồ sơ học sinh.';
        }

        if ($student->status !== 'active') {
            return 'Tài khoản của bạn đang bị khóa, vui lòng liên hệ giáo viên phụ trách.';
        }

        if ($registration) {
            return null;
        }

        if (! $exam) {
            return 'Hiện chưa có kỳ đăng ký IOE cấp trường nào đang mở.';
        }

        if ($exam->registration_opens_at && now()->lt($exam->registration_opens_at)) {
            return 'Kỳ đăng ký sẽ mở từ: '.$exam->registration_opens_at->format('d/m/Y H:i');
        }

        if ($exam->registration_closes_at && now()->gt($exam->registration_closes_at)) {
            return 'Kỳ đăng ký đã kết thúc vào: '.$exam->registration_closes_at->format('d/m/Y H:i');
        }

        if (! $exam->isRegistrationOpen()) {
            return 'Hiện chưa có kỳ đăng ký IOE cấp trường nào đang mở.';
        }

        $grades = collect($exam->target_grades ?? [10, 11, 12])->map(fn ($grade) => (int) $grade);
        if ($grades->isNotEmpty() && ! $grades->contains($student->resolvedGrade())) {
            return 'Lớp/khối của bạn không thuộc đối tượng đăng ký kỳ thi này.';
        }

        if ($exam->requiresSessionChoice() && $availableSessionCount === 0) {
            return 'Hiện chưa có ca thi phù hợp hoặc còn chỗ cho khối/lớp của bạn. Vui lòng liên hệ giáo viên phụ trách.';
        }

        return null;
    }
}
