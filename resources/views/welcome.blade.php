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
                    <span class="block text-lg font-semibold">IOE nội bộ {{ $settings->schoolYear() }}</span>
                </span>
            </a>
            <nav class="hidden flex-wrap items-center gap-2 text-sm font-medium md:flex">
                <a href="{{ route('home') }}" class="rounded px-3 py-2 text-slate-700 hover:bg-slate-100">Trang chủ</a>
                <a href="#ky-thi" class="rounded px-3 py-2 text-slate-700 hover:bg-slate-100">Kỳ thi</a>
                <a href="#huong-dan" class="rounded px-3 py-2 text-slate-700 hover:bg-slate-100">Hướng dẫn</a>
                <a href="#lien-he" class="rounded px-3 py-2 text-slate-700 hover:bg-slate-100">Liên hệ</a>
                <a href="{{ route('login') }}" class="rounded border border-slate-300 px-3 py-2 text-slate-700 hover:bg-slate-50">Đăng nhập</a>
                @if($account['student_registration_enabled'] ?? true)
                    <a href="{{ route('register') }}" class="rounded bg-emerald-700 px-3 py-2 text-white hover:bg-emerald-800">Tạo tài khoản</a>
                @endif
            </nav>
        </div>
    </header>

    <main>
        <section class="border-b border-slate-200 bg-white">
            <div class="mx-auto grid max-w-7xl gap-8 px-4 py-10 lg:grid-cols-[1fr_420px] lg:py-14">
                <div class="space-y-6">
                    <div class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-sm font-semibold text-emerald-800">
                        Năm học {{ $settings->schoolYear() }}
                    </div>
                    <div>
                        <h1 class="max-w-4xl text-4xl font-semibold leading-tight text-slate-950 md:text-5xl">{{ $settings->contestName() }}</h1>
                        <p class="mt-5 max-w-3xl text-lg leading-8 text-slate-600">
                            {{ $settings->text('site.info', 'home_description', 'Hệ thống quản lý IOE nội bộ của Trường THPT Võ Văn Kiệt: theo dõi kỳ thi, danh sách học sinh, ca thi, live mã ca và nhập điểm sau khi thi trên ioe.vn.') }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        @if($landingState['button_active'] && $exam)
                            <a href="{{ route('student.registrations.create', $exam) }}" class="rounded bg-emerald-700 px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-800">Đăng ký dự thi</a>
                        @else
                            <span class="rounded bg-slate-200 px-5 py-3 text-sm font-semibold text-slate-700" title="{{ $landingState['button_reason'] }}">{{ $landingState['button_label'] }}</span>
                        @endif
                        <a href="{{ route('login') }}" class="rounded border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Đăng nhập học sinh</a>
                        @if($account['student_registration_enabled'] ?? true)
                            <a href="{{ route('register') }}" class="rounded border border-emerald-700 bg-white px-5 py-3 text-sm font-semibold text-emerald-800 hover:bg-emerald-50">Tạo tài khoản</a>
                        @endif
                    </div>
                    @unless($landingState['button_active'])
                        <p class="text-sm font-medium text-slate-600">{{ $landingState['button_reason'] }}</p>
                    @endunless
                </div>

                <section id="ky-thi" class="rounded-lg border border-slate-200 bg-slate-50 p-6">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">{{ $landingState['headline'] }}</p>
                            <h2 class="mt-1 text-xl font-semibold">Kỳ thi đang hiển thị</h2>
                        </div>
                        @if($exam)
                            <x-status-badge :status="$exam->status" />
                        @endif
                    </div>

                    @if($exam)
                        <dl class="mt-5 space-y-3 text-sm">
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Tên kỳ</dt><dd class="text-right font-medium">{{ $exam->name }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Đối tượng</dt><dd class="font-medium">Khối {{ implode(', ', $exam->target_grades ?? [10, 11, 12]) }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Mở đăng ký</dt><dd class="font-medium">{{ $exam->registration_opens_at?->format('d/m/Y H:i') ?? 'Chưa cấu hình' }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Đóng đăng ký</dt><dd class="font-medium">{{ $exam->registration_closes_at?->format('d/m/Y H:i') ?? 'Chưa cấu hình' }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Ngày thi</dt><dd class="font-medium">{{ $exam->exam_date?->format('d/m/Y') ?? 'Chưa cấu hình' }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Ca thi công khai</dt><dd class="font-medium">{{ $publicStats['sessions'] ?: 'Chưa có' }}</dd></div>
                        </dl>
                    @else
                        <div class="mt-5 rounded border border-slate-200 bg-white p-4 text-sm leading-6 text-slate-700">
                            Nhà trường chưa mở kỳ thi IOE nào cho năm học {{ $settings->schoolYear() }}. Các kỳ thi năm 2025-2026 đã được lưu dưới dạng lịch sử.
                        </div>
                    @endif
                </section>
            </div>
        </section>

        @if($exam?->show_public_stats)
            <section class="border-b border-slate-200 bg-white py-8">
                <div class="mx-auto grid max-w-7xl gap-4 px-4 sm:grid-cols-2 lg:grid-cols-5">
                    <x-stat-card label="Học sinh đã đăng ký" :value="$publicStats['registrations']" tone="emerald" />
                    <x-stat-card label="Lớp có đăng ký" :value="$publicStats['classes']" tone="blue" />
                    <x-stat-card label="Ca thi đã tạo" :value="$publicStats['sessions']" tone="slate" />
                    <x-stat-card label="Ca còn chỗ" :value="$publicStats['sessions_open']" tone="amber" />
                    <x-stat-card label="Ca đã đầy" :value="$publicStats['sessions_full']" tone="rose" />
                </div>
            </section>
        @endif

        <section class="mx-auto grid max-w-7xl gap-8 px-4 py-12 lg:grid-cols-[.95fr_1.05fr]">
            <section id="huong-dan" class="rounded-lg border border-slate-200 bg-white p-6">
                <h2 class="text-lg font-semibold">Quy trình năm học 2026-2027</h2>
                <ol class="mt-5 space-y-3 text-sm leading-6 text-slate-700">
                    <li>1. Admin tạo kỳ thi, cấu hình thời gian đăng ký, đối tượng dự thi, thang điểm và ca thi.</li>
                    <li>2. Admin/giáo viên thêm học sinh thủ công hoặc mở để học sinh tự đăng ký nếu kỳ thi cho phép.</li>
                    <li>3. Khi có mã ca thi từ ioe.vn, admin nhập mã và gán cho ca/khung giờ cần trình chiếu.</li>
                    <li>4. Trang live tự hiện mã 5 phút trước ca, tự ẩn mã sau khi bắt đầu và chuyển sang ca tiếp theo.</li>
                    <li>5. Sau khi học sinh thi trên ioe.vn, giáo viên/giám thị nhập điểm để hệ thống xếp hạng và xếp giải.</li>
                </ol>
                <div class="mt-5 rounded border border-slate-200 bg-slate-50 p-4 text-sm leading-6 text-slate-700">
                    {{ $account['student_code_help'] ?? 'Nếu chưa biết mã học sinh, học sinh có thể liên hệ Trương Minh Khiêm để được hỗ trợ.' }}
                    @if(! empty($account['student_code_lookup_url']))
                        <a href="{{ $account['student_code_lookup_url'] }}" target="_blank" rel="noopener" class="mt-2 block font-semibold text-emerald-700">Tra cứu mã học sinh</a>
                    @endif
                </div>
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
                        <div class="rounded border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700 md:col-span-2">Chưa có ca thi công khai cho năm học mới.</div>
                    @endforelse
                </div>
            </section>
        </section>

        <section id="lien-he" class="border-t border-slate-200 bg-white py-10">
            <div class="mx-auto max-w-7xl px-4">
                <h2 class="text-lg font-semibold">Liên hệ</h2>
                <div class="mt-5 grid gap-4 md:grid-cols-3">
                    <div class="rounded-lg border border-slate-200 p-5">
                        <div class="text-sm text-slate-500">Giáo viên phụ trách</div>
                        <div class="mt-1 font-semibold">{{ $contact['teacher_name'] ?? 'Thầy Huỳnh Thanh Hào' }}</div>
                        <div class="text-sm text-slate-600">{{ $contact['teacher_title'] ?? 'Giáo viên tiếng Anh' }}</div>
                        <div class="mt-2 text-sm text-slate-700">{{ $contact['teacher_email'] ?? 'huynhthanhhaota@gmail.com' }}</div>
                    </div>
                    <div class="rounded-lg border border-slate-200 p-5">
                        <div class="text-sm text-slate-500">Hỗ trợ học sinh</div>
                        <div class="mt-1 font-semibold">{{ $contact['support_name'] ?? 'Trương Minh Khiêm' }}</div>
                        <div class="text-sm text-slate-600">{{ $contact['support_title'] ?? 'Cựu học sinh' }}</div>
                        <div class="mt-2 text-sm text-slate-700">{{ $contact['support_phone'] ?? '0385844458' }} · {{ $contact['support_email'] ?? 'truongminhkhiemvta@gmail.com' }}</div>
                    </div>
                    <div class="rounded-lg border border-slate-200 p-5">
                        <div class="text-sm text-slate-500">Dev</div>
                        <div class="mt-1 font-semibold">{{ $contact['developer_name'] ?? 'Trương Minh Khiêm' }}</div>
                        <div class="mt-2 text-sm leading-6 text-slate-700">{{ $contact['note'] ?? 'Liên hệ khi cần hỗ trợ tài khoản, mã học sinh hoặc thông tin ca thi.' }}</div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-slate-200 bg-white py-6">
        <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 text-sm text-slate-600 md:flex-row md:items-center md:justify-between">
            <div>{{ $settings->schoolName() }} · Năm học {{ $settings->schoolYear() }}</div>
            <div>© {{ now()->year }} Hệ thống IOE nội bộ.</div>
        </div>
    </footer>
</body>
</html>
