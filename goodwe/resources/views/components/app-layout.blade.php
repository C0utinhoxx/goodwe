<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Laravel') }}</title>
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
    <body class="bg-[#FDFDFC] dark:bg-[#0f1117] text-[#1b1b18] dark:text-[#EDEDEC] min-h-screen antialiased">
        <div class="flex min-h-screen">
            <aside class="hidden w-64 shrink-0 flex-col border-r border-[#e3e3e0] dark:border-[#343843] bg-white dark:bg-[#1a1a1a] lg:flex">
                <div class="px-6 pb-6 pt-8">
                    <a href="{{ route('user.home') }}" class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#e53935]">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                            </svg>
                        </div>
                        <span class="text-lg font-bold tracking-tight text-gray-900 dark:text-white">GOODWE</span>
                    </a>
                    <p class="mt-2 text-xs text-gray-500 dark:text-[#a0a0a0]">{{ __('app.subtitle') }}</p>
                </div>

                <nav class="space-y-1 px-4">
                    <a href="{{ route('user.home') }}" class="flex items-center gap-3 rounded-lg border border-[#e53935]/40 bg-[#e53935]/10 px-4 py-2.5 text-sm font-medium text-[#e53935]">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                        </svg>
                        <span>{{ __('nav.home') }}</span>
                    </a>
                    <a href="{{ route('chargers.index') }}" class="flex items-center gap-3 rounded-lg px-4 py-2.5 text-sm font-medium text-[#706f6c] hover:bg-[#e3e3e0]/50 hover:text-[#1b1b18] dark:text-[#a0a0a0] dark:hover:bg-[#343843] dark:hover:text-white transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                        </svg>
                        <span>{{ __('nav.chargers') }}</span>
                    </a>
                    <a href="{{ route('user.profile') }}" class="flex items-center gap-3 rounded-lg px-4 py-2.5 text-sm font-medium text-[#706f6c] hover:bg-[#e3e3e0]/50 hover:text-[#1b1b18] dark:text-[#a0a0a0] dark:hover:bg-[#343843] dark:hover:text-white transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                        </svg>
                        <span>{{ __('nav.profile') }}</span>
                    </a>
                </nav>

                <div class="mt-auto space-y-1 border-t border-[#e3e3e0] dark:border-[#343843] px-4 pb-6 pt-4">
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
                <header class="border-b border-[#e3e3e0] dark:border-[#343843] bg-white/80 dark:bg-[#1a1a1a]/80 backdrop-blur-sm sticky top-0 z-30">
                    <nav class="max-w-5xl mx-auto px-6 py-3.5 flex items-center justify-between">
                        <div class="flex items-center gap-4 lg:hidden">
                            <a href="{{ route('user.home') }}" class="flex items-center gap-2">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#e53935]">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                                    </svg>
                                </div>
                                <span class="text-sm font-bold tracking-tight text-gray-900 dark:text-white">GOODWE</span>
                            </a>
                        </div>

                        <div class="flex-1 lg:flex-none"></div>

                        <div class="flex items-center gap-3">
                            <x-lang-switcher variant="boxed" />
                            <x-theme-toggle variant="boxed" />

                            @auth
                                <div class="hidden sm:flex items-center gap-2 pl-2 border-l border-[#e3e3e0] dark:border-[#343843]">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-[#e53935]/10 text-[#e53935] text-xs font-semibold">
                                        {{ strtoupper(substr(Auth::user()->name, 0, 2)) }}
                                    </div>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ Auth::user()->name }}</span>
                                </div>

                                <form method="POST" action="{{ route('logout') }}" class="inline">
                                    @csrf
                                    <button type="submit" class="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 dark:border-[#343843] bg-gray-50 dark:bg-[#181b22] text-gray-500 dark:text-[#a0a0a0] hover:border-[#e53935]/40 hover:text-[#e53935] dark:hover:text-[#e53935] transition-all cursor-pointer" title="{{ __('app.logout') }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                                        </svg>
                                    </button>
                                </form>
                            @endauth
                        </div>
                    </nav>
                </header>

                <div class="max-w-5xl mx-auto px-6 py-8">
                    <x-flash-messages />
                    {{ $slot }}
                </div>
            </main>
        </div>

        <script src="{{ asset('js/theme.js') }}"></script>
    </body>
</html>
