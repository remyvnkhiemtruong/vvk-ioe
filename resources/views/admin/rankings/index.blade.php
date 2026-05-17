<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">Xếp hạng - {{ $exam->name }}</h1></x-slot>
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        @if(session('success'))<div class="rounded bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if($needsRerank)<div class="rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">Có điểm đã sửa sau khi xếp giải, cần chạy lại xếp hạng/xếp giải.</div>@endif
        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <form method="POST" action="{{ route('admin.exam.rankings.run', $exam) }}" class="flex flex-wrap gap-3">
                @csrf
                <select name="scope" class="rounded border-slate-300"><option value="school">Toàn trường</option><option value="province">Toàn tỉnh</option><option value="national">Toàn quốc</option></select>
                <input name="grade_number" placeholder="Khối, bỏ trống = tất cả" class="rounded border-slate-300">
                <button class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Chạy xếp hạng</button>
            </form>
        </section>
        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Hạng</th><th class="px-3 py-2">Học sinh</th><th class="px-3 py-2">Khối</th><th class="px-3 py-2">Điểm</th><th class="px-3 py-2">Thời gian</th><th class="px-3 py-2">Giải</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @forelse($rankings as $ranking)
                        <tr><td class="px-3 py-3 font-semibold">{{ $ranking->rank }}</td><td class="px-3 py-3">{{ $ranking->student?->full_name }}</td><td class="px-3 py-3">{{ $ranking->grade_number }}</td><td class="px-3 py-3">{{ $ranking->score }}</td><td class="px-3 py-3">{{ $ranking->duration_seconds }}</td><td class="px-3 py-3">{{ $ranking->award_name ?? '-' }}</td></tr>
                    @empty
                        <tr><td colspan="6" class="px-3 py-8 text-center text-slate-500">Chưa có dữ liệu xếp hạng.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $rankings->links() }}</div>
        </section>
    </div>
</x-app-layout>
