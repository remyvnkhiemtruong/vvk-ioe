<?php

namespace App\Providers;

use App\Models\Checkin;
use App\Models\Exam;
use App\Models\ExamRegistration;
use App\Models\ExamScore;
use App\Models\ExamSession;
use App\Models\Incident;
use App\Models\SeatAssignment;
use App\Models\Student;
use App\Observers\ActivityObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ([Student::class, Exam::class, ExamSession::class, ExamRegistration::class, SeatAssignment::class, Checkin::class, Incident::class, ExamScore::class] as $model) {
            $model::observe(ActivityObserver::class);
        }
    }
}
