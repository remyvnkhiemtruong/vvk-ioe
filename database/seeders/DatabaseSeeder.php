<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\ExamRoom;
use App\Models\RoomComputer;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SystemSettingService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $permissions = [
            'dashboard.view',
            'students.view',
            'students.view_sensitive',
            'students.import',
            'students.import_roster',
            'students.export',
            'students.lookup',
            'academic_years.view',
            'academic_years.prepare',
            'academic_years.reset',
            'exams.manage',
            'form.manage',
            'registrations.view',
            'registrations.approve',
            'registrations.update',
            'registrations.delete',
            'devices.approve',
            'rooms.manage',
            'sessions.manage',
            'sessions.override_grade_restriction',
            'exam_codes.view',
            'exam_codes.update',
            'assignments.manage',
            'assignments.lock',
            'rooms.assign',
            'checkins.manage',
            'attendance.manage',
            'incidents.manage',
            'scores.enter',
            'scores.verify',
            'scores.lock',
            'scores.unlock',
            'results.enter',
            'results.review',
            'results.lock',
            'rankings.manage',
            'awards.manage',
            'leaderboard.public.manage',
            'leaderboard.public.view',
            'minutes.generate',
            'minutes.upload',
            'minutes.review',
            'reports.export',
            'exports.manage',
            'research.manage',
            'users.manage',
            'activity.view',
            'settings.manage',
            'staff.manage',
            'staff.create_account',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $examAdmin = Role::firstOrCreate(['name' => 'exam_admin', 'guard_name' => 'web']);
        $teacher = Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        $proctor = Role::firstOrCreate(['name' => 'proctor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);

        $admin->syncPermissions($permissions);
        $superAdmin->syncPermissions($permissions);
        $examAdmin->syncPermissions($permissions);
        $teacher->syncPermissions([
            'dashboard.view',
            'students.view',
            'students.export',
            'exams.manage',
            'form.manage',
            'registrations.view',
            'registrations.approve',
            'registrations.update',
            'devices.approve',
            'rooms.manage',
            'sessions.manage',
            'assignments.manage',
            'assignments.lock',
            'checkins.manage',
            'attendance.manage',
            'incidents.manage',
            'scores.enter',
            'scores.verify',
            'scores.lock',
            'results.enter',
            'results.review',
            'minutes.generate',
            'minutes.upload',
            'minutes.review',
            'exports.manage',
            'reports.export',
            'research.manage',
        ]);
        $proctor->syncPermissions(['checkins.manage', 'attendance.manage', 'incidents.manage', 'scores.enter', 'results.enter']);

        $adminUser = User::updateOrCreate([
            'email' => 'admin@example.test',
        ], [
            'name' => 'Quản trị hệ thống',
            'username' => 'admin',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => 'active',
        ]);
        $adminUser->assignRole('admin');

        AcademicYear::query()->update(['is_current' => false, 'is_active' => false]);
        AcademicYear::updateOrCreate(
            ['code' => '2026-2027'],
            [
                'name' => 'Năm học 2026 - 2027',
                'start_date' => '2026-09-01',
                'end_date' => '2027-05-31',
                'starts_at' => '2026-09-01',
                'ends_at' => '2027-05-31',
                'status' => 'current',
                'is_current' => true,
                'is_active' => true,
            ]
        );

        SystemSetting::updateOrCreate(['key' => 'school.info'], [
            'value' => [
                'name' => 'Trường THPT Võ Văn Kiệt',
                'address' => 'Tỉnh Cà Mau',
                'website' => '',
            ],
        ]);
        SystemSetting::updateOrCreate(['key' => 'site.info'], [
            'value' => [
                'site_name' => 'IOE nội bộ',
                'contest_name' => 'IOE nội bộ Trường THPT Võ Văn Kiệt',
                'school_year' => '2026-2027',
                'home_description' => 'Nhà trường đang chuẩn bị dữ liệu năm học mới. Thông tin cuộc thi sẽ được cập nhật sau.',
            ],
        ]);
        SystemSetting::updateOrCreate(['key' => 'account.options'], [
            'value' => [
                'student_registration_enabled' => true,
                'allow_ioe_id_as_credential' => false,
                'student_code_lookup_url' => '/tra-cuu-ma-hoc-sinh',
                'student_code_help' => 'Nếu chưa biết mã học sinh, hãy dùng trang tra cứu hoặc liên hệ giáo viên phụ trách.',
            ],
        ]);
        SystemSetting::updateOrCreate(['key' => 'score.options'], [
            'value' => [
                'publish_scores' => false,
                'show_ranking' => false,
                'ranking_scope' => 'school',
                'public_scoreboard' => false,
            ],
        ]);

        app(SystemSettingService::class)->storeLogoFromPath(base_path('file/LogoVVK (1).png'));

        $room = ExamRoom::updateOrCreate([
            'room_code' => 'TINHOC1',
        ], [
            'room_name' => 'Phòng Tin học 1',
            'location' => 'Khu phòng máy',
            'usable_computers' => 25,
            'backup_computers' => 10,
            'status' => 'active',
        ]);

        for ($i = 1; $i <= 25; $i++) {
            RoomComputer::updateOrCreate([
                'exam_room_id' => $room->id,
                'computer_label' => 'Máy '.$i,
            ], [
                'computer_number' => $i,
                'type' => 'main',
                'status' => 'ready',
            ]);
        }

        for ($i = 1; $i <= 10; $i++) {
            RoomComputer::updateOrCreate([
                'exam_room_id' => $room->id,
                'computer_label' => 'Máy dự phòng '.$i,
            ], [
                'computer_number' => $i,
                'type' => 'backup',
                'status' => 'ready',
            ]);
        }
    }
}
