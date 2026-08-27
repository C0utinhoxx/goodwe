<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoodWe</title>
    <link rel="icon" href="{{ asset('imagens/logo.png') }}" type="image/x-icon">
    <script>
        (function() {
            var theme = localStorage.getItem('theme');
            if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#FDFDFC] text-[#1b1b18] dark:bg-[#0f1117] dark:text-[#EDEDEC]">
    <div class="flex min-h-screen">
        <aside class="hidden w-64 shrink-0 flex-col border-r border-[#e3e3e0] bg-white dark:border-[#343843] dark:bg-[#1a1a1a] lg:flex">
            <div class="px-6 pb-6 pt-8">
                <a href="{{ route('user.home') }}" class="flex items-center gap-2.5">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#e53935]">
                        <span class="text-white font-bold text-base">W</span>
                    </div>
                    <span class="text-lg font-bold tracking-tight text-gray-900 dark:text-white">GOODWE</span>
                </a>
                <p class="mt-2 text-xs text-gray-500 dark:text-[#a0a0a0]">{{ __('app.subtitle') }}</p>
            </div>

            @php
                $navActive = 'flex items-center gap-3 rounded-lg border border-[#e53935]/40 bg-[#e53935]/10 px-4 py-2.5 text-sm font-medium text-[#e53935]';
                $navInactive = 'flex items-center gap-3 rounded-lg px-4 py-2.5 text-sm font-medium text-[#706f6c] hover:bg-[#e3e3e0]/50 hover:text-[#1b1b18] dark:text-[#a0a0a0] dark:hover:bg-[#343843] dark:hover:text-white transition-colors';

                $isHome = request()->routeIs('user.home');
                $isChargers = request()->routeIs('chargers.*');
                $isProfile = request()->routeIs('user.profile');
            @endphp

            <nav class="space-y-1 px-4">
                <a href="{{ route('user.home') }}" class="{{ $isHome ? $navActive : $navInactive }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                    </svg>
                    <span>{{ __('nav.home') }}</span>
                </a>
                <a href="{{ route('chargers.index') }}" class="{{ $isChargers ? $navActive : $navInactive }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                    </svg>
                    <span>{{ __('nav.chargers') }}</span>
                </a>
                <a href="{{ route('wallet.index') }}" class="{{ request()->routeIs('wallet.index') ? $navActive : $navInactive }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 01-1.5 1.5H5.25a1.5 1.5 0 01-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 109.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1114.625 7.5H12m0 0V21m-8.625-9.75h18c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-18c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                    </svg>
                    <span>{{ __('nav.wallet') }}</span>
                </a>
                <a href="{{ route('wallet.history') }}" class="{{ request()->routeIs('wallet.history') ? $navActive : $navInactive }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ __('nav.history') }}</span>
                </a>
                <a href="#" class="flex items-center gap-3 rounded-lg px-4 py-2.5 text-sm font-medium text-[#706f6c] hover:bg-[#e3e3e0]/50 hover:text-[#1b1b18] dark:text-[#a0a0a0] dark:hover:bg-[#343843] dark:hover:text-white transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
                    </svg>
                    <span>{{ __('nav.help') }}</span>
                </a>
                @if(Auth::user()->isGestor())
                    <a href="{{ route('manager.dashboard') }}" class="{{ request()->routeIs('manager.*') ? $navActive : $navInactive }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                        </svg>
                        <span>{{ __('nav.gestor') }}</span>
                    </a>
                @endif
            </nav>

            <div class="mt-auto space-y-1 border-t border-[#e3e3e0] dark:border-[#343843] px-4 pb-6 pt-4">
                <a href="{{ route('user.profile') }}" class="{{ $isProfile ? $navActive : $navInactive }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                    </svg>
                    <span>{{ __('nav.profile') }}</span>
                </a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-4 py-2.5 text-sm font-medium text-[#706f6c] hover:bg-[#e3e3e0]/50 hover:text-[#1b1b18] dark:text-[#a0a0a0] dark:hover:bg-[#343843] dark:hover:text-white transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                        </svg>
                        <span>{{ __('app.logout') }}</span>
                    </button>
                </form>
            </div>
        </aside>

        <main class="min-w-0 flex-1">
            <div class="mx-auto max-w-[1280px] px-5 py-8 sm:px-6 lg:px-8">
                <header class="flex items-center justify-between gap-4">
                    <div>
                        {{ $header ?? '' }}
                    </div>

                    <div class="flex items-center gap-3">
                        <x-lang-switcher variant="boxed" />
                        <x-theme-toggle variant="boxed" />

                    <div class="hidden h-8 border-l border-[#e3e3e0] dark:border-[#343843] sm:block"></div>

                    <a
                        href="{{ route('user.profile') }}"
                        class="flex h-10 w-10 items-center justify-center rounded-full border border-[#e53935]/40 bg-[#e53935]/10 text-sm font-semibold text-[#e53935] transition-colors hover:bg-[#e53935]/20"
                        title="{{ __('nav.profile') }}"
                    >
                        {{ strtoupper(substr(Auth::user()->name ?? 'U', 0, 2)) }}
                    </a>

                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button
                            type="submit"
                            class="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 dark:border-[#343843] bg-gray-50 dark:bg-[#181b22] text-gray-500 dark:text-[#a0a0a0] hover:border-[#e53935]/40 hover:text-[#e53935] dark:hover:text-[#e53935] transition-all cursor-pointer"
                            title="{{ __('app.logout') }}"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                            </svg>
                        </button>
                    </form>
                </div>
            </header>

                <x-flash-messages />
                {{ $slot }}
            </div>
        </main>
    </div>
    <script src="{{ asset('js/theme.js') }}"></script>
</body>
</html>
