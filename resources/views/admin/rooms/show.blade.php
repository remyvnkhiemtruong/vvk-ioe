<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">{{ $room->room_name }}</h1></x-slot>
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            @foreach($room->computers as $computer)
                <div class="rounded border border-slate-200 bg-white p-3 text-sm">
                    <div class="font-medium">{{ $computer->computer_label }}</div>
                    <div class="text-slate-500">{{ $computer->type }} · {{ $computer->status }}</div>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
