<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">Phòng thi và máy tính</h1></x-slot>
    <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
        <form method="POST" action="{{ route('admin.rooms.store') }}" class="grid gap-3 rounded-lg border border-slate-200 bg-white p-4 md:grid-cols-6">
            @csrf
            <input name="room_code" placeholder="Mã phòng" required class="rounded-md border-slate-300">
            <input name="room_name" placeholder="Tên phòng" required class="rounded-md border-slate-300 md:col-span-2">
            <input name="usable_computers" type="number" min="0" placeholder="Máy chính" required class="rounded-md border-slate-300">
            <input name="backup_computers" type="number" min="0" placeholder="Máy dự phòng" required class="rounded-md border-slate-300">
            <button class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Tạo phòng</button>
        </form>
        <div class="grid gap-4 md:grid-cols-2">
            @foreach($rooms as $room)
                <a href="{{ route('admin.rooms.show', $room) }}" class="rounded-lg border border-slate-200 bg-white p-5 hover:bg-slate-50">
                    <h2 class="font-semibold">{{ $room->room_name }}</h2>
                    <p class="text-sm text-slate-600">{{ $room->room_code }} · {{ $room->usable_computers }} máy chính · {{ $room->backup_computers }} máy dự phòng</p>
                </a>
            @endforeach
        </div>
        {{ $rooms->links() }}
    </div>
</x-app-layout>
