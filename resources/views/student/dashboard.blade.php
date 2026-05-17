<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold">Trang học sinh</h1>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        @if(session('status'))
            <div class="rounded border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm motion-safe:animate-[slideUp_.45s_ease-out]">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold">Thông tin cá nhân</h2>
                    <p class="mt-1 text-sm text-slate-600">Các thông tin học vụ cố định cần liên hệ giáo viên phụ trách để điều chỉnh.</p>
                </div>
                <a href="{{ route('student.profile.edit') }}" class="rounded border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cập nhật thông tin cá nhân</a>
            </div>
            <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <div><dt class="text-slate-500">Họ và tên</dt><dd class="font-medium">{{ $student?->full_name ?? 'Chưa cập nhật' }}</dd></div>
                <div><dt class="text-slate-500">Mã học sinh</dt><dd class="font-medium">{{ $student?->student_code ?? 'Chưa cập nhật' }}</dd></div>
                <div><dt class="text-slate-500">Lớp</dt><dd class="font-medium">{{ $student?->class_name ?? 'Chưa cập nhật' }}</dd></div>
                <div><dt class="text-slate-500">Khối</dt><dd class="font-medium">{{ $student?->resolvedGrade() ?? 'Chưa cập nhật' }}</dd></div>
                <div><dt class="text-slate-500">Ngày sinh</dt><dd class="font-medium">{{ $student?->date_of_birth?->format('d/m/Y') ?? 'Chưa cập nhật' }}</dd></div>
                <div><dt class="text-slate-500">Giới tính</dt><dd class="font-medium">{{ $student?->gender ?? 'Chưa cập nhật' }}</dd></div>
                <div><dt class="text-slate-500">CCCD/Mã định danh</dt><dd class="font-medium">{{ $student?->maskedIdentity() ?: 'Chưa cập nhật' }}</dd></div>
                <div><dt class="text-slate-500">Trạng thái tài khoản</dt><dd><x-status-badge :status="$student?->status ?? 'inactive'" /></dd></div>
                <div><dt class="text-slate-500">Số điện thoại</dt><dd class="font-medium">{{ $student?->phone ?? 'Chưa cập nhật' }}</dd></div>
                <div><dt class="text-slate-500">Email</dt><dd class="font-medium">{{ $student?->email ?? 'Chưa cập nhật' }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-slate-500">Địa chỉ</dt><dd class="font-medium">{{ $student?->address ?? 'Chưa cập nhật' }}</dd></div>
            </dl>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm motion-safe:animate-[slideUp_.55s_ease-out]">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold">Đăng ký IOE cấp trường</h2>
                    <p class="text-sm text-slate-600">{{ $exam->name ?? 'Chưa có kỳ đăng ký' }}</p>
                </div>
                @if($exam)
                    <x-status-badge :status="$exam->status" />
                @endif
            </div>

            @if($registration)
                @php($session = $registration->chosenSession ?: $registration->seatAssignment?->session)
                <dl class="mt-5 grid gap-4 text-sm md:grid-cols-4">
                    <div><dt class="text-slate-500">Mã đăng ký</dt><dd class="font-medium">{{ $registration->registration_code }}</dd></div>
                    <div><dt class="text-slate-500">ID IOE</dt><dd class="font-medium">{{ $registration->ioe_id }}</dd></div>
                    <div><dt class="text-slate-500">Trạng thái</dt><dd><x-status-badge :status="$registration->status" /></dd></div>
                    <div><dt class="text-slate-500">Máy cá nhân</dt><dd><x-status-badge :status="$registration->personal_computer_status" /></dd></div>
                    <div><dt class="text-slate-500">Ca thi đã chọn</dt><dd class="font-medium">{{ $session?->name ?? 'Chưa chọn ca' }}</dd></div>
                    <div><dt class="text-slate-500">Ngày giờ thi</dt><dd class="font-medium">{{ $session?->exam_date?->format('d/m/Y') ?? 'Chưa có ngày thi' }} {{ $session?->start_time }}</dd></div>
                    <div><dt class="text-slate-500">Phòng thi</dt><dd class="font-medium">{{ $registration->seatAssignment?->room?->room_name ?? $session?->room?->room_name ?? 'Chưa phân phòng' }}</dd></div>
                    <div><dt class="text-slate-500">Số máy/SBD</dt><dd class="font-medium">{{ $registration->seatAssignment?->seat_type === 'personal_computer' ? 'Máy cá nhân' : ($registration->seatAssignment?->computer?->computer_label ?? 'Chưa phân máy') }} / {{ $registration->seatAssignment?->candidate_number ?? 'Chưa có' }}</dd></div>
                    <div><dt class="text-slate-500">Check-in</dt><dd><x-status-badge :status="$registration->seatAssignment?->checkin?->status ?? 'not_checked_in'" /></dd></div>
                    <div><dt class="text-slate-500">Điểm thi</dt><dd class="font-medium">{{ $registration->exam->publish_scores && $registration->score ? $registration->score->official_score : 'Chưa công bố điểm' }}</dd></div>
                </dl>
                <div class="mt-5 flex flex-wrap gap-2">
                    <a href="{{ route('student.registrations.show', $registration) }}" class="rounded border border-slate-300 px-4 py-2 text-sm font-semibold">Xem đăng ký</a>
                    @if($registration->exam->isRegistrationOpen() && $registration->exam->allow_student_edit)
                        <a href="{{ route('student.registrations.edit', $registration) }}" class="rounded border border-emerald-700 px-4 py-2 text-sm font-semibold text-emerald-800">Cập nhật đăng ký</a>
                    @endif
                    @if($registration->seatAssignment)
                        <a href="{{ route('student.registrations.ticket', $registration) }}" class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Tải phiếu dự thi</a>
                    @endif
                    @if($registration->exam->publish_scores && $registration->score)
                        <a href="{{ route('student.registrations.show', $registration) }}" class="rounded border border-slate-300 px-4 py-2 text-sm font-semibold">Xem kết quả</a>
                    @endif
                </div>
            @else
                <div class="mt-5 rounded border border-slate-200 bg-slate-50 p-4">
                    <p class="font-medium text-slate-800">Bạn chưa đăng ký dự thi IOE cấp trường.</p>
                    <p class="mt-1 text-sm text-slate-600">{{ $registrationBlockReason ?? 'Nếu kỳ đăng ký đang mở, hãy bấm nút bên dưới để đăng ký.' }}</p>
                    @if($exam?->isRegistrationOpen() && ! $registrationBlockReason)
                        <a href="{{ route('student.registrations.create', $exam) }}" class="mt-4 inline-flex rounded bg-emerald-700 px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-800">Đăng ký dự thi IOE cấp trường</a>
                    @endif
                </div>
            @endif
        </section>

        @if(! $registration && $availableSessions->isNotEmpty())
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm motion-safe:animate-[slideUp_.65s_ease-out]">
                <h2 class="text-lg font-semibold">Ca thi khả dụng cho lớp {{ $student->class_name }}</h2>
                <div class="mt-4 grid gap-3 md:grid-cols-3">
                    @foreach($availableSessions->take(6) as $session)
                        <article class="rounded border border-slate-200 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-semibold">{{ $session->name }}</h3>
                                    <p class="mt-1 text-sm text-slate-600">{{ $session->exam_date?->format('d/m/Y') }} · {{ $session->start_time }}-{{ $session->end_time }}</p>
                                    <p class="text-sm text-slate-600">{{ $session->room?->room_name ?? 'Chưa cấu hình phòng' }} · {{ $session->targetLabel() }}</p>
                                </div>
                                <x-status-badge :status="$session->status" />
                            </div>
                            <div class="mt-3 text-sm font-medium text-slate-700">Còn {{ $session->remaining_slots }}/{{ $session->max_candidates }} chỗ</div>
                            <a href="{{ route('student.registrations.create', $exam) }}" class="mt-3 inline-flex rounded border border-emerald-700 px-3 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-50">Đăng ký ngay</a>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-app-layout>
