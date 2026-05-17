<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">Ca thi</h1></x-slot>
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        @if(session('status'))<div class="rounded bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="rounded bg-rose-50 p-3 text-sm text-rose-800">{{ $errors->first() }}</div>@endif

        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <h2 class="font-semibold">Tạo ca thi</h2>
            <form method="POST" action="{{ route('admin.sessions.store') }}" class="mt-4 grid gap-3 md:grid-cols-4">
                @csrf
                <select name="exam_id" required class="rounded-md border-slate-300">@foreach($exams as $exam)<option value="{{ $exam->id }}">{{ $exam->name }}</option>@endforeach</select>
                <select name="exam_room_id" class="rounded-md border-slate-300"><option value="">Chọn phòng sau</option>@foreach($rooms as $room)<option value="{{ $room->id }}">{{ $room->room_name }}</option>@endforeach</select>
                <input name="name" placeholder="Tên ca thi" required class="rounded-md border-slate-300">
                <input name="exam_date" type="date" required class="rounded-md border-slate-300">
                <input name="start_time" type="time" required class="rounded-md border-slate-300">
                <input name="end_time" type="time" required class="rounded-md border-slate-300">
                <select name="target_grade" class="rounded-md border-slate-300"><option value="">Tất cả khối</option><option value="10">Khối 10</option><option value="11">Khối 11</option><option value="12">Khối 12</option></select>
                <input name="target_classes_text" placeholder="Lớp áp dụng, cách nhau bởi dấu phẩy" class="rounded-md border-slate-300">
                <input name="max_candidates" type="number" value="25" min="1" required class="rounded-md border-slate-300">
                <select name="status" class="rounded-md border-slate-300"><option value="draft">Nháp</option><option value="open">Mở cho đăng ký</option><option value="closed">Đóng đăng ký</option><option value="locked">Đã khóa</option><option value="completed">Đã hoàn thành</option></select>
                <textarea name="note" placeholder="Ghi chú" class="rounded-md border-slate-300 md:col-span-2"></textarea>
                <button class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Tạo ca thi</button>
            </form>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <h2 class="font-semibold">Tạo nhanh nhiều ca</h2>
            <form method="POST" action="{{ route('admin.sessions.bulk') }}" class="mt-4 grid gap-3 md:grid-cols-4">
                @csrf
                <select name="exam_id" required class="rounded-md border-slate-300">@foreach($exams as $exam)<option value="{{ $exam->id }}">{{ $exam->name }}</option>@endforeach</select>
                <select name="exam_room_id" required class="rounded-md border-slate-300">@foreach($rooms as $room)<option value="{{ $room->id }}">{{ $room->room_name }}</option>@endforeach</select>
                <input name="exam_date" type="date" required class="rounded-md border-slate-300">
                <input name="first_start_time" type="time" value="07:00" required class="rounded-md border-slate-300">
                <input name="duration_minutes" type="number" value="45" min="10" required class="rounded-md border-slate-300">
                <input name="break_minutes" type="number" value="15" min="0" required class="rounded-md border-slate-300">
                <input name="session_count" type="number" value="12" min="1" max="48" required class="rounded-md border-slate-300">
                <input name="max_candidates" type="number" value="25" min="1" required class="rounded-md border-slate-300">
                <select name="target_grade" class="rounded-md border-slate-300"><option value="">Tất cả khối</option><option value="10">Khối 10</option><option value="11">Khối 11</option><option value="12">Khối 12</option></select>
                <input name="target_classes_text" placeholder="Lớp cụ thể nếu có" class="rounded-md border-slate-300">
                <select name="status" class="rounded-md border-slate-300"><option value="open">Mở cho đăng ký</option><option value="draft">Nháp</option></select>
                <button class="rounded bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Tạo nhanh 12 ca thi</button>
            </form>
        </section>

        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50">
                    <tr><th class="p-3 text-left">Ca thi</th><th class="p-3">Ngày giờ</th><th class="p-3">Đối tượng</th><th class="p-3">Phòng</th><th class="p-3">Số lượng</th><th class="p-3">Trạng thái</th><th class="p-3"></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($sessions as $session)
                        @php($used = $session->valid_registrations_count ?? 0)
                        @php($percent = $session->max_candidates > 0 ? min(100, round($used / $session->max_candidates * 100)) : 0)
                        <tr>
                            <td class="p-3 font-medium">{{ $session->name }}<div class="text-xs text-slate-500">{{ $session->exam?->name }}</div></td>
                            <td class="p-3 text-center">{{ $session->exam_date?->format('d/m/Y') }}<div class="text-xs text-slate-500">{{ $session->start_time }}-{{ $session->end_time }}</div></td>
                            <td class="p-3 text-center">{{ $session->targetLabel() }}</td>
                            <td class="p-3 text-center">{{ $session->room?->room_name ?? 'Chưa cấu hình' }}</td>
                            <td class="p-3">
                                <div class="text-center text-xs font-medium">{{ $used }}/{{ $session->max_candidates }} · còn {{ $session->remaining_slots }}</div>
                                <div class="mt-1 h-2 rounded bg-slate-100"><div class="h-2 rounded bg-emerald-600" style="width: {{ $percent }}%"></div></div>
                            </td>
                            <td class="p-3 text-center"><x-status-badge :status="$session->remaining_slots <= 0 ? 'full' : $session->status" /></td>
                            <td class="p-3">
                                <div class="flex justify-end gap-2">
                                    <form method="POST" action="{{ route('admin.sessions.duplicate', $session) }}">@csrf<button class="text-sm font-semibold text-emerald-700">Nhân bản</button></form>
                                    @if($used === 0)
                                        <form method="POST" action="{{ route('admin.sessions.destroy', $session) }}">@csrf @method('DELETE')<button class="text-sm font-semibold text-rose-700">Xóa</button></form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-6 text-center text-sm text-slate-600">Hiện chưa có ca thi nào. Hãy dùng form “Tạo ca thi” hoặc “Tạo nhanh 12 ca thi”.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $sessions->links() }}
    </div>
</x-app-layout>
