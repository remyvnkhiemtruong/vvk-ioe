<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamRoom;
use App\Models\RoomComputer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoomController extends Controller
{
    public function index(): View
    {
        return view('admin.rooms.index', ['rooms' => ExamRoom::withCount('computers')->paginate(15)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'room_code' => ['required', 'string', 'max:50', 'unique:exam_rooms,room_code'],
            'room_name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'usable_computers' => ['required', 'integer', 'min:0'],
            'backup_computers' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string'],
        ]);

        $room = ExamRoom::create($data);

        for ($i = 1; $i <= $room->usable_computers; $i++) {
            RoomComputer::create(['exam_room_id' => $room->id, 'computer_label' => 'Máy '.$i, 'computer_number' => $i, 'type' => 'main', 'status' => 'ready']);
        }

        for ($i = 1; $i <= $room->backup_computers; $i++) {
            RoomComputer::create(['exam_room_id' => $room->id, 'computer_label' => 'Máy dự phòng '.$i, 'computer_number' => $i, 'type' => 'backup', 'status' => 'ready']);
        }

        return back()->with('status', 'Đã tạo phòng thi và danh sách máy.');
    }

    public function show(ExamRoom $room): View
    {
        return view('admin.rooms.show', ['room' => $room->load('computers')]);
    }
}
