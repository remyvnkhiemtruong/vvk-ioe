<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">Nhật ký hoạt động</h1></x-slot>
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <table class="min-w-full text-sm"><thead class="bg-slate-50"><tr><th class="p-3 text-left">Thời gian</th><th class="p-3 text-left">Hành động</th><th class="p-3 text-left">Đối tượng</th><th class="p-3 text-left">IP</th></tr></thead><tbody class="divide-y divide-slate-100">@foreach($logs as $log)<tr><td class="p-3">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td><td class="p-3 font-medium">{{ $log->action }}</td><td class="p-3">{{ $log->entity_type }} #{{ $log->entity_id }}</td><td class="p-3">{{ $log->ip_address }}</td></tr>@endforeach</tbody></table>
        </div>
        {{ $logs->links() }}
    </div>
</x-app-layout>
