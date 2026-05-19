<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AssignmentController;
use App\Http\Controllers\Admin\CheckinController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ExamController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\FormFieldController;
use App\Http\Controllers\Admin\IncidentController;
use App\Http\Controllers\Admin\MonitoringController;
use App\Http\Controllers\Admin\PasswordResetRequestController;
use App\Http\Controllers\Admin\ProctorAssignmentController;
use App\Http\Controllers\Admin\RegistrationController as AdminRegistrationController;
use App\Http\Controllers\Admin\ResearchController;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\Admin\ScoreController;
use App\Http\Controllers\Admin\SessionController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StaffAccountController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\StudentImportController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Proctor\DashboardController as ProctorDashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicLeaderboardController;
use App\Http\Controllers\StudentCodeLookupController;
use App\Http\Controllers\Student\ProfileController as StudentProfileController;
use App\Http\Controllers\Student\RegistrationController as StudentRegistrationController;
use App\Http\Controllers\LiveController;
use App\Http\Controllers\Admin\ExamStudentController;
use App\Http\Controllers\Admin\ExamCodeController;
use App\Http\Controllers\Admin\LiveScreenController;
use App\Http\Controllers\Admin\ScoreEntryController;
use App\Http\Controllers\Admin\RankingController;
use App\Http\Controllers\Admin\AwardController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::view('/huong-dan', 'pages.guide')->name('guide');
Route::view('/lien-he', 'pages.contact')->name('contact');
Route::get('/tra-cuu-ma-hoc-sinh', [StudentCodeLookupController::class, 'create'])->name('student_code.lookup');
Route::post('/tra-cuu-ma-hoc-sinh', [StudentCodeLookupController::class, 'store'])->middleware('throttle:10,1')->name('student_code.lookup.store');
Route::get('/bang-xep-hang', [PublicLeaderboardController::class, 'index'])->name('public.leaderboard');
Route::get('/bang-xep-hang/{exam}', [PublicLeaderboardController::class, 'show'])->name('public.leaderboard.exam');

// ── Live screen – public với token (không auth) ──────────────────────────────
Route::get('/live/{token}', [LiveController::class, 'show'])->name('live.show');
Route::get('/live/{token}/state', [LiveController::class, 'state'])->name('live.state');

