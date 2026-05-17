<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">Yêu cầu cấp lại mật khẩu</h1></x-slot>
    <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
        @if(session('status'))
            <div class="rounded bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        <div class="space-y-3">
            @foreach($requests as $item)
                <div class="rounded-lg border border-slate-200 bg-white p-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div class="font-semibold">{{ $item->student?->full_name ?? 'Không rõ học sinh' }}</div>
                            <div class="text-sm text-slate-600">{{ $item->student?->class_name }} · {{ $item->user?->email ?? $item->user?->username }}</div>
                            <div class="mt-2 text-sm text-slate-600">{{ $item->request_note }}</div>
                        </div>
                        <x-status-badge :status="$item->status" />
                    </div>

                    @if($item->status === 'pending')
                        <form method="POST" action="{{ route('admin.password_reset_requests.resolve', $item) }}" class="mt-4 grid gap-3 md:grid-cols-4">
                            @csrf
                            <input name="temporary_password" type="password" placeholder="Mật khẩu tạm thời" required class="rounded-md border-slate-300">
                            <input name="temporary_password_confirmation" type="password" placeholder="Nhập lại mật khẩu" required class="rounded-md border-slate-300">
                            <input name="admin_note" placeholder="Ghi chú xử lý" class="rounded-md border-slate-300">
                            <button class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Đặt mật khẩu</button>
                        </form>
                    @else
                        <div class="mt-3 text-sm text-slate-600">Đã xử lý bởi {{ $item->resolver?->name ?? '-' }} lúc {{ $item->resolved_at?->format('d/m/Y H:i') ?? '-' }}.</div>
                    @endif
                </div>
            @endforeach
        </div>

        {{ $requests->links() }}
    </div>
</x-app-layout>
