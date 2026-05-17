<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">Phân ca, phòng thi và số máy</h1></x-slot>
    <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
        @if(session('status'))<div class="rounded bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="rounded bg-rose-50 p-3 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
        @can('exports.manage')
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.exports.assignments.pdf') }}" class="rounded border border-slate-300 px-4 py-2 text-sm font-semibold">Xuất phòng thi PDF</a>
                <a href="{{ route('admin.exports.rooms') }}" class="rounded border border-slate-300 px-4 py-2 text-sm font-semibold">Xuất danh sách phòng Excel</a>
            </div>
        @endcan
        <form method="POST" action="{{ route('admin.assignments.store') }}" class="rounded-lg border border-slate-200 bg-white p-5">
            @csrf
            <div class="grid gap-3 md:grid-cols-4">
                <select name="exam_session_id" required class="rounded-md border-slate-300"><option value="">Chọn ca thi</option>@foreach($sessions as $session)<option value="{{ $session->id }}">{{ $session->name }} · {{ $session->targetLabel() }}</option>@endforeach</select>
                <select name="exam_room_id" required class="rounded-md border-slate-300"><option value="">Chọn phòng</option>@foreach($rooms as $room)<option value="{{ $room->id }}">{{ $room->room_name }}</option>@endforeach</select>
                <select name="method" class="rounded-md border-slate-300"><option value="class">Theo lớp</option><option value="name">Theo họ tên A-Z</option><option value="registered_at">Theo thứ tự đăng ký</option><option value="random">Ngẫu nhiên</option><option value="manual">Thủ công</option></select>
                <button class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Xác nhận phân phòng</button>
            </div>
            <div class="mt-4 max-h-80 overflow-auto rounded border border-slate-200">
                @forelse($registrations as $registration)
                    <label class="flex items-center gap-3 border-b border-slate-100 px-3 py-2 text-sm">
                        <input type="checkbox" name="registration_ids[]" value="{{ $registration->id }}">
                        <span class="font-medium">{{ $registration->full_name }}</span>
                        <span class="text-slate-500">{{ $registration->class_name }} · {{ $registration->chosenSession?->name ?? 'Chưa chọn ca' }} · {{ $registration->uses_personal_computer ? 'BYOD '.$registration->personal_computer_status : 'Máy trường' }}</span>
                    </label>
                @empty
                    <div class="space-y-3 p-4 text-sm text-slate-600">
                        <p>Chưa có đăng ký đã duyệt cần phân phòng. Hãy duyệt đăng ký hoặc kiểm tra danh sách học sinh chưa chọn ca.</p>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('admin.registrations.index', ['status' => 'submitted']) }}" class="rounded bg-emerald-700 px-3 py-2 text-xs font-semibold text-white">Duyệt đăng ký</a>
                            <a href="{{ route('admin.registrations.index', ['session_status' => 'missing']) }}" class="rounded border border-slate-300 px-3 py-2 text-xs font-semibold">Kiểm tra đăng ký chưa chọn ca</a>
                            <a href="{{ route('admin.sessions.index') }}" class="rounded border border-slate-300 px-3 py-2 text-xs font-semibold">Kiểm tra ca thi</a>
                        </div>
                    </div>
                @endforelse
            </div>
        </form>
        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
            <table class="min-w-full text-sm"><thead class="bg-slate-50"><tr><th class="p-3 text-left">Thí sinh</th><th class="p-3">Ca học sinh chọn</th><th class="p-3">Ca phân phòng</th><th class="p-3">Phòng</th><th class="p-3">Máy</th><th class="p-3">SBD</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($assignments as $assignment)
                        <tr>
                            <td class="p-3 font-medium">{{ $assignment->registration->full_name }}</td>
                            <td class="p-3 text-center">{{ $assignment->registration->chosenSession?->name ?? 'Dữ liệu cũ chưa chọn ca' }}</td>
                            <td class="p-3 text-center">{{ $assignment->session->name }}</td>
                            <td class="p-3 text-center">{{ $assignment->room->room_name }}</td>
                            <td class="p-3 text-center">{{ $assignment->seat_type === 'personal_computer' ? 'Máy cá nhân/BYOD' : $assignment->computer?->computer_label }}</td>
                            <td class="p-3 text-center">{{ $assignment->candidate_number }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-6 text-center text-sm text-slate-600">
                                <div class="space-y-3">
                                    <p>Chưa phân phòng cho học sinh nào.</p>
                                    <div class="flex flex-wrap justify-center gap-2">
                                        <a href="{{ route('admin.registrations.index') }}" class="rounded bg-emerald-700 px-3 py-2 text-xs font-semibold text-white">Mở danh sách đăng ký</a>
                                        <a href="{{ route('admin.rooms.index') }}" class="rounded border border-slate-300 px-3 py-2 text-xs font-semibold">Kiểm tra phòng/máy</a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $assignments->links() }}
    </div>
</x-app-layout>
