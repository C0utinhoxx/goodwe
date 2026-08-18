<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Laravel') }}</title>
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
    <body class="bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] dark:text-[#EDEDEC] min-h-screen antialiased">
        <header class="border-b border-[#e3e3e0] dark:border-[#3E3E3A]">
            <nav class="max-w-4xl mx-auto px-6 py-4 flex items-center justify-between">
                <a href="{{ route('chargers.index') }}" class="font-medium">{{ __('app.name') }}</a>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-[#706f6c] dark:text-[#A1A09A]">{{ __('app.subtitle') }}</span>

                    <div class="relative">
                        <button type="button" id="lang-toggle" class="flex items-center gap-2 text-sm text-[#706f6c] dark:text-[#A1A09A] hover:text-[#1b1b18] dark:hover:text-[#EDEDEC] transition-colors cursor-pointer">
                            <span class="text-base leading-none">@if(app()->getLocale() === 'pt') 🇧🇷 @elseif(app()->getLocale() === 'en') 🇺🇸 @else 🇪🇸 @endif</span>
                            <span>{{ __('language.' . app()->getLocale()) }}</span>
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                        <div id="lang-menu" class="hidden absolute right-0 top-full mt-2 bg-white dark:bg-[#161615] border border-[#e3e3e0] dark:border-[#3E3E3A] rounded-md shadow-lg z-50 min-w-[160px] overflow-hidden">
                            <a href="{{ route('locale.set', 'pt') }}" class="flex items-center gap-3 px-4 py-2.5 text-sm text-[#1b1b18] dark:text-[#EDEDEC] hover:bg-[#e3e3e0] dark:hover:bg-[#3E3E3A] transition-colors {{ app()->getLocale() === 'pt' ? 'font-bold bg-[#e3e3e0]/50 dark:bg-[#3E3E3A]/50' : '' }}">
                                <span class="text-base leading-none">🇧🇷</span> {{ __('language.pt') }}
                            </a>
                            <a href="{{ route('locale.set', 'en') }}" class="flex items-center gap-3 px-4 py-2.5 text-sm text-[#1b1b18] dark:text-[#EDEDEC] hover:bg-[#e3e3e0] dark:hover:bg-[#3E3E3A] transition-colors {{ app()->getLocale() === 'en' ? 'font-bold bg-[#e3e3e0]/50 dark:bg-[#3E3E3A]/50' : '' }}">
                                <span class="text-base leading-none">🇺🇸</span> {{ __('language.en') }}
                            </a>
                            <a href="{{ route('locale.set', 'es') }}" class="flex items-center gap-3 px-4 py-2.5 text-sm text-[#1b1b18] dark:text-[#EDEDEC] hover:bg-[#e3e3e0] dark:hover:bg-[#3E3E3A] transition-colors {{ app()->getLocale() === 'es' ? 'font-bold bg-[#e3e3e0]/50 dark:bg-[#3E3E3A]/50' : '' }}">
                                <span class="text-base leading-none">🇪🇸</span> {{ __('language.es') }}
                            </a>
                        </div>
                    </div>

                    <button type="button" id="theme-toggle" class="text-[#706f6c] dark:text-[#A1A09A] hover:text-[#1b1b18] dark:hover:text-[#EDEDEC] transition-colors cursor-pointer">
                        <svg class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        <svg class="w-5 h-5 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                        </svg>
                    </button>

                    @auth
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="text-sm text-[#706f6c] dark:text-[#A1A09A] hover:text-[#1b1b18] dark:hover:text-[#EDEDEC] transition-colors cursor-pointer">
                                {{ __('app.logout') }}
                            </button>
                        </form>
                    @endauth
                </div>
            </nav>
        </header>

        <main class="max-w-4xl mx-auto px-6 py-8">
            @if (session('success'))
                <div class="mb-6 px-4 py-3 rounded-sm border border-green-600/30 bg-green-600/10 text-green-700 dark:text-green-400 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-6 px-4 py-3 rounded-sm border border-red-600/30 bg-red-600/10 text-red-700 dark:text-red-400 text-sm">
                    {{ session('error') }}
                </div>
            @endif

            {{ $slot }}
        </main>

        <script src="{{ asset('js/theme.js') }}"></script>
    </body>
</html>
