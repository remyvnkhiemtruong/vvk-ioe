<nav x-data="{ open: false }" class="border-b border-slate-200 bg-white">
    @auth
        @php
            $settings = app(\App\Services\SystemSettingService::class);
            $navExam = \App\Models\Exam::query()
                ->where('school_year', $settings->schoolYear())
                ->whereIn('status', \App\Services\SystemSettingService::ACTIVE_LANDING_STATUSES)
                ->latest('id')
                ->first();
            $publicScoreboardEnabled = data_get($settings->get('score.options', []), 'public_scoreboard', false);
        @endphp
    @endauth

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 justify-between">
            <div class="flex">
                <div class="flex shrink-0 items-center">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                        <x-application-logo class="h-10 w-10 rounded-full" />
                        <span class="hidden text-sm font-semibold leading-tight text-slate-800 sm:block">
                            THPT Võ Văn Kiệt<br>
                            <span class="font-normal text-slate-500">IOE nội bộ</span>
                        </span>
                    </a>
                </div>

                <div class="hidden space-x-6 sm:-my-px sm:ms-8 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">Tổng quan</x-nav-link>
                    @auth
                        @if($publicScoreboardEnabled)
                            <x-nav-link :href="route('public.leaderboard')" :active="request()->routeIs('public.leaderboard*')">Bảng xếp hạng</x-nav-link>
                        @endif
                        @can('exams.manage')
                            <x-nav-link :href="route('admin.exams.index')" :active="request()->routeIs('admin.exams.*')">Kỳ thi</x-nav-link>
                            @if($navExam)
                                <x-nav-link :href="route('admin.exam-students.index', $navExam)" :active="request()->routeIs('admin.exam-students.*')">Học sinh nội bộ</x-nav-link>
                                <x-nav-link :href="route('admin.exam-codes.index', $navExam)" :active="request()->routeIs('admin.exam-codes.*')">Mã ca</x-nav-link>
                                <x-nav-link :href="route('admin.live-screens.index', $navExam)" :active="request()->routeIs('admin.live-screens.*')">Live</x-nav-link>
                                <x-nav-link :href="route('admin.exam.rankings.index', $navExam)" :active="request()->routeIs('admin.exam.rankings.*') || request()->routeIs('admin.exam.awards.*')">Xếp giải</x-nav-link>
                            @endif
                        @endcan
                        @can('sessions.manage')
                            <x-nav-link :href="route('admin.sessions.index')" :active="request()->routeIs('admin.sessions.*')">Lịch/khung giờ</x-nav-link>
                        @endcan
                        @can('scores.enter')
                            @php
                                $scoreHref = auth()->user()->isProctor() && ! auth()->user()->isAdmin() && ! auth()->user()->isTeacher()
                                    ? route('proctor.scores.index')
                                    : ($navExam ? route('admin.score-entry.index', $navExam) : route('admin.scores.index'));
                            @endphp
                            <x-nav-link
                                :href="$scoreHref"
                                :active="request()->routeIs('admin.score-entry.*') || request()->routeIs('admin.scores.*') || request()->routeIs('proctor.scores.*')"
                            >Nhập điểm</x-nav-link>
                        @endcan
                        @can('students.view')
                            <x-nav-link :href="route('admin.students.index')" :active="request()->routeIs('admin.students.*')">Hồ sơ học sinh</x-nav-link>
                        @endcan
                        @can('registrations.view')
                            <x-nav-link :href="route('admin.registrations.index')" :active="request()->routeIs('admin.registrations.*')">Đăng ký legacy</x-nav-link>
                        @endcan
                        @can('settings.manage')
                            <x-nav-link :href="route('admin.settings.index')" :active="request()->routeIs('admin.settings.*')">Cài đặt</x-nav-link>
                        @endcan
                        @can('staff.manage')
                            <x-nav-link :href="route('admin.staff.index')" :active="request()->routeIs('admin.staff.*')">Nhân sự</x-nav-link>
                        @endcan
                    @endauth
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition hover:text-gray-700 focus:outline-none">
                            @php $avaUrl = Auth::user()->avatarUrl(); @endphp
                            <img src="{{ $avaUrl }}" alt="" class="mr-2 h-7 w-7 rounded-full object-cover">
                            <div>{{ Auth::user()->name }}</div>
                            <div class="ms-1">
                                <svg class="h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">Hồ sơ</x-dropdown-link>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">Đăng xuất</x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="space-y-1 pb-3 pt-2">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">Tổng quan</x-responsive-nav-link>
            @auth
                @if($publicScoreboardEnabled)
                    <x-responsive-nav-link :href="route('public.leaderboard')">Bảng xếp hạng</x-responsive-nav-link>
                @endif
                @can('exams.manage')
                    <x-responsive-nav-link :href="route('admin.exams.index')">Kỳ thi</x-responsive-nav-link>
                    @if($navExam)
                        <x-responsive-nav-link :href="route('admin.exam-students.index', $navExam)">Học sinh nội bộ</x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('admin.exam-codes.index', $navExam)">Mã ca</x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('admin.live-screens.index', $navExam)">Live</x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('admin.exam.rankings.index', $navExam)">Xếp giải</x-responsive-nav-link>
                    @endif
                @endcan
                @can('sessions.manage')<x-responsive-nav-link :href="route('admin.sessions.index')">Lịch/khung giờ</x-responsive-nav-link>@endcan
                @can('scores.enter')<x-responsive-nav-link :href="$scoreHref ?? route('admin.scores.index')">Nhập điểm</x-responsive-nav-link>@endcan
                @can('students.view')<x-responsive-nav-link :href="route('admin.students.index')">Hồ sơ học sinh</x-responsive-nav-link>@endcan
                @can('registrations.view')<x-responsive-nav-link :href="route('admin.registrations.index')">Đăng ký legacy</x-responsive-nav-link>@endcan
                @can('settings.manage')<x-responsive-nav-link :href="route('admin.settings.index')">Cài đặt</x-responsive-nav-link>@endcan
                @can('staff.manage')<x-responsive-nav-link :href="route('admin.staff.index')">Nhân sự</x-responsive-nav-link>@endcan
            @endauth
        </div>
        <div class="border-t border-gray-200 pb-1 pt-4">
            <div class="px-4">
                <div class="text-base font-medium text-gray-800">{{ Auth::user()->name }}</div>
                <div class="text-sm font-medium text-gray-500">{{ Auth::user()->email }}</div>
            </div>
            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">Hồ sơ</x-responsive-nav-link>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">Đăng xuất</x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
