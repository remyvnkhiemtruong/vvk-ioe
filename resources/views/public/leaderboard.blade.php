<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bảng xếp hạng - {{ $settings->contestName() }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-900 antialiased">
@php
    $durationLabel = function ($seconds) {
        if ($seconds === null) {
            return '—';
        }
        $seconds = (int) $seconds;
        return $seconds >= 3600 ? gmdate('H:i:s', $seconds) : gmdate('i:s', $seconds);
    };
    $awardClass = fn ($code) => match ($code) {
        'first', 'gold' => 'bg-amber-100 text-amber-800 border-amber-200',
        'second', 'silver' => 'bg-slate-100 text-slate-700 border-slate-200',
        'third', 'bronze' => 'bg-orange-100 text-orange-800 border-orange-200',
        'encouragement' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
        default => 'bg-slate-100 text-slate-600 border-slate-200',
    };
@endphp

<header class="border-b border-slate-200 bg-white">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4">
        <a href="{{ route('home') }}" class="flex items-center gap-3">
            <x-application-logo class="h-11 w-11 rounded-full" />
            <span>
                <span class="block text-sm font-semibold uppercase tracking-wide text-emerald-700">{{ $settings->schoolName() }}</span>
                <span class="block text-lg font-semibold">Bảng xếp hạng IOE</span>
            </span>
        </a>
        <a href="{{ route('home') }}" class="rounded border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Trang chủ</a>
    </div>
</header>

<main>
    <section class="border-b border-slate-200 bg-white">
        <div class="mx-auto max-w-7xl px-4 py-10">
            <div class="max-w-3xl">
                <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">{{ $settings->schoolYear() }}</p>
                <h1 class="mt-2 text-4xl font-semibold leading-tight text-slate-950 md:text-5xl">Bảng xếp hạng IOE cấp trường</h1>
                <p class="mt-4 text-lg leading-8 text-slate-600">
                    {{ $exam?->name ?? 'Chưa chọn kỳ thi' }}
                </p>
                @if($lastGeneratedAt)
                    <p class="mt-2 text-sm text-slate-500">Cập nhật lần cuối: {{ $lastGeneratedAt->format('d/m/Y H:i') }}</p>
                @endif
            </div>
        </div>
    </section>

    <div class="mx-auto max-w-7xl space-y-6 px-4 py-8">
        <form method="GET" action="{{ route('public.leaderboard') }}" class="rounded-lg border border-slate-200 bg-white p-5">
            <div class="grid gap-4 md:grid-cols-5">
                <div>
                    <x-input-label for="exam_id" value="Kỳ thi" />
                    <select id="exam_id" name="exam_id" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                        @foreach($exams as $item)
                            <option value="{{ $item->id }}" @selected($exam?->id === $item->id)>{{ $item->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="grade" value="Khối" />
                    <select id="grade" name="grade" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                        <option value="">Tất cả</option>
                        @foreach($grades as $grade)
                            <option value="{{ $grade }}" @selected((string) request('grade') === (string) $grade)>Khối {{ $grade }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="class_name" value="Lớp" />
                    <select id="class_name" name="class_name" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                        <option value="">Tất cả</option>
                        @foreach($classes as $class)
                            <option value="{{ $class }}" @selected(request('class_name') === $class)>{{ $class }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="scope" value="Scope" />
                    <select id="scope" name="scope" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                        @foreach(['school' => 'Trường', 'ward' => 'Xã/phường', 'province' => 'Tỉnh', 'national' => 'Quốc gia'] as $value => $label)
                            <option value="{{ $value }}" @selected($scope === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="q" value="Tìm tên" />
                    <x-text-input id="q" name="q" class="mt-1 block w-full" :value="request('q')" />
                </div>
            </div>
            <div class="mt-4 flex justify-end">
                <x-primary-button>Lọc bảng</x-primary-button>
            </div>
        </form>

        @unless($canShow)
            <section class="rounded-lg border border-dashed border-slate-300 bg-white p-10 text-center">
                <h2 class="text-xl font-semibold">Bảng xếp hạng chưa được công bố.</h2>
                <p class="mt-2 text-sm text-slate-600">Khi nhà trường bật công bố, dữ liệu xếp hạng sẽ hiển thị tại đây.</p>
            </section>
        @else
            <section class="grid gap-4 md:grid-cols-3">
                @forelse($topByGrade as $grade => $items)
                    <div class="rounded-lg border border-slate-200 bg-white p-5">
                        <h2 class="text-lg font-semibold">Top 3 khối {{ $grade }}</h2>
                        <div class="mt-4 space-y-3">
                            @foreach($items as $ranking)
                                <div class="flex items-center justify-between gap-3 rounded border border-slate-200 p-3">
                                    <div>
                                        <div class="text-sm font-semibold">#{{ $ranking->rank }} {{ $ranking->student?->full_name }}</div>
                                        <div class="text-xs text-slate-500">{{ $ranking->student?->class_name }} · {{ $ranking->score }} điểm</div>
                                    </div>
                                    @if($ranking->award_code)
                                        <span class="rounded-full border px-2 py-1 text-xs font-semibold {{ $awardClass($ranking->award_code) }}">{{ $ranking->award_name }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="rounded-lg border border-dashed border-slate-300 bg-white p-6 text-slate-500 md:col-span-3">Chưa có dữ liệu xếp hạng.</div>
                @endforelse
            </section>

            @foreach($rankingsByGrade as $grade => $items)
                <section class="overflow-hidden rounded-lg border border-slate-200 bg-white">
                    <div class="border-b border-slate-200 bg-slate-50 px-5 py-4">
                        <h2 class="text-lg font-semibold">Khối {{ $grade }}</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-white text-left text-xs uppercase text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Hạng</th>
                                    <th class="px-4 py-3">Họ tên</th>
                                    <th class="px-4 py-3">Lớp</th>
                                    <th class="px-4 py-3">Khối</th>
                                    <th class="px-4 py-3">Điểm</th>
                                    <th class="px-4 py-3">Thời gian</th>
                                    <th class="px-4 py-3">Giải</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($items as $ranking)
                                    <tr>
                                        <td class="px-4 py-3 font-semibold">#{{ $ranking->rank }}</td>
                                        <td class="px-4 py-3">{{ $ranking->student?->full_name }}</td>
                                        <td class="px-4 py-3">{{ $ranking->student?->class_name }}</td>
                                        <td class="px-4 py-3">{{ $ranking->grade_number }}</td>
                                        <td class="px-4 py-3 font-medium">{{ $ranking->score }}</td>
                                        <td class="px-4 py-3">{{ $durationLabel($ranking->duration_seconds) }}</td>
                                        <td class="px-4 py-3">
                                            @if($ranking->award_code)
                                                <span class="rounded-full border px-2 py-1 text-xs font-semibold {{ $awardClass($ranking->award_code) }}">{{ $ranking->award_name }}</span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endforeach
        @endunless
    </div>
</main>
</body>
</html>
