<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">Kỳ đăng ký cấp trường</h1></x-slot>
    <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
        @if(session('status'))<div class="rounded bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
        <form method="POST" action="{{ route('admin.exams.store') }}" class="grid gap-3 rounded-lg border border-slate-200 bg-white p-4 md:grid-cols-4">
            @csrf
            <input name="name" placeholder="Tên kỳ đăng ký" required class="rounded-md border-slate-300 md:col-span-2">
            <input name="school_year" value="2025-2026" required class="rounded-md border-slate-300">
            <select name="registration_mode" class="rounded-md border-slate-300">
                <option value="admin_assign_session">Ban tổ chức phân ca sau</option>
                <option value="student_select_session">Học sinh chọn ca khi đăng ký</option>
            </select>
            <select name="status" class="rounded-md border-slate-300">
                @foreach(['draft'=>'Nháp','open'=>'Đang mở','closed'=>'Đã đóng','assigning'=>'Đang phân phòng','locked'=>'Đã khóa danh sách','in_progress'=>'Đang thi','completed'=>'Đã hoàn thành'] as $value=>$label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <input type="datetime-local" name="registration_opens_at" class="rounded-md border-slate-300">
            <input type="datetime-local" name="registration_closes_at" class="rounded-md border-slate-300">
            <input type="date" name="exam_date" class="rounded-md border-slate-300">
            <input type="time" name="exam_time" class="rounded-md border-slate-300">
            <select name="countdown_mode" class="rounded-md border-slate-300"><option value="auto">Countdown tự động</option><option value="open">Đến mở đăng ký</option><option value="close">Đến đóng đăng ký</option><option value="exam">Đến ngày thi</option></select>
            <label class="text-sm"><input type="hidden" name="allow_student_edit" value="0"><input type="checkbox" name="allow_student_edit" value="1" checked> Cho học sinh sửa</label>
            <label class="text-sm"><input type="hidden" name="allow_student_session_change" value="0"><input type="checkbox" name="allow_student_session_change" value="1" checked> Cho đổi ca</label>
            <input type="hidden" name="require_session_choice" value="0">
            <label class="text-sm"><input type="hidden" name="allow_personal_computer" value="0"><input type="checkbox" name="allow_personal_computer" value="1" checked> Cho BYOD</label>
            <label class="text-sm"><input type="hidden" name="auto_lock_full_sessions" value="0"><input type="checkbox" name="auto_lock_full_sessions" value="1" checked> Tự khóa ca đầy</label>
            <label class="text-sm"><input type="hidden" name="show_public_stats" value="0"><input type="checkbox" name="show_public_stats" value="1" checked> Thống kê công khai</label>
            <label class="text-sm"><input type="hidden" name="require_approval" value="0"><input type="checkbox" name="require_approval" value="1" checked> Cần duyệt</label>
            <label class="text-sm"><input type="hidden" name="show_countdown" value="0"><input type="checkbox" name="show_countdown" value="1" checked> Hiển thị countdown</label>
            <label class="text-sm"><input type="hidden" name="publish_scores" value="0"><input type="checkbox" name="publish_scores" value="1"> Công bố điểm</label>
            <textarea name="description" placeholder="Mô tả kỳ đăng ký" class="rounded-md border-slate-300 md:col-span-3"></textarea>
            <button class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Tạo kỳ</button>
        </form>
        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50"><tr><th class="p-3 text-left">Tên kỳ</th><th class="p-3">Năm học</th><th class="p-3">Mở</th><th class="p-3">Đóng</th><th class="p-3">Ngày thi</th><th class="p-3">Ca/ĐK</th><th class="p-3">Trạng thái</th><th class="p-3">Thao tác</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($exams as $exam)
                        <tr>
                            <td class="p-3 font-medium">{{ $exam->name }}</td>
                            <td class="p-3 text-center">{{ $exam->school_year }}</td>
                            <td class="p-3 text-center">{{ $exam->registration_opens_at?->format('d/m/Y H:i') ?? 'Chưa cấu hình' }}</td>
                            <td class="p-3 text-center">{{ $exam->registration_closes_at?->format('d/m/Y H:i') ?? 'Chưa cấu hình' }}</td>
                            <td class="p-3 text-center">{{ $exam->exam_date?->format('d/m/Y') ?? 'Chưa có ngày thi' }}</td>
                            <td class="p-3 text-center">{{ $exam->sessions_count }}/{{ $exam->registrations_count }}</td>
                            <td class="p-3 text-center"><x-status-badge :status="$exam->status" /></td>
                            <td class="p-3">
                                <div class="flex flex-wrap justify-end gap-2">
                                    @if($exam->status !== 'open')<form method="POST" action="{{ route('admin.exams.open', $exam) }}">@csrf<button class="rounded border px-2 py-1 text-xs font-semibold">Mở</button></form>@endif
                                    @if($exam->status === 'open')<form method="POST" action="{{ route('admin.exams.close', $exam) }}">@csrf<button class="rounded border px-2 py-1 text-xs font-semibold">Đóng</button></form>@endif
                                    <form method="POST" action="{{ route('admin.exams.lock', $exam) }}">@csrf<button class="rounded border px-2 py-1 text-xs font-semibold">Khóa</button></form>
                                    <form method="POST" action="{{ route('admin.exams.publish_scores', $exam) }}">@csrf<button class="rounded border px-2 py-1 text-xs font-semibold">{{ $exam->publish_scores ? 'Tắt điểm' : 'Công bố điểm' }}</button></form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="p-6 text-center text-sm text-slate-600">Chưa có kỳ đăng ký. Hãy tạo kỳ IOE cấp trường đầu tiên.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $exams->links() }}
    </div>
</x-app-layout>
