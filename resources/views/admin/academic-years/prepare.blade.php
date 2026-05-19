<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Chuẩn bị năm học mới {{ $year }}</h1>
                <p class="mt-1 text-sm text-slate-600">Reset dữ liệu nghiệp vụ {{ $deleteYear }}, import roster {{ $year }}, không tự tạo kỳ thi.</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Dashboard</a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        @if(session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900" role="alert">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-md border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <x-stat-card label="Học sinh {{ $year }}" :value="$stats['students']" tone="blue" />
            <x-stat-card label="Lớp" :value="$stats['classes']" tone="emerald" />
            <x-stat-card label="Khối" :value="$stats['grades']" tone="slate" />
            <x-stat-card label="Tài khoản học sinh" :value="$stats['accounts']" tone="amber" />
            <x-stat-card label="Kỳ thi {{ $year }}" :value="$stats['exams']" tone="rose" />
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-semibold">Stepper chuẩn bị năm học</h2>
            <div class="mt-5 grid gap-3 md:grid-cols-6">
                @foreach(['Kiểm tra dữ liệu', 'Upload Excel', 'Preview', 'Dry-run reset', 'Xác nhận', 'Report'] as $index => $label)
                    <div class="rounded-md border border-slate-200 bg-slate-50 p-3 text-sm">
                        <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-700 text-xs font-semibold text-white">{{ $index + 1 }}</div>
                        <div class="mt-2 font-semibold text-slate-800">{{ $label }}</div>
                    </div>
                @endforeach
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-[1fr_.9fr]">
            <section class="rounded-lg border border-slate-200 bg-white p-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold">1. Upload và preview roster {{ $year }}</h2>
                        <p class="mt-1 text-sm text-slate-600">Chọn nhiều file xlsx/xls/csv. Hệ thống tự nhận diện sheet/header và chỉ import danh sách học sinh.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.academic-years.prepare.preview') }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                    @csrf
                    <input type="hidden" name="year" value="{{ $year }}">
                    <label for="files" class="block rounded-lg border-2 border-dashed border-slate-300 bg-slate-50 p-6 text-center transition hover:border-blue-400">
                        <span class="block text-sm font-semibold text-slate-800">Tải file Excel</span>
                        <span class="mt-1 block text-xs text-slate-500">Hỗ trợ .xlsx, .xls, .csv, tối đa 20MB/file</span>
                        <input id="files" name="files[]" type="file" multiple accept=".xlsx,.xls,.csv" class="mt-4 block w-full text-sm">
                    </label>
                    <div class="flex justify-end">
                        <x-primary-button>Xem trước dữ liệu</x-primary-button>
                    </div>
                </form>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="text-lg font-semibold">Dry-run reset {{ $deleteYear }}</h2>
                <p class="mt-1 text-sm text-slate-600">Thiếu xác nhận thì command và UI chỉ report, không xóa dữ liệu.</p>
                <div class="mt-4 max-h-80 overflow-auto rounded-md border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-3 py-2">Bảng</th>
                                <th class="px-3 py-2 text-right">Sẽ xử lý</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($resetReport['counts'] as $table => $count)
                                <tr>
                                    <td class="px-3 py-2 font-medium">{{ $table }}</td>
                                    <td class="px-3 py-2 text-right">{{ $count }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <section class="rounded-lg border border-rose-200 bg-white p-5">
            <h2 class="text-lg font-semibold text-rose-900">2. Xóa {{ $deleteYear }} và seed {{ $year }}</h2>
            <p class="mt-1 text-sm text-slate-600">Thao tác này chạy trong transaction, giữ admin/users/roles/permissions/settings và detach user học sinh cũ thay vì xóa.</p>

            <form method="POST" action="{{ route('admin.academic-years.prepare.run') }}" enctype="multipart/form-data" class="mt-5 grid gap-4 md:grid-cols-2">
                @csrf
                <input type="hidden" name="year" value="{{ $year }}">
                <input type="hidden" name="delete_year" value="{{ $deleteYear }}">
                <input type="hidden" name="batch_id" value="{{ session('preview_batch_id') }}">

                <div class="md:col-span-2">
                    <x-input-label for="path" value="Hoặc nhập đường dẫn thư mục/file roster trên server" />
                    <x-text-input id="path" name="path" class="mt-1 block w-full" placeholder="storage/app/imports/2026-2027 hoặc C:\..." />
                    <p class="mt-1 text-xs text-slate-500">Nếu vừa preview file upload, có thể để trống để dùng batch preview gần nhất.</p>
                </div>

                <div class="md:col-span-2">
                    <x-input-label for="logo" value="Logo trường (tùy chọn)" />
                    <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp" class="mt-1 block w-full text-sm">
                </div>

                <label class="md:col-span-2 flex items-start gap-3 rounded-md border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900">
                    <input type="checkbox" name="confirm_understanding" value="1" class="mt-1 rounded border-rose-300 text-rose-700 focus:ring-rose-600">
                    <span>Tôi hiểu thao tác này sẽ xóa dữ liệu nghiệp vụ {{ $deleteYear }} theo phạm vi an toàn và không xóa admin/roles/permissions/settings.</span>
                </label>

                <div>
                    <x-input-label for="confirm_text" value="Nhập chuỗi xác nhận" />
                    <x-text-input id="confirm_text" name="confirm_text" class="mt-1 block w-full" placeholder="XOA-2025-2026" autocomplete="off" />
                </div>

                <div class="flex items-end justify-end">
                    <button type="submit" class="rounded-md bg-rose-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-rose-800 focus:outline-none focus:ring-2 focus:ring-rose-600 focus:ring-offset-2">
                        Xóa 2025-2026 và seed 2026-2027
                    </button>
                </div>
            </form>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-semibold">Report gần đây</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Batch</th>
                            <th class="px-3 py-2">Type</th>
                            <th class="px-3 py-2">Rows</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Report</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($batches as $batch)
                            <tr>
                                <td class="px-3 py-2">#{{ $batch->id }}</td>
                                <td class="px-3 py-2">{{ $batch->type }}</td>
                                <td class="px-3 py-2">{{ $batch->valid_rows }}/{{ $batch->total_rows }}</td>
                                <td class="px-3 py-2">{{ $batch->status }}</td>
                                <td class="px-3 py-2"><a class="font-semibold text-blue-700" href="{{ route('admin.academic-years.prepare.report', $batch) }}">Tải report</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-3 py-6 text-center text-slate-500">Chưa có batch prepare.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
