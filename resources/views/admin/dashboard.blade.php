<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Dashboard quản trị IOE nội bộ</h1>
                <p class="mt-1 text-sm text-slate-600">Theo dõi học sinh, điều kiện, lịch thi, mã ca, live, điểm và giải thưởng.</p>
            </div>
            @if($latestInternalExam)
                <x-status-badge :status="$latestInternalExam->status" />
            @endif
        </div>
    </x-slot>

    @php
        $examStudentsUrl = $latestInternalExam ? route('admin.exam-students.index', $latestInternalExam) : route('admin.exams.index');
        $examCodesUrl = $latestInternalExam ? route('admin.exam-codes.index', $latestInternalExam) : route('admin.exams.index');
        $liveUrl = $latestInternalExam ? route('admin.live-screens.index', $latestInternalExam) : route('admin.exams.index');
        $scoreEntryUrl = $latestInternalExam ? route('admin.score-entry.index', $latestInternalExam) : route('admin.exams.index');
        $rankingsUrl = $latestInternalExam ? route('admin.exam.rankings.index', $latestInternalExam) : route('admin.exams.index');
        $awardsUrl = $latestInternalExam ? route('admin.exam.awards.index', $latestInternalExam) : route('admin.exams.index');
    @endphp

    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        @if(session('status'))
            <div class="rounded bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-stat-card label="Học sinh nội bộ" :value="$stats['internal_students']" tone="blue" :href="$examStudentsUrl" />
            <x-stat-card label="Đủ điều kiện" :value="$stats['internal_eligible']" tone="emerald" :href="$examStudentsUrl" />
            <x-stat-card label="Thiếu điều kiện" :value="$stats['internal_ineligible']" tone="amber" :href="$examStudentsUrl" />
            <x-stat-card label="Đã đăng ký trên ioe.vn" :value="$stats['registered_on_ioe']" tone="emerald" :href="$examStudentsUrl" />
            <x-stat-card label="Đã gán khung giờ" :value="$stats['assigned_to_slot']" tone="slate" :href="$examStudentsUrl" />
            <x-stat-card label="Khung giờ có học sinh" :value="$stats['time_slots_with_students']" tone="blue" :href="route('admin.sessions.index')" />
            <x-stat-card label="Màn hình live" :value="$stats['live_screens']" tone="blue" :href="$liveUrl" />
            <x-stat-card label="Đã nhập điểm" :value="$stats['v2_scores_entered']" tone="emerald" :href="$scoreEntryUrl" />
            <x-stat-card label="Điểm đã khóa" :value="$stats['v2_scores_locked']" tone="slate" :href="$scoreEntryUrl" />
            <x-stat-card label="Bản ghi vinh danh" :value="$stats['award_records']" tone="amber" :href="$awardsUrl" />
            <x-stat-card label="Rollover 2026-2027" :value="$stats['rollover_2026_2027']" tone="blue" :href="route('admin.students.index')" />
            <x-stat-card label="Tổng học sinh" :value="$stats['students']" tone="slate" :href="route('admin.students.index')" />
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold">Workflow IOE nội bộ</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Hệ thống chỉ hỗ trợ quản lý nội bộ. Mã ca thi do admin nhập sau khi lấy từ ioe.vn; điểm cũng được nhập lại sau khi học sinh thi trên ioe.vn.
                    </p>
                </div>
                <a href="{{ route('admin.exams.index') }}" class="rounded bg-emerald-700 px-3 py-2 text-sm font-semibold text-white">Quản lý kỳ thi</a>
            </div>
            <div class="mt-4 grid gap-3 md:grid-cols-3">
                <a href="{{ $examStudentsUrl }}" class="rounded border border-slate-200 p-3 text-sm font-semibold hover:bg-slate-50">Danh sách học sinh nội bộ</a>
                <a href="{{ route('admin.sessions.index') }}" class="rounded border border-slate-200 p-3 text-sm font-semibold hover:bg-slate-50">Lịch thi và khung giờ</a>
                <a href="{{ $examCodesUrl }}" class="rounded border border-slate-200 p-3 text-sm font-semibold hover:bg-slate-50">Mã ca thi từ ioe.vn</a>
                <a href="{{ $liveUrl }}" class="rounded border border-slate-200 p-3 text-sm font-semibold hover:bg-slate-50">Link /live cho giám thị</a>
                <a href="{{ $scoreEntryUrl }}" class="rounded border border-slate-200 p-3 text-sm font-semibold hover:bg-slate-50">Nhập điểm sau thi</a>
                <a href="{{ $rankingsUrl }}" class="rounded border border-slate-200 p-3 text-sm font-semibold hover:bg-slate-50">Xếp hạng và xếp giải</a>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="text-lg font-semibold">Cảnh báo live</h2>
                <div class="mt-4 space-y-3">
                    @forelse($slotWarnings as $slot)
                        <a href="{{ $examCodesUrl }}" class="block rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                            Khung giờ {{ $slot->name ?? $slot->gradeLabel() }} có {{ $slot->student_count }} học sinh nhưng chưa có mã active.
                        </a>
                    @empty
                        <div class="rounded border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">Chưa có cảnh báo thiếu mã cho khung giờ có học sinh.</div>
                    @endforelse
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="text-lg font-semibold">Top điểm theo kỳ thi đang xem</h2>
                <div class="mt-4 divide-y divide-slate-100">
                    @forelse($topScores as $score)
                        <div class="flex items-center justify-between gap-3 py-2 text-sm">
                            <div>
                                <div class="font-semibold">{{ $score->student?->full_name ?? 'Học sinh' }}</div>
                                <div class="text-slate-500">Khối {{ $score->grade_number }} · {{ $score->class_name }}</div>
                            </div>
                            <div class="text-right">
                                <div class="font-semibold">{{ rtrim(rtrim(number_format((float) $score->score, 2, '.', ''), '0'), '.') }}</div>
                                <div class="text-slate-500">{{ $score->duration_seconds ? gmdate('i:s', $score->duration_seconds) : '--:--' }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">Chưa có điểm được nhập cho kỳ thi này.</div>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="text-lg font-semibold">Vinh danh lịch sử</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    @forelse($awardCounts as $item)
                        <div class="rounded border border-slate-200 p-3 text-sm">
                            <span class="font-semibold">{{ ucfirst($item->award_scope) }}</span>
                            <span class="float-right">{{ $item->total }}</span>
                        </div>
                    @empty
                        <div class="rounded border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 sm:col-span-2">Chưa có dữ liệu vinh danh lịch sử.</div>
                    @endforelse
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="text-lg font-semibold">Rollover 2026-2027</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    @forelse($rolloverCounts as $status => $total)
                        <div class="rounded border border-slate-200 p-3 text-sm">
                            <span class="font-semibold">{{ str_replace('_', ' ', $status) }}</span>
                            <span class="float-right">{{ $total }}</span>
                        </div>
                    @empty
                        <div class="rounded border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 sm:col-span-2">Chưa có bản ghi rollover năm học 2026-2027.</div>
                    @endforelse
                </div>
            </section>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-semibold">Số liệu legacy còn tương thích</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-stat-card label="Đăng ký legacy" :value="$stats['registrations']" tone="slate" :href="route('admin.registrations.index')" />
                <x-stat-card label="Chờ duyệt" :value="$stats['pending']" tone="amber" :href="route('admin.registrations.index', ['status' => 'submitted'])" />
                <x-stat-card label="Đã phân phòng" :value="$stats['assigned']" tone="slate" :href="route('admin.assignments.index')" />
                <x-stat-card label="Điểm legacy đã nhập" :value="$stats['scores_entered']" tone="slate" :href="route('admin.scores.index')" />
            </div>
        </section>
    </div>
</x-app-layout>
