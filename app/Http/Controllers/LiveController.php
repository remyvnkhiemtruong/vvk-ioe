<?php

namespace App\Http\Controllers;

use App\Models\LiveScreen;
use App\Services\LiveScreenService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class LiveController extends Controller
{
    public function __construct(private readonly LiveScreenService $liveService) {}

    /** Trang /live/{token} – Giao diện trình chiếu */
    public function show(string $token): View|\Illuminate\Http\Response
    {
        $screen = LiveScreen::with(['exam.examLevel', 'session'])->where('token', $token)->first();

        if (! $screen) {
            abort(404, 'Màn hình live không tồn tại hoặc link đã hết hiệu lực.');
        }

        $state = $this->liveService->getCurrentLiveState($screen, Carbon::now('Asia/Ho_Chi_Minh'));

        return view('live.show', [
            'screen' => $screen,
            'state'  => $state,
            'token'  => $token,
        ]);
    }

    /**
     * API /live/{token}/state – Polling endpoint.
     *
     * BẢO MẬT: Response KHÔNG chứa 'code' khi show_code = false.
     * Client chỉ nhận được mã khi server tính toán show_code = true.
     */
    public function state(string $token): JsonResponse
    {
        $screen = LiveScreen::with(['exam.examLevel', 'session'])->where('token', $token)->first();

        if (! $screen) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $state = $this->liveService->getCurrentLiveState($screen, Carbon::now('Asia/Ho_Chi_Minh'));

        // Double-check bảo mật: đảm bảo không lộ code khi show_code = false
        if (! ($state['show_code'] ?? false)) {
            unset($state['code']); // Xóa code hoàn toàn khỏi response
        }

        return response()->json($state);
    }
}
