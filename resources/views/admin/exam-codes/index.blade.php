<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">Mã ca thi - {{ $exam->name }}</h1></x-slot>
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        @if(session('success'))<div class="rounded bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-semibold">Nhập mã lấy thủ công từ ioe.vn</h2>
            <form method="POST" action="{{ route('admin.exam-codes.store', $exam) }}" class="mt-4 grid gap-3 md:grid-cols-4">
                @csrf
                <input name="code" required placeholder="Mã ca thi" class="rounded border-slate-300">
                <input name="label" placeholder="Nhãn mô tả" class="rounded border-slate-300">
                <select name="exam_session_id" class="rounded border-slate-300">
                    <option value="">Gắn cấp kỳ thi</option>
                    @foreach($sessions as $session)<option value="{{ $session->id }}">{{ $session->name }}</option>@endforeach
                </select>
                <select name="exam_time_slot_id" class="rounded border-slate-300">
                    <option value="">Gắn cấp ca/khung giờ</option>
                    @foreach($timeSlots as $slot)<option value="{{ $slot->id }}">{{ $slot->starts_at?->format('d/m H:i') }} - {{ $slot->name }}</option>@endforeach
                </select>
                <div class="md:col-span-4 flex flex-wrap gap-3 text-sm">
                    @for($grade = 1; $grade <= 12; $grade++)
                        <label class="flex items-center gap-1"><input type="checkbox" name="applied_grade_ids[]" value="{{ $grade }}" class="rounded"> Khối {{ $grade }}</label>
                    @endfor
                </div>
                <button class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white md:col-span-4">Lưu mã ca thi</button>
            </form>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-semibold">Mã đã nhập</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Mã</th><th class="px-3 py-2">Phạm vi</th><th class="px-3 py-2">Nguồn</th><th class="px-3 py-2">Trạng thái</th><th class="px-3 py-2">Thao tác</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @forelse($codes as $code)
                        <tr>
                            <td class="px-3 py-3 font-mono text-lg font-bold">{{ $code->code }}</td>
                            <td class="px-3 py-3">{{ $code->timeSlot?->name ?? $code->session?->name ?? 'Toàn kỳ thi' }}<div class="text-xs text-slate-500">{{ $code->label }}</div></td>
                            <td class="px-3 py-3">{{ $code->source }}</td>
                            <td class="px-3 py-3"><x-status-badge :status="$code->is_active ? 'active' : 'inactive'" /></td>
                            <td class="px-3 py-3">
                                <form method="POST" action="{{ route('admin.exam-codes.destroy', [$exam, $code]) }}">@csrf @method('DELETE')<button class="rounded border border-rose-300 px-2 py-1 text-xs text-rose-700">Xóa</button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-3 py-8 text-center text-slate-500">Chưa có mã ca thi.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
