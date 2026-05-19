<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">Preview Reset & Import</h1></x-slot>

    @php($report = $batch->report ?? [])

    <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
        @if(session('status'))
            <div class="rounded border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>
        @endif

        <div class="grid gap-4 md:grid-cols-4">
            <x-stat-card label="Tổng dòng" :value="$batch->total_rows" tone="slate" />
            <x-stat-card label="Hợp lệ" :value="$batch->valid_rows" tone="emerald" />
            <x-stat-card label="Có lỗi" :value="$batch->invalid_rows" tone="rose" />
            <x-stat-card label="Sẽ xóa" :value="$report['cleared_total'] ?? 0" tone="amber" />
        </div>

        @if($batch->status === 'committed')
            <section class="rounded-lg border border-emerald-200 bg-white p-5">
                <h2 class="text-lg font-semibold text-emerald-800">Báo cáo hoàn tất</h2>
                <div class="mt-4 grid gap-3 md:grid-cols-4">
                    <div class="rounded border border-slate-200 p-3"><div class="text-xs text-slate-500">Đã import</div><div class="text-2xl font-semibold">{{ $report['committed_rows'] ?? 0 }}</div></div>
                    <div class="rounded border border-slate-200 p-3"><div class="text-xs text-slate-500">Tạo mới</div><div class="text-2xl font-semibold">{{ $report['created'] ?? 0 }}</div></div>
                    <div class="rounded border border-slate-200 p-3"><div class="text-xs text-slate-500">Cập nhật</div><div class="text-2xl font-semibold">{{ $report['updated'] ?? 0 }}</div></div>
                    <div class="rounded border border-slate-200 p-3"><div class="text-xs text-slate-500">Đã xóa</div><div class="text-2xl font-semibold">{{ $report['cleared_total'] ?? 0 }}</div></div>
                </div>
            </section>
        @else
            <section class="rounded-lg border border-rose-200 bg-white p-5">
                <h2 class="text-lg font-semibold">Xác nhận Clear & Import</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                    Chỉ commit khi file không còn dòng lỗi. Nếu quá trình import gặp lỗi nghiêm trọng, toàn bộ thao tác clear/import sẽ rollback.
                </p>
                <form method="POST" action="{{ route('admin.students.reset_import.commit', $batch) }}" class="mt-4 space-y-4">
                    @csrf
                    <label class="flex items-start gap-3 rounded border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900">
                        <input type="checkbox" name="confirm_reset_import" value="1" class="mt-1 rounded border-rose-300 text-rose-700" required>
                        <span>Tôi hiểu thao tác này sẽ xóa dữ liệu nghiệp vụ cũ và nhập lại dữ liệu mới.</span>
                    </label>
                    <button class="rounded bg-rose-700 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-800" @disabled($batch->invalid_rows > 0)>
                        Clear & Import
                    </button>
                </form>
            </section>
        @endif

        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <h2 class="font-semibold">Bảng sẽ {{ $batch->status === 'committed' ? 'đã' : 'được' }} xóa</h2>
            <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                @foreach(($report['cleared'] ?? []) as $table => $count)
                    <div class="flex justify-between rounded border border-slate-200 px-3 py-2 text-sm">
                        <span>{{ $table }}</span>
                        <span class="font-semibold">{{ $count }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="p-3 text-left">Dòng</th>
                        <th class="p-3 text-left">Họ tên</th>
                        <th class="p-3 text-left">Lớp</th>
                        <th class="p-3 text-left">Khối</th>
                        <th class="p-3 text-left">Mã HS</th>
                        <th class="p-3 text-left">Lỗi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach(array_slice($batch->preview_rows ?? [], 0, 200) as $row)
                        <tr class="{{ ($row['valid'] ?? false) ? '' : 'bg-rose-50' }}">
                            <td class="p-3">{{ $row['row'] }}</td>
                            <td class="p-3">{{ $row['data']['full_name'] ?? '' }}</td>
                            <td class="p-3">{{ $row['data']['class_name'] ?? '' }}</td>
                            <td class="p-3">{{ $row['data']['grade'] ?? '' }}</td>
                            <td class="p-3">{{ $row['data']['student_code'] ?? '' }}</td>
                            <td class="p-3 text-rose-700">{{ implode('; ', $row['errors'] ?? []) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
