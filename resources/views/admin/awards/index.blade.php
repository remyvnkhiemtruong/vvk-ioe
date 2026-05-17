<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">Xếp giải - {{ $exam->name }}</h1></x-slot>
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        @if(session('success'))<div class="rounded bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <form method="POST" action="{{ route('admin.exam.awards.run', $exam) }}" class="flex flex-wrap gap-3">
                @csrf
                <input name="grade_number" placeholder="Khối, bỏ trống = tất cả" class="rounded border-slate-300">
                <button class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Chạy xếp giải</button>
            </form>
            <div class="mt-3 text-sm text-slate-600">Quy tắc đang bật: {{ $awardRules->count() }}</div>
        </section>
        @forelse($rankings as $grade => $items)
            <section class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="text-lg font-semibold">Khối {{ $grade }}</h2>
                <div class="mt-4 grid gap-2">
                    @foreach($items as $ranking)
                        <div class="flex items-center justify-between rounded border border-slate-200 p-3 text-sm">
                            <span>{{ $ranking->student?->full_name }} - {{ $ranking->score }} điểm - {{ $ranking->duration_seconds }} giây</span>
                            <span class="font-semibold">{{ $ranking->award_name }} @if($ranking->is_highest_award)<span class="text-emerald-700">(cao nhất)</span>@endif</span>
                        </div>
                    @endforeach
                </div>
            </section>
        @empty
            <section class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-slate-500">Chưa có giải. Hãy chạy xếp hạng rồi xếp giải.</section>
        @endforelse
    </div>
</x-app-layout>
