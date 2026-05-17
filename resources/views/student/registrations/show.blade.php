<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold">Chi tiết đăng ký</h1>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
        @if(session('status'))<div class="rounded bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
        @php($session = $registration->chosenSession ?: $registration->seatAssignment?->session)
        <div class="rounded-lg border border-slate-200 bg-white p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold">{{ $registration->full_name }}</h2>
                    <p class="text-sm text-slate-600">{{ $registration->class_name }} · ID IOE {{ $registration->ioe_id }}</p>
                </div>
                <x-status-badge :status="$registration->status" />
            </div>
            <dl class="mt-6 grid gap-4 text-sm md:grid-cols-3">
                <div><dt class="text-slate-500">Mã đăng ký</dt><dd class="font-medium">{{ $registration->registration_code }}</dd></div>
                <div><dt class="text-slate-500">CCCD/Mã định danh</dt><dd class="font-medium">{{ $registration->maskedIdentity() }}</dd></div>
                <div><dt class="text-slate-500">Máy cá nhân</dt><dd class="font-medium"><x-status-badge :status="$registration->personal_computer_status" /></dd></div>
                <div><dt class="text-slate-500">Ca thi đã chọn</dt><dd class="font-medium">{{ $session?->name ?? 'Chưa chọn ca' }}</dd></div>
                <div><dt class="text-slate-500">Ngày thi</dt><dd class="font-medium">{{ $session?->exam_date?->format('d/m/Y') ?? 'Chưa có ngày thi' }}</dd></div>
                <div><dt class="text-slate-500">Giờ thi</dt><dd class="font-medium">{{ $session ? $session->start_time.'-'.$session->end_time : 'Chưa cấu hình' }}</dd></div>
                <div><dt class="text-slate-500">Phòng thi</dt><dd class="font-medium">{{ $registration->seatAssignment?->room?->room_name ?? $session?->room?->room_name ?? 'Chưa phân phòng' }}</dd></div>
                <div><dt class="text-slate-500">Số máy</dt><dd class="font-medium">{{ $registration->seatAssignment?->seat_type === 'personal_computer' ? 'Máy cá nhân' : ($registration->seatAssignment?->computer?->computer_label ?? 'Chưa phân máy') }}</dd></div>
                <div><dt class="text-slate-500">Số báo danh</dt><dd class="font-medium">{{ $registration->seatAssignment?->candidate_number ?? 'Chưa có' }}</dd></div>
                <div><dt class="text-slate-500">Check-in</dt><dd><x-status-badge :status="$registration->seatAssignment?->checkin?->status ?? 'not_checked_in'" /></dd></div>
                @if($registration->exam->publish_scores && $registration->score)
                    <div><dt class="text-slate-500">Điểm thi</dt><dd class="font-medium">{{ $registration->score->official_score }}</dd></div>
                @else
                    <div><dt class="text-slate-500">Kết quả</dt><dd class="font-medium">Kết quả thi chưa được công bố.</dd></div>
                @endif
            </dl>
            <div class="mt-6 flex flex-wrap gap-2">
                @if($registration->exam->isRegistrationOpen() && $registration->exam->allow_student_edit)
                    <a href="{{ route('student.registrations.edit', $registration) }}" class="rounded border border-slate-300 px-4 py-2 text-sm font-semibold">Cập nhật đăng ký</a>
                @endif
                @if($registration->seatAssignment)
                    <a href="{{ route('student.registrations.ticket', $registration) }}" class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Tải phiếu dự thi PDF</a>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
