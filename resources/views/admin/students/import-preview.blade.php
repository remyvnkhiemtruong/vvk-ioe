<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">Preview import học sinh</h1></x-slot>
    <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
        <div class="grid gap-4 md:grid-cols-3">
            <x-stat-card label="Tổng dòng" :value="$batch->total_rows" tone="slate" />
            <x-stat-card label="Hợp lệ" :value="$batch->valid_rows" tone="emerald" />
            <x-stat-card label="Có lỗi" :value="$batch->invalid_rows" tone="rose" />
        </div>
        <form method="POST" action="{{ route('admin.students.import.commit', $batch) }}">
            @csrf
            <x-primary-button @disabled($batch->invalid_rows > 0)>Lưu dữ liệu hợp lệ</x-primary-button>
        </form>
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50"><tr><th class="p-3 text-left">Dòng</th><th class="p-3 text-left">Họ tên</th><th class="p-3 text-left">Lớp</th><th class="p-3 text-left">Khối</th><th class="p-3 text-left">Lỗi</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach(array_slice($batch->preview_rows ?? [], 0, 100) as $row)
                        <tr><td class="p-3">{{ $row['row'] }}</td><td class="p-3">{{ $row['data']['full_name'] ?? '' }}</td><td class="p-3">{{ $row['data']['class_name'] ?? '' }}</td><td class="p-3">{{ $row['data']['grade'] ?? '' }}</td><td class="p-3 text-rose-700">{{ implode('; ', $row['errors'] ?? []) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
