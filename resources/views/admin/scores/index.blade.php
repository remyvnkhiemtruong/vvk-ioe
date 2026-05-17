<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">Điểm thi chính thức</h1></x-slot>
    <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
        @if(session('status'))<div class="rounded bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
        @can('exports.manage')
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.exports.scores') }}" class="rounded border border-slate-300 px-4 py-2 text-sm font-semibold">Xuất bảng điểm Excel</a>
                <a href="{{ route('admin.exports.scores.pdf') }}" class="rounded border border-slate-300 px-4 py-2 text-sm font-semibold">Xuất bảng điểm PDF</a>
            </div>
        @endcan
        <div class="space-y-3">
            @foreach($registrations as $registration)
                <form method="POST" action="{{ route(request()->routeIs('proctor.*') ? 'proctor.scores.store' : 'admin.scores.store', $registration) }}" class="rounded-lg border border-slate-200 bg-white p-4">
                    @csrf
                    <div class="grid gap-3 md:grid-cols-[1fr_120px_140px_140px_160px_auto] md:items-center">
                        <div><div class="font-medium">{{ $registration->full_name }}</div><div class="text-sm text-slate-500">{{ $registration->class_name }} · {{ $registration->seatAssignment?->session?->name ?? 'Chưa phân' }} · <x-status-badge :status="$registration->score?->score_status ?? 'not_entered'" /></div></div>
                        <input name="official_score" type="number" step="0.01" min="0" value="{{ $registration->score?->official_score }}" placeholder="Điểm" class="rounded-md border-slate-300">
                        <input name="completion_time_seconds" type="number" min="0" value="{{ $registration->score?->completion_time_seconds }}" placeholder="Thời gian giây" class="rounded-md border-slate-300">
                        <input name="correct_answers" type="number" min="0" value="{{ $registration->score?->correct_answers }}" placeholder="Số câu đúng" class="rounded-md border-slate-300">
                        <select name="exam_status" class="rounded-md border-slate-300">@foreach(['not_started'=>'Chưa thi','in_progress'=>'Đang thi','completed'=>'Hoàn thành','incomplete'=>'Không hoàn thành','incident'=>'Có sự cố'] as $value=>$label)<option value="{{ $value }}" @selected(($registration->score?->exam_status ?? 'completed')===$value)>{{ $label }}</option>@endforeach</select>
                        <button class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Lưu</button>
                    </div>
                    @if($registration->score?->score_status === 'locked')
                        <input type="hidden" name="locked_change" value="1">
                        <input name="reason" placeholder="Lý do sửa điểm đã khóa" class="mt-3 w-full rounded-md border-slate-300">
                    @endif
                    <input name="note" value="{{ $registration->score?->note }}" placeholder="Ghi chú" class="mt-3 w-full rounded-md border-slate-300">
                    @if($registration->score && ! request()->routeIs('proctor.*'))
                        <div class="mt-3 flex gap-2">
                            <button form="verify-{{ $registration->score->id }}" class="rounded border px-3 py-1 text-xs font-semibold" type="submit">Xác nhận</button>
                            <button form="lock-{{ $registration->score->id }}" class="rounded border px-3 py-1 text-xs font-semibold" type="submit">Khóa điểm</button>
                        </div>
                    @endif
                </form>
                @if($registration->score)
                    <form id="verify-{{ $registration->score->id }}" method="POST" action="{{ route('admin.scores.verify', $registration->score) }}" class="hidden">@csrf</form>
                    <form id="lock-{{ $registration->score->id }}" method="POST" action="{{ route('admin.scores.lock', $registration->score) }}" class="hidden">@csrf</form>
                @endif
            @endforeach
        </div>
        {{ $registrations->links() }}
    </div>
</x-app-layout>
