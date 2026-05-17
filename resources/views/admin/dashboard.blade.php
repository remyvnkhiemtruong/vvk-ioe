<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">Dashboard quản trị IOE</h1></x-slot>
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        @if(session('status'))<div class="rounded bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-stat-card label="Tổng học sinh" :value="$stats['students']" tone="blue" :href="route('admin.students.index')" />
            <x-stat-card label="Tổng đăng ký IOE" :value="$stats['registrations']" tone="emerald" :href="route('admin.registrations.index')" />
            <x-stat-card label="Chờ duyệt" :value="$stats['pending']" tone="amber" :href="route('admin.registrations.index', ['status'=>'submitted'])" />
            <x-stat-card label="Từ chối" :value="$stats['rejected']" tone="rose" :href="route('admin.registrations.index', ['status'=>'rejected'])" />
            <x-stat-card label="BYOD chờ duyệt" :value="$stats['byod_pending']" tone="amber" :href="route('admin.registrations.index')" />
            <x-stat-card label="Đã phân phòng" :value="$stats['assigned']" tone="slate" :href="route('admin.assignments.index')" />
            <x-stat-card label="Chưa phân phòng" :value="$stats['unassigned']" tone="amber" :href="route('admin.assignments.index')" />
            <x-stat-card label="Có sự cố" :value="$stats['incidents']" tone="rose" :href="route('admin.incidents.index')" />
            <x-stat-card label="Ca khối 10" :value="$stats['sessions_grade_10']" tone="blue" :href="route('admin.sessions.index')" />
            <x-stat-card label="Ca khối 11" :value="$stats['sessions_grade_11']" tone="blue" :href="route('admin.sessions.index')" />
            <x-stat-card label="Ca khối 12" :value="$stats['sessions_grade_12']" tone="blue" :href="route('admin.sessions.index')" />
            <x-stat-card label="Ca còn chỗ" :value="$stats['sessions_open']" tone="emerald" :href="route('admin.sessions.index')" />
            <x-stat-card label="Ca đã đầy" :value="$stats['sessions_full']" tone="rose" :href="route('admin.sessions.index')" />
            <x-stat-card label="Ca bị khóa" :value="$stats['sessions_locked']" tone="slate" :href="route('admin.sessions.index')" />
            <x-stat-card label="Đã nhập điểm" :value="$stats['scores_entered']" tone="emerald" :href="route('admin.scores.index')" />
            <x-stat-card label="Chưa nhập điểm" :value="$stats['scores_missing']" tone="rose" :href="route('admin.scores.index')" />
        </div>

        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">Cảnh báo vận hành</h2>
                @if($exam)<x-status-badge :status="$exam->status" />@endif
            </div>
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                <a href="{{ route('admin.settings.index') }}" class="rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">Hạn đăng ký: {{ $exam?->registration_closes_at?->format('d/m/Y H:i') ?? 'Chưa cấu hình' }}</a>
                <a href="{{ route('admin.registrations.index') }}" class="rounded border border-slate-200 p-3 text-sm">Máy cá nhân cần duyệt: {{ $stats['byod_pending'] }}</a>
                <a href="{{ route('admin.registrations.index', ['session_status'=>'missing']) }}" class="rounded border border-slate-200 p-3 text-sm">Đăng ký chưa chọn ca thi: {{ $stats['missing_session'] }}</a>
                <a href="{{ route('admin.assignments.index') }}" class="rounded border border-slate-200 p-3 text-sm">Học sinh chưa phân phòng: {{ $stats['unassigned'] }}</a>
                <a href="{{ route('admin.scores.index') }}" class="rounded border border-slate-200 p-3 text-sm">Bài thi chưa nhập điểm: {{ $stats['scores_missing'] }}</a>
                <a href="{{ route('admin.sessions.index') }}" class="rounded border {{ $stats['wrong_session'] ? 'border-rose-200 bg-rose-50 text-rose-900' : 'border-slate-200' }} p-3 text-sm">Gán ca sai khối/lớp: {{ $stats['wrong_session'] }}</a>
                <a href="{{ route('admin.rooms.index') }}" class="rounded border {{ $stats['broken_computers'] ? 'border-rose-200 bg-rose-50 text-rose-900' : 'border-slate-200' }} p-3 text-sm">Máy hỏng/bảo trì: {{ $stats['broken_computers'] }}</a>
                @if($nearlyFullSessions->isNotEmpty())
                    <a href="{{ route('admin.sessions.index') }}" class="rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">Ca sắp đầy: {{ $nearlyFullSessions->pluck('name')->join(', ') }}</a>
                @else
                    <div class="rounded border border-slate-200 p-3 text-sm">Chưa có ca sắp đầy.</div>
                @endif
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-semibold">Đăng ký theo lớp</h2>
            <div class="mt-4 grid gap-3 md:grid-cols-4">
                @forelse($gradeCounts as $item)
                    <div class="rounded border border-slate-200 p-3 text-sm"><span class="font-semibold">{{ $item->class_name }}</span><span class="float-right">{{ $item->total }}</span></div>
                @empty
                    <div class="rounded border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700 md:col-span-4">
                        <p>Chưa có đăng ký theo lớp.</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <a href="{{ route('admin.registrations.index') }}" class="rounded bg-emerald-700 px-3 py-2 text-xs font-semibold text-white">Mở danh sách đăng ký</a>
                            <a href="{{ route('admin.students.import') }}" class="rounded border border-slate-300 px-3 py-2 text-xs font-semibold">Import học sinh</a>
                            <a href="{{ route('admin.sessions.index') }}" class="rounded border border-slate-300 px-3 py-2 text-xs font-semibold">Tạo ca thi</a>
                        </div>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
