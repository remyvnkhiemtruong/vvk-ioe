<?php

namespace App\Http\Controllers\Proctor;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('proctor.dashboard', [
            'assignments' => auth()->user()->proctorAssignments()->with(['session.exam', 'room'])->latest()->get(),
        ]);
    }
}
