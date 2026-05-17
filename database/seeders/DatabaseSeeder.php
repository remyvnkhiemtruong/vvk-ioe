<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\ExamFormField;
use App\Models\ExamRoom;
use App\Models\RoomComputer;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $permissions = [
            'dashboard.view',
            'students.view',
            'students.view_sensitive',
            'students.import',
            'students.export',
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
            'assignments.manage',
            'assignments.lock',
            'checkins.manage',
            'incidents.manage',
            'scores.enter',
            'scores.verify',
            'scores.lock',
            'scores.unlock',
            'exports.manage',
            'research.manage',
            'users.manage',
            'activity.view',
            'settings.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $teacher = Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        $proctor = Role::firstOrCreate(['name' => 'proctor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $admin->syncPermissions($permissions);
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
            'incidents.manage',
            'scores.enter',
            'scores.verify',
            'scores.lock',
            'exports.manage',
            'research.manage',
        ]);
        $proctor->syncPermissions(['checkins.manage', 'incidents.manage', 'scores.enter']);

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

        $exam = Exam::firstOrCreate([
            'name' => 'IOE cấp trường năm học 2025-2026',
            'school_year' => '2025-2026',
        ], [
            'level' => 'school',
            'target_grades' => [10, 11, 12],
            'allow_student_edit' => true,
            'allow_student_session_change' => true,
            'require_session_choice' => true,
            'allow_personal_computer' => true,
            'auto_lock_full_sessions' => true,
            'show_public_stats' => true,
            'require_approval' => true,
            'publish_scores' => false,
            'show_countdown' => true,
            'countdown_mode' => 'auto',
            'status' => 'draft',
            'description' => 'Đăng ký dự thi Olympic Tiếng Anh trên Internet cấp trường.',
        ]);

        $defaultFields = [
            ['full_name', 'Họ và tên', 'text', true, true],
            ['ioe_id', 'ID tài khoản IOE', 'text', true, true],
            ['date_of_birth', 'Ngày sinh', 'date', true, true],
            ['gender', 'Giới tính', 'select', true, true],
            ['identity_number', 'Số CCCD/mã định danh', 'text', true, true],
            ['class_name', 'Lớp', 'text', true, true],
            ['address', 'Địa chỉ', 'textarea', true, true],
            ['phone', 'Số điện thoại học sinh', 'text', true, true],
            ['email', 'Email', 'email', true, true],
            ['uses_personal_computer', 'Sử dụng máy tính cá nhân?', 'boolean', true, true],
            ['note', 'Ghi chú', 'textarea', true, false],
        ];

        array_splice($defaultFields, 7, 0, [
            ['exam_session_id', 'Chon ca thi mong muon', 'radio', true, true],
        ]);

        foreach ($defaultFields as $index => [$key, $label, $type, $enabled, $required]) {
            ExamFormField::updateOrCreate([
                'exam_id' => $exam->id,
                'field_key' => $key,
            ], [
                'label' => $label,
                'type' => $type,
                'is_enabled' => $enabled,
                'is_required' => $required,
                'sort_order' => $index + 1,
            ]);
        }

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
