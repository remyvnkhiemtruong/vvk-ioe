<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">Check-in ngày thi</h1></x-slot>
    <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
        @if(session('status'))<div class="rounded bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
        @can('exports.manage')
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.exports.checkins') }}" class="rounded border border-slate-300 px-4 py-2 text-sm font-semibold">Xuất check-in Excel</a>
                <a href="{{ route('admin.exports.checkins.pdf') }}" class="rounded border border-slate-300 px-4 py-2 text-sm font-semibold">Xuất check-in PDF</a>
                <a href="{{ route('admin.exports.absent') }}" class="rounded border border-slate-300 px-4 py-2 text-sm font-semibold">Xuất vắng thi Excel</a>
                <a href="{{ route('admin.exports.absent.pdf') }}" class="rounded border border-slate-300 px-4 py-2 text-sm font-semibold">Xuất vắng thi PDF</a>
            </div>
        @endcan
        <div class="space-y-3">
            @foreach($assignments as $assignment)
                <form method="POST" action="{{ route(request()->routeIs('proctor.*') ? 'proctor.checkins.update' : 'admin.checkins.update', $assignment) }}" class="rounded-lg border border-slate-200 bg-white p-4">
                    @csrf @method('PATCH')
                    <div class="grid gap-3 md:grid-cols-[1fr_180px_1fr_auto] md:items-center">
                        <div><div class="font-medium">{{ $assignment->registration->full_name }}</div><div class="text-sm text-slate-500">{{ $assignment->registration->class_name }} · {{ $assignment->session->name }} · {{ $assignment->room->room_name }} · {{ $assignment->computer?->computer_label ?? 'BYOD' }}</div></div>
                        <select name="status" class="rounded-md border-slate-300">@foreach(['not_checked_in'=>'Chưa check-in','present'=>'Có mặt','absent'=>'Vắng','late'=>'Đến muộn','incident'=>'Sự cố','completed'=>'Hoàn thành'] as $value=>$label)<option value="{{ $value }}" @selected(($assignment->checkin?->status ?? 'not_checked_in')===$value)>{{ $label }}</option>@endforeach</select>
                        <input name="note" value="{{ $assignment->checkin?->note }}" placeholder="Ghi chú" class="rounded-md border-slate-300">
                        <button class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Lưu</button>
                    </div>
                    @if($assignment->seat_type === 'personal_computer')
                        <div class="mt-3 flex flex-wrap gap-4 text-sm"><label><input type="checkbox" name="personal_device_present" value="1" @checked($assignment->checkin?->personal_device_present)> Có thiết bị</label><label><input type="checkbox" name="charger_present" value="1" @checked($assignment->checkin?->charger_present)> Có sạc</label><label><input type="checkbox" name="network_ok" value="1" @checked($assignment->checkin?->network_ok)> Kết nối mạng được</label><label><input type="checkbox" name="ioe_login_ok" value="1" @checked($assignment->checkin?->ioe_login_ok)> Đăng nhập IOE được</label></div>
                    @endif
                </form>
            @endforeach
        </div>
        {{ $assignments->links() }}
    </div>
</x-app-layout>
