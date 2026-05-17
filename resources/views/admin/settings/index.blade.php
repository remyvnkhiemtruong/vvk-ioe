<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">Cài đặt hệ thống</h1></x-slot>
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        @if(session('status'))<div class="rounded bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="rounded bg-rose-50 p-3 text-sm text-rose-800">Vui lòng kiểm tra lại thông tin cài đặt.</div>@endif

        @php
            $school = $allSettings['school.info'] ?? [];
            $site = $allSettings['site.info'] ?? [];
            $contact = $allSettings['site.contact'] ?? [];
            $mail = $allSettings['mail.smtp'] ?? [];
            $security = $allSettings['security.options'] ?? [];
            $score = $allSettings['score.options'] ?? [];
        @endphp

        <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf @method('PUT')

            <section class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="text-lg font-semibold">Thông tin trường và website</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-3">
                    <input name="school[name]" value="{{ old('school.name', $school['name'] ?? $settings->schoolName()) }}" required placeholder="Tên trường" class="rounded-md border-slate-300">
                    <input name="school[address]" value="{{ old('school.address', $school['address'] ?? '') }}" placeholder="Địa chỉ trường" class="rounded-md border-slate-300">
                    <input name="school[website]" value="{{ old('school.website', $school['website'] ?? '') }}" placeholder="Website trường" class="rounded-md border-slate-300">
                    <input name="site[site_name]" value="{{ old('site.site_name', $site['site_name'] ?? $settings->siteName()) }}" required placeholder="Tên website" class="rounded-md border-slate-300">
                    <input name="site[contest_name]" value="{{ old('site.contest_name', $site['contest_name'] ?? $settings->contestName()) }}" required placeholder="Tên cuộc thi" class="rounded-md border-slate-300">
                    <input name="site[school_year]" value="{{ old('site.school_year', $site['school_year'] ?? $settings->schoolYear()) }}" required placeholder="Năm học" class="rounded-md border-slate-300">
                    <textarea name="site[home_description]" rows="3" placeholder="Mô tả trang chủ" class="rounded-md border-slate-300 md:col-span-2">{{ old('site.home_description', $site['home_description'] ?? '') }}</textarea>
                    <label class="rounded border border-slate-200 p-3 text-sm">Logo trường
                        <input name="logo" type="file" accept="image/*" class="mt-2 block w-full text-sm">
                    </label>
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="text-lg font-semibold">Thời gian và kỳ đăng ký hiện tại</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-4">
                    <input name="exam[name]" value="{{ old('exam.name', $exam->name) }}" required placeholder="Tên kỳ" class="rounded-md border-slate-300 md:col-span-2">
                    <input name="exam[school_year]" value="{{ old('exam.school_year', $exam->school_year) }}" required placeholder="Năm học" class="rounded-md border-slate-300">
                    <select name="exam[status]" class="rounded-md border-slate-300">
                        @foreach(['draft'=>'Nháp','open'=>'Đang mở','closed'=>'Đã đóng','assigning'=>'Đang phân phòng','locked'=>'Đã khóa danh sách','in_progress'=>'Đang thi','completed'=>'Đã hoàn thành'] as $value=>$label)
                            <option value="{{ $value }}" @selected(old('exam.status', $exam->status)===$value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <input type="datetime-local" name="exam[registration_opens_at]" value="{{ old('exam.registration_opens_at', $exam->registration_opens_at?->format('Y-m-d\\TH:i')) }}" class="rounded-md border-slate-300">
                    <input type="datetime-local" name="exam[registration_closes_at]" value="{{ old('exam.registration_closes_at', $exam->registration_closes_at?->format('Y-m-d\\TH:i')) }}" class="rounded-md border-slate-300">
                    <input type="date" name="exam[exam_date]" value="{{ old('exam.exam_date', $exam->exam_date?->format('Y-m-d')) }}" class="rounded-md border-slate-300">
                    <input type="time" name="exam[exam_time]" value="{{ old('exam.exam_time', $exam->exam_time ? \Illuminate\Support\Carbon::parse($exam->exam_time)->format('H:i') : '') }}" class="rounded-md border-slate-300">
                    <select name="exam[countdown_mode]" class="rounded-md border-slate-300">
                        @foreach(['auto'=>'Tự động theo trạng thái','open'=>'Đến mở đăng ký','close'=>'Đến đóng đăng ký','exam'=>'Đến ngày thi'] as $value=>$label)
                            <option value="{{ $value }}" @selected(old('exam.countdown_mode', $exam->countdown_mode ?? 'auto')===$value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <textarea name="exam[description]" rows="3" placeholder="Mô tả kỳ đăng ký" class="rounded-md border-slate-300 md:col-span-3">{{ old('exam.description', $exam->description) }}</textarea>
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="text-lg font-semibold">Tùy chọn đăng ký và điểm</h2>
                <div class="mt-4 grid gap-3 text-sm md:grid-cols-3">
                    @foreach([
                        'allow_student_edit' => 'Cho học sinh chỉnh sửa trước hạn',
                        'allow_student_session_change' => 'Cho học sinh đổi ca trước hạn',
                        'require_session_choice' => 'Bắt buộc học sinh chọn ca thi',
                        'allow_personal_computer' => 'Cho đăng ký máy tính cá nhân',
                        'auto_lock_full_sessions' => 'Tự khóa ca khi đủ số lượng',
                        'show_public_stats' => 'Hiển thị thống kê công khai',
                        'require_approval' => 'Cần duyệt đăng ký',
                        'show_countdown' => 'Hiển thị countdown',
                    ] as $key=>$label)
                        <label class="rounded border border-slate-200 p-3">
                            <input type="hidden" name="options[{{ $key }}]" value="0">
                            <input type="checkbox" name="options[{{ $key }}]" value="1" @checked(old("options.$key", $exam->{$key}))> {{ $label }}
                        </label>
                    @endforeach
                    <label class="rounded border border-slate-200 p-3">
                        <input type="hidden" name="score[publish_scores]" value="0">
                        <input type="checkbox" name="score[publish_scores]" value="1" @checked(old('score.publish_scores', $exam->publish_scores))> Công bố điểm cho học sinh
                    </label>
                    <label class="rounded border border-slate-200 p-3">
                        <input type="hidden" name="score[show_ranking]" value="0">
                        <input type="checkbox" name="score[show_ranking]" value="1" @checked(old('score.show_ranking', $score['show_ranking'] ?? false))> Hiển thị xếp hạng
                    </label>
                    <label class="rounded border border-slate-200 p-3">
                        <input type="hidden" name="score[public_scoreboard]" value="0">
                        <input type="checkbox" name="score[public_scoreboard]" value="1" @checked(old('score.public_scoreboard', $score['public_scoreboard'] ?? false))> Công khai bảng điểm
                    </label>
                    <select name="score[ranking_scope]" class="rounded-md border-slate-300">
                        @foreach(['class'=>'Theo lớp','grade'=>'Theo khối','school'=>'Toàn trường'] as $value=>$label)
                            <option value="{{ $value }}" @selected(old('score.ranking_scope', $score['ranking_scope'] ?? 'grade')===$value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="text-lg font-semibold">Liên hệ, SMTP và bảo mật</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-4">
                    <input name="contact[teacher_name]" value="{{ old('contact.teacher_name', $contact['teacher_name'] ?? '') }}" placeholder="Giáo viên phụ trách" class="rounded-md border-slate-300">
                    <input name="contact[phone]" value="{{ old('contact.phone', $contact['phone'] ?? '') }}" placeholder="Số điện thoại" class="rounded-md border-slate-300">
                    <input name="contact[email]" value="{{ old('contact.email', $contact['email'] ?? '') }}" placeholder="Email liên hệ" class="rounded-md border-slate-300">
                    <input name="security[auto_logout_minutes]" type="number" min="5" value="{{ old('security.auto_logout_minutes', $security['auto_logout_minutes'] ?? 120) }}" placeholder="Tự đăng xuất sau phút" class="rounded-md border-slate-300">
                    <textarea name="contact[note]" rows="2" placeholder="Ghi chú liên hệ" class="rounded-md border-slate-300 md:col-span-4">{{ old('contact.note', $contact['note'] ?? '') }}</textarea>
                    <input name="mail[host]" value="{{ old('mail.host', $mail['host'] ?? '') }}" placeholder="SMTP host" class="rounded-md border-slate-300">
                    <input name="mail[port]" type="number" value="{{ old('mail.port', $mail['port'] ?? 587) }}" placeholder="SMTP port" class="rounded-md border-slate-300">
                    <input name="mail[username]" value="{{ old('mail.username', $mail['username'] ?? '') }}" placeholder="SMTP username" class="rounded-md border-slate-300">
                    <input name="mail[password]" type="password" placeholder="{{ ($mail['password_set'] ?? false) ? 'Đã lưu mật khẩu SMTP' : 'SMTP password' }}" class="rounded-md border-slate-300">
                    <select name="mail[encryption]" class="rounded-md border-slate-300">
                        @foreach(['tls'=>'TLS','ssl'=>'SSL','null'=>'Không mã hóa'] as $value=>$label)
                            <option value="{{ $value }}" @selected(old('mail.encryption', $mail['encryption'] ?? 'tls')===$value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <input name="mail[from_address]" value="{{ old('mail.from_address', $mail['from_address'] ?? '') }}" placeholder="From address" class="rounded-md border-slate-300">
                    <input name="mail[from_name]" value="{{ old('mail.from_name', $mail['from_name'] ?? '') }}" placeholder="From name" class="rounded-md border-slate-300">
                    <input name="security[max_login_attempts]" type="number" min="3" value="{{ old('security.max_login_attempts', $security['max_login_attempts'] ?? 5) }}" placeholder="Số lần đăng nhập sai tối đa" class="rounded-md border-slate-300">
                </div>
                <p class="mt-3 text-sm text-amber-700">Khuyến nghị production: đặt cấu hình SMTP nhạy cảm trong `.env`; nếu lưu trong DB, mật khẩu sẽ được mã hóa và không hiển thị lại.</p>
            </section>

            <div class="flex justify-end">
                <button class="rounded bg-emerald-700 px-5 py-3 text-sm font-semibold text-white">Lưu cài đặt</button>
            </div>
        </form>

        <form method="POST" action="{{ route('admin.settings.test_mail') }}" class="rounded-lg border border-slate-200 bg-white p-5">
            @csrf
            <h2 class="text-lg font-semibold">Test gửi email</h2>
            <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                <input name="test_email" type="email" required placeholder="Email nhận thử" class="flex-1 rounded-md border-slate-300">
                <button class="rounded border border-emerald-700 px-4 py-2 text-sm font-semibold text-emerald-800">Gửi email kiểm tra</button>
            </div>
            <x-input-error :messages="$errors->get('test_email')" class="mt-2" />
        </form>
    </div>
</x-app-layout>
