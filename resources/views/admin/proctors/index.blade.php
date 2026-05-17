<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-slate-900">Phân công giám thị</h2>
                <p class="text-sm text-slate-500">Giám thị chỉ xem và thao tác trong ca/phòng được phân công.</p>
            </div>
            @can('exports.manage')
                <a href="{{ route('admin.exports.proctors') }}" class="rounded border border-slate-300 px-4 py-2 text-sm font-semibold">Xuất phân công Excel</a>
            @endcan
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6 p-6">
        @if(session('success'))
            <div class="rounded border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif

        <form method="GET" class="rounded-lg border border-slate-200 bg-white p-4">
            <label class="text-xs font-semibold text-slate-600">Lọc theo kỳ thi</label>
            <div class="mt-2 flex flex-wrap gap-2">
                <select name="exam_id" class="rounded border-slate-300 text-sm">
                    <option value="">Tất cả kỳ thi</option>
                    @foreach($exams as $exam)
                        <option value="{{ $exam->id }}" @selected($selectedExamId === $exam->id)>{{ $exam->name }}</option>
                    @endforeach
                </select>
                <button class="rounded bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Lọc</button>
            </div>
        </form>

        <form method="POST" action="{{ route('admin.proctors.store') }}" class="grid gap-3 rounded-lg border border-slate-200 bg-white p-4 md:grid-cols-5">
            @csrf
            <div>
                <label class="text-xs font-semibold text-slate-600">Giám thị</label>
                <select name="user_id" required class="mt-1 w-full rounded border-slate-300 text-sm">
                    <option value="">Chọn giám thị</option>
                    @foreach($proctors as $proctor)
                        <option value="{{ $proctor->id }}">{{ $proctor->name }} - {{ $proctor->email }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs font-semibold text-slate-600">Ca thi</label>
                <select name="exam_session_id" required class="mt-1 w-full rounded border-slate-300 text-sm">
                    <option value="">Chọn ca thi</option>
                    @foreach($sessions as $session)
                        <option value="{{ $session->id }}">{{ $session->exam?->name }} - {{ $session->name }} - {{ $session->exam_date?->format('d/m/Y') }} {{ $session->start_time }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs font-semibold text-slate-600">Phòng thi</label>
                <select name="exam_room_id" required class="mt-1 w-full rounded border-slate-300 text-sm">
                    <option value="">Chọn phòng</option>
                    @foreach($rooms as $room)
                        <option value="{{ $room->id }}">{{ $room->room_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs font-semibold text-slate-600">Vai trò trong phòng</label>
                <input name="role" value="Giám thị" required class="mt-1 w-full rounded border-slate-300 text-sm">
            </div>
            <div class="flex items-end">
                <button class="w-full rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Phân công</button>
            </div>
            <div class="md:col-span-5">
                <label class="text-xs font-semibold text-slate-600">Ghi chú</label>
                <input name="note" class="mt-1 w-full rounded border-slate-300 text-sm" placeholder="Nhiệm vụ, ca trực bổ sung hoặc lưu ý phòng thi">
            </div>
        </form>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Giám thị</th>
                        <th class="px-4 py-3">Kỳ/Ca</th>
                        <th class="px-4 py-3">Phòng</th>
                        <th class="px-4 py-3">Vai trò</th>
                        <th class="px-4 py-3">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($assignments as $assignment)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-900">{{ $assignment->user?->name }}</div>
                                <div class="text-xs text-slate-500">{{ $assignment->user?->email }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div>{{ $assignment->session?->exam?->name }}</div>
                                <div class="text-xs text-slate-500">{{ $assignment->session?->name }} - {{ $assignment->session?->exam_date?->format('d/m/Y') }} {{ $assignment->session?->start_time }}</div>
                            </td>
                            <td class="px-4 py-3">{{ $assignment->room?->room_name }}</td>
                            <td class="px-4 py-3">
                                <div>{{ $assignment->role }}</div>
                                @if($assignment->note)
                                    <div class="text-xs text-slate-500">{{ $assignment->note }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('admin.proctors.destroy', $assignment) }}" onsubmit="return confirm('Xóa phân công giám thị này?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="rounded border border-rose-200 px-3 py-1 text-xs font-semibold text-rose-700">Xóa</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-slate-500">
                                <div class="space-y-3">
                                    <p>Chưa có phân công giám thị. Hãy chọn giám thị, ca thi và phòng thi ở form phía trên.</p>
                                    <div class="flex flex-wrap justify-center gap-2">
                                        <a href="{{ route('admin.sessions.index') }}" class="rounded bg-emerald-700 px-3 py-2 text-xs font-semibold text-white">Tạo ca thi</a>
                                        <a href="{{ route('admin.rooms.index') }}" class="rounded border border-slate-300 px-3 py-2 text-xs font-semibold">Kiểm tra phòng thi</a>
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
