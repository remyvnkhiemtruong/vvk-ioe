<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">Live trình chiếu - {{ $exam->name }}</h1></x-slot>
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        @if(session('success'))<div class="rounded bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if($slotsWithoutCode->isNotEmpty())
            <div class="rounded border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                <div class="font-semibold">Khung giờ có học sinh nhưng chưa có mã:</div>
                <div class="mt-1">{{ $slotsWithoutCode->map(fn($slot) => $slot->starts_at?->format('d/m H:i').' - '.$slot->name)->join('; ') }}</div>
            </div>
        @endif

        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-semibold">Tạo link live</h2>
            <form method="POST" action="{{ route('admin.live-screens.store', $exam) }}" class="mt-4 grid gap-3 md:grid-cols-3">
                @csrf
                <input name="display_title" placeholder="Tiêu đề hiển thị" class="rounded border-slate-300">
                <select name="exam_session_id" class="rounded border-slate-300">
                    <option value="">Toàn bộ kỳ thi</option>
                    @foreach($sessions as $session)<option value="{{ $session->id }}">{{ $session->name }}</option>@endforeach
                </select>
                <button class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Tạo link</button>
            </form>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-semibold">Các màn hình live</h2>
            <div class="mt-4 grid gap-4">
                @forelse($screens as $screen)
                    @php($state = $previews[$screen->id] ?? [])
                    <div class="rounded border border-slate-200 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <div class="font-semibold">{{ $screen->display_title ?: $exam->name }}</div>
                                <a href="{{ $screen->liveUrl() }}" target="_blank" class="text-sm text-emerald-700">{{ $screen->liveUrl() }}</a>
                                <div class="mt-1 text-xs text-slate-500">Preview: {{ $state['status'] ?? '-' }} - {{ $state['message'] ?? '' }}</div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <form method="POST" action="{{ route('admin.live-screens.toggle', [$exam, $screen]) }}">@csrf @method('PATCH')<button class="rounded border px-3 py-2 text-sm">{{ $screen->is_enabled ? 'Tắt live' : 'Bật live' }}</button></form>
                                @foreach(['hide' => 'Tạm ẩn mã', 'show' => 'Hiện thủ công', 'end' => 'Kết thúc', 'reset' => 'Reset'] as $action => $label)
                                    <form method="POST" action="{{ route('admin.live-screens.override', [$exam, $screen]) }}">@csrf @method('PATCH')<input type="hidden" name="action" value="{{ $action }}"><button class="rounded border px-3 py-2 text-sm">{{ $label }}</button></form>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="rounded border border-dashed border-slate-300 p-8 text-center text-slate-500">Chưa có link live.</div>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
