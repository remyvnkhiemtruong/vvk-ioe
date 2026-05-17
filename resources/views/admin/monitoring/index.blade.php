<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">Giám sát ngày thi, biên bản và video</h1></x-slot>

    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        @if(session('status'))<div class="rounded bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="rounded bg-rose-50 p-3 text-sm text-rose-800">Vui lòng kiểm tra lại dữ liệu vừa nhập.</div>@endif

        @if(! $exam)
            <section class="rounded-lg border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                Chưa có kỳ thi nội bộ. 
                <a href="{{ route('admin.exams.index') }}" class="font-semibold underline">Tạo kỳ thi</a>
            </section>
        @else
            <section class="grid gap-4 lg:grid-cols-3">
                <form method="POST" action="{{ route('admin.monitoring.checklist') }}" class="rounded-lg border border-slate-200 bg-white p-5">
                    @csrf
                    <h2 class="text-lg font-semibold">Checklist trước ca</h2>
                    <input type="hidden" name="exam_id" value="{{ $exam->id }}">
                    <div class="mt-4 space-y-3 text-sm">
                        <select name="exam_session_id" required class="w-full rounded-md border-slate-300"><option value="">Chọn ca thi</option>@foreach($sessions as $session)<option value="{{ $session->id }}">{{ $session->name }} · {{ $session->exam_date?->format('d/m/Y') }} {{ $session->start_time }}</option>@endforeach</select>
                        <select name="exam_room_id" required class="w-full rounded-md border-slate-300"><option value="">Chọn phòng</option>@foreach($rooms as $room)<option value="{{ $room->id }}">{{ $room->room_name }}</option>@endforeach</select>
                        @foreach(['internet_ok'=>'Internet ổn định','computers_ok'=>'Máy tính đủ và sẵn sàng','headsets_ok'=>'Tai nghe/âm thanh tốt','camera_ok'=>'Camera ghi hình bao quát','time_zone_ok'=>'Giờ máy đúng GMT+7','backup_power_network_ready'=>'Có phương án điện/mạng dự phòng'] as $key=>$label)
                            <label class="flex gap-2"><input type="hidden" name="{{ $key }}" value="0"><input type="checkbox" name="{{ $key }}" value="1" class="rounded border-slate-300"> {{ $label }}</label>
                        @endforeach
                        <textarea name="notes" rows="3" placeholder="Ghi chú checklist" class="w-full rounded-md border-slate-300"></textarea>
                        <button class="rounded bg-emerald-700 px-4 py-2 font-semibold text-white">Lưu checklist</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('admin.monitoring.minute') }}" class="rounded-lg border border-slate-200 bg-white p-5">
                    @csrf
                    <h2 class="text-lg font-semibold">Biên bản thi</h2>
                    <input type="hidden" name="exam_id" value="{{ $exam->id }}">
                    <div class="mt-4 space-y-3 text-sm">
                        <select name="exam_session_id" required class="w-full rounded-md border-slate-300"><option value="">Chọn ca thi</option>@foreach($sessions as $session)<option value="{{ $session->id }}">{{ $session->name }} · {{ $session->exam_date?->format('d/m/Y') }}</option>@endforeach</select>
                        <select name="exam_room_id" required class="w-full rounded-md border-slate-300"><option value="">Chọn phòng</option>@foreach($rooms as $room)<option value="{{ $room->id }}">{{ $room->room_name }}</option>@endforeach</select>
                        <select name="status" required class="w-full rounded-md border-slate-300">
                            @foreach(['not_generated'=>'Chưa tạo','generated'=>'Đã tạo','printed'=>'Đã in','signed'=>'Đã ký','uploaded'=>'Đã upload','approved'=>'Đã duyệt','rejected'=>'Từ chối'] as $value=>$label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <input name="signed_scan_path" placeholder="Đường dẫn scan/PDF nếu có" class="w-full rounded-md border-slate-300">
                        <textarea name="notes" rows="3" placeholder="Ghi chú biên bản" class="w-full rounded-md border-slate-300"></textarea>
                        <button class="rounded bg-emerald-700 px-4 py-2 font-semibold text-white">Lưu biên bản</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('admin.monitoring.video') }}" class="rounded-lg border border-slate-200 bg-white p-5">
                    @csrf
                    <h2 class="text-lg font-semibold">Video giám sát</h2>
                    <input type="hidden" name="exam_id" value="{{ $exam->id }}">
                    <div class="mt-4 space-y-3 text-sm">
                        <select name="exam_session_id" required class="w-full rounded-md border-slate-300"><option value="">Chọn ca thi</option>@foreach($sessions as $session)<option value="{{ $session->id }}">{{ $session->name }} · {{ $session->exam_date?->format('d/m/Y') }}</option>@endforeach</select>
                        <select name="exam_room_id" required class="w-full rounded-md border-slate-300"><option value="">Chọn phòng</option>@foreach($rooms as $room)<option value="{{ $room->id }}">{{ $room->room_name }}</option>@endforeach</select>
                        <input name="video_url" type="url" required placeholder="Link Google Drive/Youtube/khác" class="w-full rounded-md border-slate-300">
                        <select name="storage_provider" class="w-full rounded-md border-slate-300"><option value="google_drive">Google Drive</option><option value="youtube">Youtube</option><option value="other">Khác</option></select>
                        <select name="quality_status" class="w-full rounded-md border-slate-300"><option value="pending">Chờ kiểm tra</option><option value="ok">Đạt</option><option value="not_ok">Chưa đạt</option></select>
                        <label class="flex gap-2"><input type="hidden" name="visibility_checked" value="0"><input type="checkbox" name="visibility_checked" value="1" class="rounded border-slate-300"> Đã kiểm tra quyền xem</label>
                        <input name="duration_note" placeholder="Ghi chú thời lượng" class="w-full rounded-md border-slate-300">
                        <button class="rounded bg-emerald-700 px-4 py-2 font-semibold text-white">Lưu video</button>
                    </div>
                </form>
            </section>
        @endif

        <section class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="font-semibold">Checklist đã lưu</h2>
                <div class="mt-3 divide-y text-sm">
                    @forelse($checklists as $item)
                        <div class="py-2">{{ $item->session?->name ?? 'Ca thi' }} · {{ $item->room?->room_name ?? 'Phòng thi' }} · {{ $item->checked_at?->format('d/m/Y H:i') ?? 'Chưa có giờ' }}</div>
                    @empty
                        <div class="py-4 text-slate-600">Chưa có checklist. Hãy lưu checklist trước ca thi ở biểu mẫu bên trên.</div>
                    @endforelse
                </div>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="font-semibold">Biên bản thi</h2>
                <div class="mt-3 divide-y text-sm">
                    @forelse($minutes as $item)
                        <div class="py-2">Phòng #{{ $item->exam_room_id }} · Ca #{{ $item->exam_session_id }} · <x-status-badge :status="$item->status" /></div>
                    @empty
                        <div class="py-4 text-slate-600">Chưa có biên bản. Hãy tạo trạng thái biên bản cho từng phòng/ca.</div>
                    @endforelse
                </div>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="font-semibold">Video giám sát</h2>
                <div class="mt-3 divide-y text-sm">
                    @forelse($videos as $item)
                        <div class="py-2"><a class="font-medium text-emerald-700 underline" href="{{ $item->video_url }}" target="_blank" rel="noreferrer">Mở video</a> · <x-status-badge :status="$item->quality_status" /></div>
                    @empty
                        <div class="py-4 text-slate-600">Chưa có video. Hãy lưu link video giám sát sau ca thi.</div>
                    @endforelse
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
