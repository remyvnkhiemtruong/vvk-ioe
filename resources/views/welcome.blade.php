<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $settings->contestName() }} - {{ $settings->schoolName() }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-900 antialiased">
    <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4">
            <a href="{{ route('home') }}" class="flex items-center gap-3">
                <x-application-logo class="h-12 w-12 rounded-full" />
                <span>
                    <span class="block text-sm font-medium uppercase tracking-wide text-emerald-700">{{ $settings->schoolName() }}</span>
                    <span class="block text-lg font-semibold">{{ $settings->siteName() }}</span>
                </span>
            </a>
            <nav class="hidden flex-wrap items-center gap-2 text-sm font-medium md:flex">
                <a href="{{ route('home') }}" class="rounded px-3 py-2 text-slate-700 hover:bg-slate-100">Trang chủ</a>
                <a href="{{ route('home') }}#gioi-thieu" class="rounded px-3 py-2 text-slate-700 hover:bg-slate-100">Giới thiệu</a>
                <a href="{{ route('home') }}#thoi-gian" class="rounded px-3 py-2 text-slate-700 hover:bg-slate-100">Thời gian đăng ký</a>
                <a href="{{ route('home') }}#huong-dan" class="rounded px-3 py-2 text-slate-700 hover:bg-slate-100">Hướng dẫn</a>
                <a href="{{ route('home') }}#lien-he" class="rounded px-3 py-2 text-slate-700 hover:bg-slate-100">Liên hệ</a>
                <a href="{{ route('login') }}" class="rounded border border-slate-300 px-3 py-2 text-slate-700 hover:bg-slate-50">Đăng nhập</a>
                <a href="{{ route('register') }}" class="rounded bg-emerald-700 px-3 py-2 text-white hover:bg-emerald-800">Tạo tài khoản học sinh</a>
            </nav>
        </div>
    </header>

    <main class="overflow-hidden">
        <section id="gioi-thieu" class="bg-gradient-to-br from-emerald-50 via-white to-sky-50">
            <div class="mx-auto grid max-w-7xl gap-10 px-4 py-12 lg:grid-cols-[1.05fr_.95fr] lg:py-16">
                <div class="space-y-6 motion-safe:animate-[fadeIn_.5s_ease-out]">
                    <div class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-sm font-semibold text-amber-800">
                        Website chỉ mở đăng ký IOE cấp trường
                    </div>
                    <div>
                        <h1 class="max-w-4xl text-4xl font-semibold leading-tight text-slate-950 md:text-5xl">{{ $settings->contestName() }}</h1>
                        <p class="mt-3 text-xl font-medium text-emerald-800">Năm học {{ $settings->schoolYear() }}</p>
                        <p class="mt-5 max-w-2xl text-lg leading-8 text-slate-600">
                            {{ $settings->text('site.info', 'home_description', 'Hệ thống hỗ trợ học sinh Trường THPT Võ Văn Kiệt đăng ký dự thi IOE cấp trường, theo dõi ca thi, phòng thi, số máy và kết quả sau khi được công bố.') }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        @if($landingState['button_active'] && $exam)
                            <a href="{{ route('student.registrations.create', $exam) }}" class="rounded bg-emerald-700 px-5 py-3 text-sm font-semibold text-white transition hover:scale-[1.02] hover:bg-emerald-800 motion-reduce:transform-none">Đăng ký dự thi</a>
                        @else
                            <span class="rounded bg-slate-200 px-5 py-3 text-sm font-semibold text-slate-700" title="{{ $landingState['button_reason'] }}">{{ $landingState['button_label'] }}</span>
                        @endif
                        <a href="{{ route('login') }}" class="rounded border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:scale-[1.02] hover:bg-slate-50 motion-reduce:transform-none">Đăng nhập học sinh</a>
                        <a href="{{ route('register') }}" class="rounded border border-emerald-700 bg-white px-5 py-3 text-sm font-semibold text-emerald-800 transition hover:scale-[1.02] hover:bg-emerald-50 motion-reduce:transform-none">Tạo tài khoản</a>
                    </div>
                    @unless($landingState['button_active'])
                        <p class="text-sm font-medium text-slate-600">{{ $landingState['button_reason'] }}</p>
                    @endunless
                </div>

                <div id="thoi-gian" class="space-y-5">
                    <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm motion-safe:animate-[slideUp_.55s_ease-out]">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">{{ $landingState['headline'] }}</p>
                                <h2 class="mt-1 text-xl font-semibold">Bộ đếm ngược</h2>
                            </div>
                            @if($exam)
                                <x-status-badge :status="$exam->status" />
                            @endif
                        </div>
                        @if($landingState['target_at'])
                            <div class="mt-5 grid grid-cols-4 gap-3 text-center" x-data="ioeCountdown('{{ $landingState['target_at']->toIso8601String() }}')">
                                <div class="rounded border border-slate-200 bg-slate-50 p-3"><div class="text-2xl font-semibold" x-text="days">0</div><div class="text-xs text-slate-500">Ngày</div></div>
                                <div class="rounded border border-slate-200 bg-slate-50 p-3"><div class="text-2xl font-semibold" x-text="hours">0</div><div class="text-xs text-slate-500">Giờ</div></div>
                                <div class="rounded border border-slate-200 bg-slate-50 p-3"><div class="text-2xl font-semibold" x-text="minutes">0</div><div class="text-xs text-slate-500">Phút</div></div>
                                <div class="rounded border border-slate-200 bg-slate-50 p-3"><div class="text-2xl font-semibold" x-text="seconds">0</div><div class="text-xs text-slate-500">Giây</div></div>
                            </div>
                        @else
                            <div class="mt-5 rounded border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">Chưa cấu hình thời điểm đếm ngược hoặc nhà trường đã tắt hiển thị countdown.</div>
                        @endif
                    </section>

                    <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm motion-safe:animate-[slideUp_.65s_ease-out]">
                        <h2 class="text-lg font-semibold">Thông tin kỳ đăng ký</h2>
                        <dl class="mt-5 space-y-3 text-sm">
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Tên kỳ</dt><dd class="text-right font-medium">{{ $exam->name ?? 'Chưa cấu hình' }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Đối tượng</dt><dd class="font-medium">Khối {{ implode(', ', $exam?->target_grades ?? [10, 11, 12]) }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Mở đăng ký</dt><dd class="font-medium">{{ $exam?->registration_opens_at?->format('d/m/Y H:i') ?? 'Chưa cấu hình' }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Đóng đăng ký</dt><dd class="font-medium">{{ $exam?->registration_closes_at?->format('d/m/Y H:i') ?? 'Chưa cấu hình' }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Ngày thi dự kiến</dt><dd class="font-medium">{{ $exam?->exam_date?->format('d/m/Y') ?? 'Chưa có ngày thi' }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Giờ thi dự kiến</dt><dd class="font-medium">{{ $exam?->exam_time ? \Illuminate\Support\Carbon::parse($exam->exam_time)->format('H:i') : 'Chưa cấu hình' }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Hình thức thi</dt><dd class="text-right font-medium">Thi IOE trên máy tính tại phòng máy</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Tổng số ca thi</dt><dd class="font-medium">{{ $publicStats['sessions'] ?: 'Chưa có ca thi' }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Ca còn chỗ</dt><dd class="font-medium">{{ $publicStats['sessions_open'] }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Ca đã đầy</dt><dd class="font-medium">{{ $publicStats['sessions_full'] }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Trạng thái</dt><dd class="font-medium">{{ $landingState['status_label'] }}</dd></div>
                        </dl>
                    </section>
                </div>
            </div>
        </section>

        @if($exam?->show_public_stats)
            <section class="border-y border-slate-200 bg-white py-8">
                <div class="mx-auto grid max-w-7xl gap-4 px-4 sm:grid-cols-2 lg:grid-cols-5">
                    <x-stat-card label="Học sinh đã đăng ký" :value="$publicStats['registrations']" tone="emerald" />
                    <x-stat-card label="Lớp có đăng ký" :value="$publicStats['classes']" tone="blue" />
                    <x-stat-card label="Ca thi đã tạo" :value="$publicStats['sessions']" tone="slate" />
                    <x-stat-card label="Ca còn chỗ" :value="$publicStats['sessions_open']" tone="amber" />
                    <x-stat-card label="Ca đã đầy" :value="$publicStats['sessions_full']" tone="rose" />
                </div>
            </section>
        @endif

        <section class="mx-auto max-w-7xl space-y-8 px-4 py-12">
            <div class="grid gap-4 md:grid-cols-5">
                @foreach([
                    ['Đăng ký dự thi', 'Điền ID IOE, thông tin liên hệ và chọn ca thi cấp trường.', $landingState['button_active'] && $exam ? route('student.registrations.create', $exam) : route('login'), 'Đăng ký'],
                    ['Chọn ca thi', 'Học sinh chỉ thấy ca đúng khối/lớp và còn chỗ.', $landingState['button_active'] && $exam ? route('student.registrations.create', $exam) : route('login'), 'Chọn ca'],
                    ['Phòng thi, số máy', 'Đăng nhập để xem ca, phòng, số máy sau khi phân phòng.', route('login'), 'Đăng nhập'],
                    ['Phiếu dự thi', 'Tải phiếu sau khi được phân ca, phòng và số máy.', route('login'), 'Xem phiếu'],
                    ['Kết quả thi', 'Chỉ xem kết quả sau khi nhà trường công bố.', route('login'), 'Xem kết quả'],
                ] as [$title, $description, $href, $label])
                    <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md motion-reduce:transform-none">
                        <h3 class="font-semibold">{{ $title }}</h3>
                        <p class="mt-2 min-h-16 text-sm leading-6 text-slate-600">{{ $description }}</p>
                        <a href="{{ $href }}" class="mt-4 inline-flex rounded border border-emerald-700 px-3 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-50">{{ $label }}</a>
                    </article>
                @endforeach
            </div>

            <div class="grid gap-8 lg:grid-cols-[.85fr_1.15fr]">
                <section class="rounded-lg border border-slate-200 bg-white p-6">
                    <h2 class="text-lg font-semibold">Quy trình đăng ký</h2>
                    <ol class="mt-5 space-y-3 text-sm text-slate-700">
                        <li>1. Tạo tài khoản học sinh bằng lớp và mã học sinh hoặc CCCD/mã định danh đã import.</li>
                        <li>2. Đăng nhập và đăng ký IOE cấp trường trong thời gian nhà trường mở đăng ký.</li>
                        <li>3. Chọn ca thi phù hợp với khối/lớp; ca đủ chỗ sẽ tự khóa.</li>
                        <li>4. Theo dõi trạng thái duyệt, ca thi, phòng thi, số máy và phiếu dự thi.</li>
                        <li>5. Dự thi và xem kết quả sau khi nhà trường công bố.</li>
                    </ol>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white p-6">
                    <h2 class="text-lg font-semibold">Ca thi công khai</h2>
                    <div class="mt-5 grid gap-3 md:grid-cols-2">
                        @forelse(array_slice($publicSessions, 0, 6) as $session)
                            <div class="rounded border border-slate-200 p-4 text-sm">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-semibold">{{ $session['name'] }}</div>
                                        <div class="mt-1 text-slate-600">{{ $session['date'] }} · {{ $session['time'] }}</div>
                                        <div class="text-slate-600">{{ $session['room'] }} · {{ $session['target'] }}</div>
                                    </div>
                                    <x-status-badge :status="$session['status']" />
                                </div>
                                <div class="mt-3 text-slate-700">Còn {{ $session['remaining'] }}/{{ $session['max'] }} chỗ</div>
                            </div>
                        @empty
                            <div class="rounded border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">Chưa có ca thi.</div>
                        @endforelse
                    </div>
                </section>
            </div>

            <section id="huong-dan" class="rounded-lg border border-slate-200 bg-white p-6">
                <h2 class="text-lg font-semibold">Hướng dẫn quan trọng</h2>
                <div class="mt-4 grid gap-3 text-sm leading-6 text-slate-700 md:grid-cols-2">
                    <p>Cần có ID tài khoản IOE trước khi đăng ký.</p>
                    <p>Kiểm tra email, số điện thoại và lớp trước khi gửi form.</p>
                    <p>Học sinh chỉ được chọn ca thi của đúng khối/lớp.</p>
                    <p>Ca thi đủ số lượng sẽ tự khóa và học sinh phải chọn ca khác.</p>
                    <p>Nếu sử dụng máy tính cá nhân, học sinh phải đăng ký và chờ duyệt.</p>
                    <p>Nếu quên mật khẩu, liên hệ giáo viên phụ trách hoặc gửi yêu cầu cấp lại thủ công.</p>
                </div>
            </section>

            <section id="lien-he" class="rounded-lg border border-slate-200 bg-white p-6">
                <h2 class="text-lg font-semibold">Liên hệ</h2>
                <dl class="mt-4 grid gap-3 text-sm md:grid-cols-4">
                    <div><dt class="text-slate-500">Giáo viên phụ trách</dt><dd class="font-medium">{{ $contact['teacher_name'] ?? 'Chưa cấu hình' }}</dd></div>
                    <div><dt class="text-slate-500">Số điện thoại</dt><dd class="font-medium">{{ $contact['phone'] ?? 'Chưa cấu hình' }}</dd></div>
                    <div><dt class="text-slate-500">Email</dt><dd class="font-medium">{{ $contact['email'] ?? 'Chưa cấu hình' }}</dd></div>
                    <div><dt class="text-slate-500">Ghi chú</dt><dd class="font-medium">{{ $contact['note'] ?? 'Liên hệ giáo viên phụ trách khi cần hỗ trợ.' }}</dd></div>
                </dl>
            </section>
        </section>
    </main>

    <footer class="border-t border-slate-200 bg-white py-6">
        <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 text-sm text-slate-600 md:flex-row md:items-center md:justify-between">
            <div>{{ $settings->schoolName() }} · Năm học {{ $settings->schoolYear() }}</div>
            <div>© {{ now()->year }} Hệ thống IOE cấp trường.</div>
        </div>
    </footer>
</body>
</html>
