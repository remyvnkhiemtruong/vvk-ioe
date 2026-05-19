<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-semibold text-slate-900">Xếp hạng - {{ $exam->name }}</h1>
                <p class="text-sm text-slate-500">Mỗi khối có bảng riêng, hạng bắt đầu từ 1 theo từng scope.</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.exam.rankings.index', $exam) }}" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white">Xếp hạng</a>
                <a href="{{ route('admin.exam.awards.index', $exam) }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700">Xếp giải</a>
            </div>
        </div>
    </x-slot>

    @php
        $formatDuration = function ($seconds) {
            if ($seconds === null) {
                return '-';
            }
            $seconds = (int) $seconds;
            $hours = intdiv($seconds, 3600);
            $minutes = intdiv($seconds % 3600, 60);
            $remain = $seconds % 60;

            return $hours > 0
                ? sprintf('%d:%02d:%02d', $hours, $minutes, $remain)
                : sprintf('%02d:%02d', $minutes, $remain);
        };
    @endphp

    <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        @if(session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm font-medium text-rose-800">{{ $errors->first() }}</div>
        @endif

        <div class="grid gap-4 md:grid-cols-3">
            <x-stat-card label="Điểm trong phạm vi" :value="$scoreCount" tone="blue" />
            <x-stat-card label="Đã xếp hạng" :value="$rankings->count()" tone="emerald" />
            <x-stat-card label="Cần chạy lại" :value="$needsRerank ? 'Có' : 'Không'" :tone="$needsRerank ? 'amber' : 'slate'" />
        </div>

        @if($scoreCount === 0)
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Chưa có điểm hợp lệ trong phạm vi đang chọn.</div>
        @elseif($needsRerank)
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Có điểm đã thay đổi. Hãy chạy lại xếp hạng trước khi xếp giải.</div>
        @elseif(! $hasRanking)
            <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-600">Chưa chạy xếp hạng cho scope/khối đang chọn.</div>
        @endif

        @if($rankingReport)
            <section class="rounded-lg border border-emerald-200 bg-emerald-50 p-5">
                <h2 class="font-semibold text-emerald-950">Báo cáo xếp hạng</h2>
                <div class="mt-3 grid gap-3 text-sm text-emerald-900 sm:grid-cols-3">
                    <div>Tổng: <strong>{{ $rankingReport['total_ranked'] ?? 0 }}</strong></div>
                    <div>Scope: <strong>{{ $scopes[$rankingReport['scope'] ?? $selectedScope] ?? ($rankingReport['scope'] ?? $selectedScope) }}</strong></div>
                    <div>Khối: <strong>{{ $rankingReport['grade_number'] ?? 'Tất cả' }}</strong></div>
                </div>
                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                    @foreach(($rankingReport['ranked_by_grade'] ?? []) as $grade => $count)
                        <span class="rounded-full bg-white px-3 py-1 font-semibold text-emerald-800">Khối {{ $grade }}: {{ $count }}</span>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <div class="grid gap-4 lg:grid-cols-[1fr_auto] lg:items-end">
                <form method="GET" action="{{ route('admin.exam.rankings.index', $exam) }}" class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <x-input-label for="scope" value="Scope" />
                        <select id="scope" name="scope" class="mt-1 w-full rounded-md border-slate-300">
                            @foreach($scopes as $scope => $label)
                                <option value="{{ $scope }}" @selected($selectedScope === $scope)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="grade" value="Khối" />
                        <select id="grade" name="grade" class="mt-1 w-full rounded-md border-slate-300">
                            <option value="">Tất cả khối</option>
                            @foreach($grades as $grade)
                                <option value="{{ $grade }}" @selected($selectedGrade === (int) $grade)>Khối {{ $grade }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end">
                        <x-secondary-button type="submit" class="w-full justify-center">Lọc dữ liệu</x-secondary-button>
                    </div>
                </form>

                <form method="POST" action="{{ route('admin.exam.rankings.run', $exam) }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <input type="hidden" name="scope" value="{{ $selectedScope }}">
                    <input type="hidden" name="grade_number" value="{{ $selectedGrade }}">
                    <x-primary-button>Chạy xếp hạng</x-primary-button>
                </form>
            </div>
        </section>

        @forelse($rankingGroups as $grade => $items)
            <section class="rounded-lg border border-slate-200 bg-white">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
                    <h2 class="text-lg font-semibold text-slate-900">Khối {{ $grade }}</h2>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-700">{{ $items->count() }} học sinh</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Hạng</th>
                                <th class="px-4 py-3">Học sinh</th>
                                <th class="px-4 py-3">Lớp</th>
                                <th class="px-4 py-3">Điểm</th>
                                <th class="px-4 py-3">Thời gian</th>
                                <th class="px-4 py-3">Giải</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($items as $ranking)
                                <tr>
                                    <td class="px-4 py-3 text-base font-bold text-slate-900">{{ $ranking->rank }}</td>
                                    <td class="px-4 py-3 font-medium text-slate-900">{{ $ranking->student?->full_name ?? 'Chưa ghép học sinh' }}</td>
                                    <td class="px-4 py-3">{{ $ranking->studentScore?->class_name ?? $ranking->student?->class_name ?? '-' }}</td>
                                    <td class="px-4 py-3 font-semibold">{{ rtrim(rtrim(number_format((float) $ranking->score, 2, '.', ''), '0'), '.') }}</td>
                                    <td class="px-4 py-3">{{ $formatDuration($ranking->duration_seconds) }}</td>
                                    <td class="px-4 py-3">{{ $ranking->award_name ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @empty
            <section class="rounded-lg border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">
                Chưa có dữ liệu xếp hạng trong phạm vi đang chọn.
            </section>
        @endforelse
    </div>
</x-app-layout>
