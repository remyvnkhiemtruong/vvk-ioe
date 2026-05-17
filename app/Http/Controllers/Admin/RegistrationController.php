<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamRegistration;
use App\Models\ExamSession;
use App\Services\ExamSessionAvailabilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    public function index(Request $request): View
    {
        $registrations = ExamRegistration::with(['student', 'exam', 'chosenSession.room', 'seatAssignment.session', 'seatAssignment.room'])
            ->when($request->filled('q'), fn ($q) => $q->where(function ($search) use ($request) {
                $search->where('full_name', 'like', '%'.$request->q.'%')
                    ->orWhere('ioe_id', 'like', '%'.$request->q.'%')
                    ->orWhere('registration_code', 'like', '%'.$request->q.'%');
            }))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('class_name'), fn ($q) => $q->where('class_name', $request->class_name))
            ->when($request->filled('session_status') && $request->session_status === 'missing', fn ($q) => $q->whereNull('exam_session_id'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.registrations.index', compact('registrations'));
    }

    public function approve(ExamRegistration $registration, ExamSessionAvailabilityService $availability): RedirectResponse
    {
        $this->makeValid($registration, $availability, 'approved', [
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return back()->with('status', 'Đã duyệt đăng ký.');
    }

    public function reject(ExamRegistration $registration, ExamSessionAvailabilityService $availability): RedirectResponse
    {
        $session = $registration->chosenSession;
        $registration->update(['status' => 'rejected']);
        if ($session) {
            $availability->refreshFullStatus($session->refresh());
        }

        return back()->with('status', 'Đã từ chối đăng ký và giải phóng chỗ.');
    }

    public function cancel(ExamRegistration $registration, ExamSessionAvailabilityService $availability): RedirectResponse
    {
        $session = $registration->chosenSession;
        $registration->update(['status' => 'cancelled']);
        if ($session) {
            $availability->refreshFullStatus($session->refresh());
        }

        return back()->with('status', 'Đã hủy đăng ký và giải phóng chỗ.');
    }

    public function restore(ExamRegistration $registration, ExamSessionAvailabilityService $availability): RedirectResponse
    {
        $status = $registration->exam->require_approval ? 'submitted' : 'approved';
        $this->makeValid($registration, $availability, $status);

        return back()->with('status', 'Đã khôi phục đăng ký.');
    }

    public function device(Request $request, ExamRegistration $registration): RedirectResponse
    {
        $request->validate(['personal_computer_status' => ['required', 'in:pending,approved,rejected,need_check,not_applicable']]);
        $registration->update(['personal_computer_status' => $request->personal_computer_status]);

        return back()->with('status', 'Đã cập nhật trạng thái máy cá nhân.');
    }

    private function assertCanBecomeValid(ExamRegistration $registration, ExamSessionAvailabilityService $availability): void
    {
        if (! $registration->chosenSession) {
            throw ValidationException::withMessages(['registration' => 'Đăng ký này chưa chọn ca thi. Vui lòng gán ca trước khi duyệt hoặc khôi phục.']);
        }

        $availability->assertAvailable(
            $registration->chosenSession,
            $registration,
            $registration->exam,
            $registration->id
        );
    }

    private function makeValid(ExamRegistration $registration, ExamSessionAvailabilityService $availability, string $status, array $extra = []): void
    {
        DB::transaction(function () use ($registration, $availability, $status, $extra): void {
            $lockedRegistration = ExamRegistration::query()
                ->with('exam')
                ->whereKey($registration->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedRegistration->exam_session_id) {
                throw ValidationException::withMessages(['registration' => 'Đăng ký này chưa chọn ca thi. Vui lòng gán ca trước khi duyệt hoặc khôi phục.']);
            }

            $session = ExamSession::query()
                ->whereKey($lockedRegistration->exam_session_id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedRegistration->setRelation('chosenSession', $session);
            $this->assertCanBecomeValid($lockedRegistration, $availability);

            $lockedRegistration->update([
                'status' => $status,
                ...$extra,
            ]);

            $availability->refreshFullStatus($session->refresh());
        });
    }
}
