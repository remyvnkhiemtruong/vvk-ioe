<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">Bảng điều khiển giám thị</h1></x-slot>
    <div class="mx-auto max-w-5xl space-y-4 px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap gap-2"><a href="{{ route('proctor.checkins.index') }}" class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Mở check-in</a><a href="{{ route('proctor.scores.index') }}" class="rounded border border-slate-300 px-4 py-2 text-sm font-semibold">Nhập điểm</a></div>
        <div class="grid gap-4 md:grid-cols-2">
            @foreach($assignments as $assignment)
                <div class="rounded-lg border border-slate-200 bg-white p-4"><h2 class="font-semibold">{{ $assignment->session->name }}</h2><p class="text-sm text-slate-600">{{ $assignment->room->room_name }} · {{ $assignment->session->exam_date?->format('d/m/Y') }} {{ $assignment->session->start_time }}-{{ $assignment->session->end_time }}</p></div>
            @endforeach
        </div>
    </div>
</x-app-layout>
