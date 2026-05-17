<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">Nhập điểm sau thi - {{ $exam->name }}</h1></x-slot>
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        @foreach(['success' => 'emerald', 'error' => 'rose'] as $key => $tone)
            @if(session($key))<div class="rounded bg-{{ $tone }}-50 p-3 text-sm text-{{ $tone }}-800">{{ session($key) }}</div>@endif
        @endforeach

        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-semibold">Nhập điểm mới</h2>
            <form method="POST" action="{{ route('admin.score-entry.store', $exam) }}" class="mt-4 grid gap-3 md:grid-cols-6">
                @csrf
                <select name="exam_student_id" class="md:col-span-2 rounded border-slate-300">
                    @foreach($unscored as $item)<option value="{{ $item->id }}">{{ $item->student?->full_name }} - {{ $item->class_name }}</option>@endforeach
                </select>
                <input name="score" type="number" min="0" step="0.01" required placeholder="Điểm" class="rounded border-slate-300">
                <input name="max_score" type="number" min="0" value="1000" class="rounded border-slate-300">
                <input name="duration_seconds" type="number" min="0" placeholder="Thời gian giây" class="rounded border-slate-300">
                <button class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Lưu nháp</button>
            </form>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-semibold">Bảng điểm</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Học sinh</th><th class="px-3 py-2">Điểm</th><th class="px-3 py-2">Thời gian</th><th class="px-3 py-2">Trạng thái</th><th class="px-3 py-2">Thao tác</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @forelse($scores as $score)
                        <tr>
                            <td class="px-3 py-3">{{ $score->student?->full_name }}<div class="text-xs text-slate-500">{{ $score->class_name }}</div></td>
                            <td class="px-3 py-3 font-semibold">{{ $score->score }} / {{ $score->max_score }}</td>
                            <td class="px-3 py-3">{{ $score->duration_seconds }} giây</td>
                            <td class="px-3 py-3"><x-status-badge :status="$score->status" /> @if($score->needs_rerank)<span class="text-xs text-amber-700">Cần xếp lại</span>@endif</td>
                            <td class="px-3 py-3">
                                <div class="flex flex-wrap gap-2">
                                    <form method="POST" action="{{ route('admin.score-entry.submit', [$exam, $score]) }}">@csrf<button class="rounded border px-2 py-1 text-xs">Submit</button></form>
                                    <form method="POST" action="{{ route('admin.score-entry.lock', [$exam, $score]) }}">@csrf<button class="rounded border px-2 py-1 text-xs">Khóa</button></form>
                                    <form method="POST" action="{{ route('admin.score-entry.unlock', [$exam, $score]) }}">@csrf<button class="rounded border px-2 py-1 text-xs">Mở khóa</button></form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-3 py-8 text-center text-slate-500">Chưa có điểm.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $scores->links() }}</div>
        </section>
    </div>
</x-app-layout>
