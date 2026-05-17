<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Services\LandingStateService;
use App\Services\SystemSettingService;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(LandingStateService $landing, SystemSettingService $settings): View
    {
        $exam = Schema::hasTable('exams')
            ? Exam::where('school_year', $settings->schoolYear())
                ->whereIn('status', SystemSettingService::ACTIVE_LANDING_STATUSES)
                ->with(['sessions.room'])
                ->latest('id')
                ->first()
            : null;

        return view('welcome', [
            'exam' => $exam,
            'landingState' => $landing->state($exam),
            'publicStats' => $landing->stats($exam),
            'publicSessions' => $landing->availableSessionsForGuest($exam),
            'settings' => $settings,
            'contact' => $settings->contact(),
            'account' => $settings->accountOptions(),
        ]);
    }
}