Route::get('/dashboard', function () {
    $user = auth()->user();

    return match ($user?->role) {
        'admin', 'super_admin', 'exam_admin', 'teacher' => redirect()->route('admin.dashboard'),
        'proctor' => redirect()->route('proctor.dashboard'),
        default => redirect()->route('student.dashboard'),
    };
})->middleware(['auth'])->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'role:student'])->prefix('student')->name('student.')->group(function () {
    Route::get('/dashboard', [StudentRegistrationController::class, 'dashboard'])->name('dashboard');
    Route::get('/profile', [StudentProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [StudentProfileController::class, 'update'])->name('profile.update');
    Route::get('/exams/{exam}/register', [StudentRegistrationController::class, 'create'])->name('registrations.create');
    Route::post('/exams/{exam}/register', [StudentRegistrationController::class, 'store'])->name('registrations.store');
    Route::get('/registrations/{registration}', [StudentRegistrationController::class, 'show'])->name('registrations.show');
    Route::get('/registrations/{registration}/edit', [StudentRegistrationController::class, 'edit'])->name('registrations.edit');
    Route::put('/registrations/{registration}', [StudentRegistrationController::class, 'update'])->name('registrations.update');
    Route::get('/registrations/{registration}/ticket', [StudentRegistrationController::class, 'ticket'])->name('registrations.ticket');
});

Route::middleware(['auth', 'role:admin|super_admin|exam_admin|teacher'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', AdminDashboardController::class)->middleware('permission:dashboard.view')->name('dashboard');

    Route::get('/settings', [SettingsController::class, 'index'])->middleware('permission:settings.manage')->name('settings.index');
    Route::put('/settings', [SettingsController::class, 'update'])->middleware('permission:settings.manage')->name('settings.update');
    Route::post('/settings/test-mail', [SettingsController::class, 'testMail'])->middleware('permission:settings.manage')->name('settings.test_mail');

    Route::get('/students', [StudentController::class, 'index'])->middleware('permission:students.view')->name('students.index');
    Route::post('/students', [StudentController::class, 'store'])->middleware('permission:students.import')->name('students.store');
    Route::put('/students/{student}', [StudentController::class, 'update'])->middleware('permission:students.import')->name('students.update');
    Route::post('/students/{student}/toggle', [StudentController::class, 'toggle'])->middleware('permission:students.import')->name('students.toggle');
    Route::get('/students/export', [StudentController::class, 'export'])->middleware('permission:students.export')->name('students.export');
    Route::get('/students/import', [StudentImportController::class, 'create'])->middleware('permission:students.import')->name('students.import');
    Route::post('/students/import/preview', [StudentImportController::class, 'preview'])->middleware('permission:students.import')->name('students.import.preview');
    Route::get('/students/import/{batch}', [StudentImportController::class, 'show'])->middleware('permission:students.import')->name('students.import.show');
    Route::post('/students/import/{batch}/commit', [StudentImportController::class, 'commit'])->middleware('permission:students.import')->name('students.import.commit');
    Route::get('/students/reset-import', [StudentImportController::class, 'resetCreate'])->middleware('permission:students.import')->name('students.reset_import');
    Route::post('/students/reset-import/preview', [StudentImportController::class, 'resetPreview'])->middleware('permission:students.import')->name('students.reset_import.preview');
    Route::get('/students/reset-import/{batch}', [StudentImportController::class, 'resetShow'])->middleware('permission:students.import')->name('students.reset_import.show');
    Route::post('/students/reset-import/{batch}/commit', [StudentImportController::class, 'resetCommit'])->middleware('permission:students.import')->name('students.reset_import.commit');

    Route::get('/exams', [ExamController::class, 'index'])->middleware('permission:exams.manage')->name('exams.index');
    Route::post('/exams', [ExamController::class, 'store'])->middleware('permission:exams.manage')->name('exams.store');
    Route::put('/exams/{exam}', [ExamController::class, 'update'])->middleware('permission:exams.manage')->name('exams.update');
    Route::post('/exams/{exam}/open', [ExamController::class, 'open'])->middleware('permission:exams.manage')->name('exams.open');
    Route::post('/exams/{exam}/close', [ExamController::class, 'close'])->middleware('permission:exams.manage')->name('exams.close');
    Route::post('/exams/{exam}/lock', [ExamController::class, 'lock'])->middleware('permission:exams.manage')->name('exams.lock');
    Route::post('/exams/{exam}/publish-scores', [ExamController::class, 'publishScores'])->middleware('permission:exams.manage')->name('exams.publish_scores');

    Route::get('/form-fields', [FormFieldController::class, 'index'])->middleware('permission:form.manage')->name('form_fields.index');
    Route::post('/form-fields', [FormFieldController::class, 'store'])->middleware('permission:form.manage')->name('form_fields.store');
    Route::put('/form-fields/{field}', [FormFieldController::class, 'update'])->middleware('permission:form.manage')->name('form_fields.update');

    Route::get('/registrations', [AdminRegistrationController::class, 'index'])->middleware('permission:registrations.view')->name('registrations.index');
    Route::post('/registrations/{registration}/approve', [AdminRegistrationController::class, 'approve'])->middleware('permission:registrations.approve')->name('registrations.approve');
    Route::post('/registrations/{registration}/reject', [AdminRegistrationController::class, 'reject'])->middleware('permission:registrations.approve')->name('registrations.reject');
    Route::post('/registrations/{registration}/cancel', [AdminRegistrationController::class, 'cancel'])->middleware('permission:registrations.update')->name('registrations.cancel');
    Route::post('/registrations/{registration}/restore', [AdminRegistrationController::class, 'restore'])->middleware('permission:registrations.update')->name('registrations.restore');
    Route::patch('/registrations/{registration}/device', [AdminRegistrationController::class, 'device'])->middleware('permission:devices.approve')->name('registrations.device');

    Route::get('/rooms', [RoomController::class, 'index'])->middleware('permission:rooms.manage')->name('rooms.index');
    Route::post('/rooms', [RoomController::class, 'store'])->middleware('permission:rooms.manage')->name('rooms.store');
    Route::get('/rooms/{room}', [RoomController::class, 'show'])->middleware('permission:rooms.manage')->name('rooms.show');

    Route::get('/sessions', [SessionController::class, 'index'])->middleware('permission:sessions.manage')->name('sessions.index');
    Route::post('/sessions', [SessionController::class, 'store'])->middleware('permission:sessions.manage')->name('sessions.store');
    Route::post('/sessions/bulk', [SessionController::class, 'bulk'])->middleware('permission:sessions.manage')->name('sessions.bulk');
    Route::post('/sessions/{session}/duplicate', [SessionController::class, 'duplicate'])->middleware('permission:sessions.manage')->name('sessions.duplicate');
    Route::delete('/sessions/{session}', [SessionController::class, 'destroy'])->middleware('permission:sessions.manage')->name('sessions.destroy');

    Route::get('/assignments', [AssignmentController::class, 'index'])->middleware('permission:assignments.manage')->name('assignments.index');
    Route::post('/assignments', [AssignmentController::class, 'store'])->middleware('permission:assignments.manage')->name('assignments.store');
    Route::patch('/assignments/{assignment}/move', [AssignmentController::class, 'move'])->middleware('permission:assignments.manage')->name('assignments.move');

    Route::get('/proctors', [ProctorAssignmentController::class, 'index'])->middleware('permission:assignments.manage')->name('proctors.index');
    Route::post('/proctors', [ProctorAssignmentController::class, 'store'])->middleware('permission:assignments.manage')->name('proctors.store');
    Route::delete('/proctors/{assignment}', [ProctorAssignmentController::class, 'destroy'])->middleware('permission:assignments.manage')->name('proctors.destroy');

    Route::get('/checkins', [CheckinController::class, 'index'])->middleware('permission:checkins.manage')->name('checkins.index');
    Route::patch('/checkins/{assignment}', [CheckinController::class, 'update'])->middleware('permission:checkins.manage')->name('checkins.update');

    Route::get('/incidents', [IncidentController::class, 'index'])->middleware('permission:incidents.manage')->name('incidents.index');
    Route::post('/incidents', [IncidentController::class, 'store'])->middleware('permission:incidents.manage')->name('incidents.store');

    Route::get('/monitoring', [MonitoringController::class, 'index'])->middleware('permission:attendance.manage')->name('monitoring.index');
    Route::post('/monitoring/checklist', [MonitoringController::class, 'checklist'])->middleware('permission:attendance.manage')->name('monitoring.checklist');
    Route::post('/monitoring/minutes', [MonitoringController::class, 'minute'])->middleware('permission:minutes.upload')->name('monitoring.minute');
    Route::post('/monitoring/videos', [MonitoringController::class, 'video'])->middleware('permission:minutes.upload')->name('monitoring.video');

    Route::get('/scores', [ScoreController::class, 'index'])->middleware('permission:scores.enter')->name('scores.index');
    Route::post('/scores/{registration}', [ScoreController::class, 'store'])->middleware('permission:scores.enter')->name('scores.store');
    Route::post('/scores/{score}/verify', [ScoreController::class, 'verify'])->middleware('permission:scores.verify')->name('scores.verify');
    Route::post('/scores/{score}/lock', [ScoreController::class, 'lock'])->middleware('permission:scores.lock')->name('scores.lock');

    Route::get('/research', [ResearchController::class, 'index'])->middleware('permission:research.manage')->name('research.index');
    Route::post('/research/documents', [ResearchController::class, 'storeDocument'])->middleware('permission:research.manage')->name('research.documents.store');
    Route::post('/research/checklists', [ResearchController::class, 'storeChecklist'])->middleware('permission:research.manage')->name('research.checklists.store');
    Route::patch('/research/checklists/{checklist}/toggle', [ResearchController::class, 'toggleChecklist'])->middleware('permission:research.manage')->name('research.checklists.toggle');

    Route::middleware('permission:exports.manage')->group(function () {
        Route::get('/exports/registrations', [ExportController::class, 'registrations'])->name('exports.registrations');
        Route::get('/exports/rooms', [ExportController::class, 'rooms'])->name('exports.rooms');
        Route::get('/exports/checkins', [ExportController::class, 'checkins'])->name('exports.checkins');
        Route::get('/exports/checkins-pdf', [ExportController::class, 'checkinsPdf'])->name('exports.checkins.pdf');
        Route::get('/exports/scores', [ExportController::class, 'scores'])->name('exports.scores');
        Route::get('/exports/scores-pdf', [ExportController::class, 'scoresPdf'])->name('exports.scores.pdf');
        Route::get('/exports/byod', [ExportController::class, 'byod'])->name('exports.byod');
        Route::get('/exports/byod-pdf', [ExportController::class, 'byodPdf'])->name('exports.byod.pdf');
        Route::get('/exports/absent', [ExportController::class, 'absent'])->name('exports.absent');
        Route::get('/exports/absent-pdf', [ExportController::class, 'absentPdf'])->name('exports.absent.pdf');
        Route::get('/exports/technical-incidents', [ExportController::class, 'technicalIncidents'])->name('exports.technical_incidents');
        Route::get('/exports/technical-incidents-pdf', [ExportController::class, 'technicalIncidentsPdf'])->name('exports.technical_incidents.pdf');
        Route::get('/exports/assignments-pdf', [ExportController::class, 'assignmentsPdf'])->name('exports.assignments.pdf');
        Route::get('/exports/proctors', [ExportController::class, 'proctors'])->name('exports.proctors');
        Route::get('/exports/incidents-docx', [ExportController::class, 'incidentsDocx'])->name('exports.incidents.docx');
    });

    Route::get('/password-reset-requests', [PasswordResetRequestController::class, 'index'])->middleware('permission:users.manage')->name('password_reset_requests.index');
    Route::post('/password-reset-requests/{passwordResetRequest}/resolve', [PasswordResetRequestController::class, 'resolve'])->middleware('permission:users.manage')->name('password_reset_requests.resolve');

    Route::get('/activity', [ActivityLogController::class, 'index'])->middleware('permission:activity.view')->name('activity.index');

    // ── Nhân sự & Tài khoản giáo viên ────────────────────────────────────────
    Route::prefix('staff')->name('staff.')->middleware('permission:staff.manage')->group(function () {
        Route::get('/', [StaffAccountController::class, 'index'])->name('index');
        Route::post('/bulk-accounts', [StaffAccountController::class, 'bulk'])->name('account.bulk');
        Route::post('/{staff}/account', [StaffAccountController::class, 'store'])->name('account.store');
        Route::delete('/{staff}/account', [StaffAccountController::class, 'unlink'])->name('account.unlink');
    });

    // ── Danh sách học sinh nội bộ (v2) ───────────────────────────────────────
    Route::prefix('exams/{exam}/students')->name('exam-students.')->middleware('permission:exams.manage')->group(function () {
        Route::get('/', [ExamStudentController::class, 'index'])->name('index');
        Route::post('/', [ExamStudentController::class, 'store'])->name('store');
        Route::patch('/{examStudent}', [ExamStudentController::class, 'update'])->name('update');
        Route::delete('/{examStudent}', [ExamStudentController::class, 'destroy'])->name('destroy');
        Route::post('/check-eligibility', [ExamStudentController::class, 'checkAll'])->name('check_all');
        Route::post('/{examStudent}/check', [ExamStudentController::class, 'check'])->name('check');
        Route::post('/{examStudent}/mark-ioe', [ExamStudentController::class, 'markRegisteredOnIoe'])->name('mark_ioe');
    });

    // ── Mã ca thi (v2) ───────────────────────────────────────────────────────
    Route::prefix('exams/{exam}/codes')->name('exam-codes.')->middleware('permission:exams.manage')->group(function () {
        Route::get('/', [ExamCodeController::class, 'index'])->name('index');
        Route::post('/', [ExamCodeController::class, 'store'])->name('store');
        Route::put('/{examCode}', [ExamCodeController::class, 'update'])->name('update');
        Route::delete('/{examCode}', [ExamCodeController::class, 'destroy'])->name('destroy');
    });

    // ── Live screen admin (v2) ───────────────────────────────────────────────
    Route::prefix('exams/{exam}/live')->name('live-screens.')->middleware('permission:exams.manage')->group(function () {
        Route::get('/', [LiveScreenController::class, 'index'])->name('index');
        Route::post('/', [LiveScreenController::class, 'store'])->name('store');
        Route::delete('/{liveScreen}', [LiveScreenController::class, 'destroy'])->name('destroy');
        Route::patch('/{liveScreen}/toggle', [LiveScreenController::class, 'toggle'])->name('toggle');
        Route::patch('/{liveScreen}/override', [LiveScreenController::class, 'override'])->name('override');
    });

    // ── Nhập điểm sau thi (v2) ───────────────────────────────────────────────
    Route::prefix('exams/{exam}/scores')->name('score-entry.')->middleware('permission:scores.enter')->group(function () {
        Route::get('/', [ScoreEntryController::class, 'index'])->name('index');
        Route::post('/', [ScoreEntryController::class, 'store'])->name('store');
        Route::put('/{studentScore}', [ScoreEntryController::class, 'update'])->name('update');
        Route::post('/{studentScore}/submit', [ScoreEntryController::class, 'submit'])->name('submit');
        Route::post('/{studentScore}/lock', [ScoreEntryController::class, 'lock'])->name('lock')->middleware('permission:scores.lock');
        Route::post('/{studentScore}/unlock', [ScoreEntryController::class, 'unlock'])->name('unlock')->middleware('permission:scores.lock');
    });

    // ── Xếp hạng & xếp giải (v2) ────────────────────────────────────────────
    Route::prefix('exams/{exam}')->name('exam.')->middleware('permission:exams.manage')->group(function () {
        Route::get('/rankings', [RankingController::class, 'index'])->name('rankings.index');
        Route::post('/rankings/run', [RankingController::class, 'run'])->name('rankings.run');
        Route::get('/awards', [AwardController::class, 'index'])->name('awards.index');
        Route::post('/awards/run', [AwardController::class, 'run'])->name('awards.run');
        Route::put('/awards/rules/{awardRule}', [AwardController::class, 'updateRule'])->name('awards.rules.update');
    });
});

Route::middleware(['auth', 'role:proctor|admin|teacher'])->prefix('proctor')->name('proctor.')->group(function () {
    Route::get('/dashboard', ProctorDashboardController::class)->name('dashboard');
    Route::get('/checkins', [CheckinController::class, 'index'])->middleware('permission:checkins.manage')->name('checkins.index');
    Route::patch('/checkins/{assignment}', [CheckinController::class, 'update'])->middleware('permission:checkins.manage')->name('checkins.update');
    Route::get('/incidents', [IncidentController::class, 'index'])->middleware('permission:incidents.manage')->name('incidents.index');
    Route::post('/incidents', [IncidentController::class, 'store'])->middleware('permission:incidents.manage')->name('incidents.store');
    Route::get('/scores', [ScoreController::class, 'index'])->middleware('permission:scores.enter')->name('scores.index');
    Route::post('/scores/{registration}', [ScoreController::class, 'store'])->middleware('permission:scores.enter')->name('scores.store');
});

require __DIR__.'/auth.php';
