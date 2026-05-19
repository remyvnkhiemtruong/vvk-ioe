<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-semibold text-slate-900">Xếp giải - {{ $exam->name }}</h1>
                <p class="text-sm text-slate-500">Rule khối trống được áp dụng riêng cho từng khối, không gộp học sinh nhiều khối.</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.exam.rankings.index', $exam) }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700">Xếp hạng</a>
                <a href="{{ route('admin.exam.awards.index', $exam) }}" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white">Xếp giải</a>
            </div>
        </div>
    </x-slot>

    @php
        $awardLabels = [
            'first' => 'Giải Nhất',
            'gold' => 'Giải Nhất',
            'second' => 'Giải Nhì',
            'silver' => 'Giải Nhì',
            'third' => 'Giải Ba',
            'bronze' => 'Giải Ba',
            'encouragement' => 'Khuyến khích',
        ];
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

        <section class="grid gap-4 md:grid-cols-4">
            <x-stat-card label="Tổng giải" :value="$rankings->flatten(1)->count()" tone="emerald" />
            <x-stat-card label="Rule đang bật" :value="$awardRules->where('is_active', true)->count()" tone="blue" />
            <x-stat-card label="Đã có ranking" :value="$rankingExists ? 'Có' : 'Chưa'" :tone="$rankingExists ? 'slate' : 'amber'" />
            <x-stat-card label="Cần rerank" :value="$needsRerank ? 'Có' : 'Không'" :tone="$needsRerank ? 'amber' : 'slate'" />
        </section>

        @if(! $rankingExists)
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Vui lòng chạy xếp hạng trước khi xếp giải.</div>
        @elseif($needsRerank)
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Điểm đã thay đổi, vui lòng chạy lại xếp hạng trước khi xếp giải.</div>
        @endif

        @if($awardReport)
            <section class="rounded-lg border border-emerald-200 bg-emerald-50 p-5">
                <h2 class="font-semibold text-emerald-950">Báo cáo xếp giải</h2>
                <div class="mt-3 grid gap-3 text-sm text-emerald-900 sm:grid-cols-3">
                    <div>Tổng: <strong>{{ $awardReport['total_awarded'] ?? 0 }}</strong></div>
                    <div>Scope: <strong>{{ $scopes[$awardReport['scope'] ?? $selectedScope] ?? ($awardReport['scope'] ?? $selectedScope) }}</strong></div>
                    <div>Khối: <strong>{{ $awardReport['grade_number'] ?? 'Tất cả' }}</strong></div>
                </div>
                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                    @foreach(($awardReport['awarded_by_grade'] ?? []) as $grade => $count)
                        <span class="rounded-full bg-white px-3 py-1 font-semibold text-emerald-800">Khối {{ $grade }}: {{ $count }}</span>
                    @endforeach
                    @foreach(($awardReport['awarded_by_code'] ?? []) as $code => $count)
                        <span class="rounded-full bg-white px-3 py-1 font-semibold text-emerald-800">{{ $awardLabels[$code] ?? $code }}: {{ $count }}</span>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <div class="grid gap-4 lg:grid-cols-[1fr_auto] lg:items-end">
                <form method="GET" action="{{ route('admin.exam.awards.index', $exam) }}" class="grid gap-3 sm:grid-cols-3">
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

                <form method="POST" action="{{ route('admin.exam.awards.run', $exam) }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <input type="hidden" name="scope" value="{{ $selectedScope }}">
                    <input type="hidden" name="grade_number" value="{{ $selectedGrade }}">
                    <x-primary-button @disabled(! $rankingExists || $needsRerank)>Chạy xếp giải</x-primary-button>
                </form>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-slate-900">Tổng hợp giải</h2>
                <div class="flex flex-wrap gap-2 text-xs">
                    @foreach($summaryByGrade as $grade => $count)
                        <span class="rounded-full bg-slate-100 px-3 py-1 font-semibold text-slate-700">Khối {{ $grade }}: {{ $count }}</span>
                    @endforeach
                    @foreach($summaryByCode as $code => $count)
                        <span class="rounded-full bg-amber-100 px-3 py-1 font-semibold text-amber-800">{{ $awardLabels[$code] ?? $code }}: {{ $count }}</span>
                    @endforeach
                </div>
            </div>
        </section>

        @forelse($rankings as $grade => $items)
            <section class="rounded-lg border border-slate-200 bg-white">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
                    <h2 class="text-lg font-semibold text-slate-900">Danh sách giải khối {{ $grade }}</h2>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-700">{{ $items->count() }} giải</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Giải</th>
                                <th class="px-4 py-3">Hạng</th>
                                <th class="px-4 py-3">Học sinh</th>
                                <th class="px-4 py-3">Lớp</th>
                                <th class="px-4 py-3">Điểm</th>
                                <th class="px-4 py-3">Thời gian</th>
                                <th class="px-4 py-3">Rule</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($items as $ranking)
                                <tr>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800">{{ $ranking->award_name }}</span>
                                        @if($ranking->is_highest_award)
                                            <span class="ml-2 rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-800">cao nhất</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 font-bold">{{ $ranking->rank }}</td>
                                    <td class="px-4 py-3 font-medium">{{ $ranking->student?->full_name ?? 'Chưa ghép học sinh' }}</td>
                                    <td class="px-4 py-3">{{ $ranking->studentScore?->class_name ?? $ranking->student?->class_name ?? '-' }}</td>
                                    <td class="px-4 py-3 font-semibold">{{ rtrim(rtrim(number_format((float) $ranking->score, 2, '.', ''), '0'), '.') }}</td>
                                    <td class="px-4 py-3">{{ $formatDuration($ranking->duration_seconds) }}</td>
                                    <td class="px-4 py-3 text-slate-500">{{ $ranking->awardRule?->name ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @empty
            <section class="rounded-lg border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">
                Chưa có giải trong phạm vi đang chọn.
            </section>
        @endforelse

        <section class="space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-slate-900">Rule xếp giải</h2>
                <span class="text-sm text-slate-500">Rule khối trống áp dụng riêng từng khối.</span>
            </div>
            @foreach($awardRules as $rule)
                <form method="POST" action="{{ route('admin.exam.awards.rules.update', [$exam, $rule]) }}" class="rounded-lg border border-slate-200 bg-white p-5">
                    @csrf
                    @method('PUT')
                    <div class="grid gap-3 md:grid-cols-6">
                        <div class="md:col-span-2">
                            <x-input-label :for="'rule-name-'.$rule->id" value="Tên rule" />
                            <x-text-input :id="'rule-name-'.$rule->id" name="name" class="mt-1 w-full" :value="$rule->name" required />
                        </div>
                        <div>
                            <x-input-label :for="'rule-scope-'.$rule->id" value="Scope" />
                            <select id="{{ 'rule-scope-'.$rule->id }}" name="scope" class="mt-1 w-full rounded-md border-slate-300">
                                @foreach($scopes as $scope => $label)
                                    <option value="{{ $scope }}" @selected($rule->scope === $scope)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label :for="'rule-grade-'.$rule->id" value="Khối" />
                            <select id="{{ 'rule-grade-'.$rule->id }}" name="grade_number" class="mt-1 w-full rounded-md border-slate-300">
                                <option value="">Từng khối riêng</option>
                                @foreach($grades as $grade)
                                    <option value="{{ $grade }}" @selected((int) $rule->grade_number === (int) $grade)>Khối {{ $grade }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label :for="'rule-min-percent-'.$rule->id" value="% điểm tối thiểu" />
                            <x-text-input :id="'rule-min-percent-'.$rule->id" name="min_score_percent" type="number" min="0" max="100" class="mt-1 w-full" :value="$rule->min_score_percent" />
                        </div>
                        <div>
                            <x-input-label :for="'rule-top-percent-'.$rule->id" value="Top %" />
                            <x-text-input :id="'rule-top-percent-'.$rule->id" name="top_percent" type="number" min="1" max="100" step="0.01" class="mt-1 w-full" :value="$rule->top_percent" />
                        </div>
                        <div>
                            <x-input-label :for="'rule-min-score-'.$rule->id" value="Điểm tối thiểu" />
                            <x-text-input :id="'rule-min-score-'.$rule->id" name="min_score" type="number" min="0" step="0.01" class="mt-1 w-full" :value="$rule->min_score" />
                        </div>
                        <div>
                            <x-input-label :for="'rule-max-awards-'.$rule->id" value="Tối đa giải" />
                            <x-text-input :id="'rule-max-awards-'.$rule->id" name="max_awards" type="number" min="1" class="mt-1 w-full" :value="$rule->max_awards" />
                        </div>
                        <div>
                            <x-input-label :for="'rule-priority-'.$rule->id" value="Ưu tiên" />
                            <x-text-input :id="'rule-priority-'.$rule->id" name="priority_order" type="number" min="0" max="255" class="mt-1 w-full" :value="$rule->priority_order" required />
                        </div>
                        <label class="flex items-end gap-2 pb-2 text-sm font-semibold text-slate-700">
                            <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300" @checked($rule->is_active)>
                            Đang bật
                        </label>
                    </div>

                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                                <tr>
                                    <th class="px-3 py-2">Loại giải</th>
                                    <th class="px-3 py-2">Tỷ lệ %</th>
                                    <th class="px-3 py-2">Tối đa</th>
                                    <th class="px-3 py-2">Thứ tự</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($rule->items as $item)
                                    <tr>
                                        <td class="px-3 py-2 font-medium">{{ $awardLabels[$item->award_code] ?? $item->award_name }}</td>
                                        <td class="px-3 py-2"><input name="items[{{ $item->id }}][ratio_percent]" value="{{ $item->ratio_percent }}" type="number" min="0" max="100" step="0.01" class="w-28 rounded-md border-slate-300"></td>
                                        <td class="px-3 py-2"><input name="items[{{ $item->id }}][max_quantity]" value="{{ $item->max_quantity }}" type="number" min="1" class="w-28 rounded-md border-slate-300"></td>
                                        <td class="px-3 py-2"><input name="items[{{ $item->id }}][sort_order]" value="{{ $item->sort_order }}" type="number" min="0" max="255" class="w-24 rounded-md border-slate-300" required></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <x-primary-button>Cập nhật rule</x-primary-button>
                    </div>
                </form>
            @endforeach
        </section>
    </div>
</x-app-layout>
