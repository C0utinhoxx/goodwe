<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('auth.recover_title') }}</title>
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
    <link rel="icon" href="{{ asset('imagens/logo.png') }}" type="image/x-icon">
</head>
<body class="min-h-screen font-sans bg-white dark:bg-[#0f1117]">
    <div class="flex min-h-screen">
        {{-- Lado Visual --}}
        <div class="hidden lg:flex w-[60%] bg-gray-100 dark:bg-[#1a1a1a] bg-cover bg-[20%_center] bg-no-repeat items-center justify-center relative overflow-hidden" style="background-image: url('/imagens/login-goodwe.png')">
            <div class="absolute inset-0 bg-black/20"></div>

            <div class="absolute top-8 left-9 text-[#e53935] text-2xl font-bold z-10">
                GOODWE
            </div>

            <div class="w-[70%] max-w-[620px] absolute left-9 top-[15%] z-10">
                <h1 class="text-3xl leading-tight mb-4 text-white">{{ __('visual.smart_energy') }}</h1>
                <div class="w-[45px] h-[3px] bg-[#e53935] mb-5"></div>
                <p class="text-base leading-relaxed text-[#c7c7c7] max-w-[480px]">{{ __('visual.description') }}</p>
            </div>

            <div class="absolute bottom-14 left-9 flex gap-8 z-10">
                <div class="bg-black/45 rounded-lg px-4 py-3">
                    <span class="text-white text-sm font-bold">{{ __('visual.smart_charging') }}</span>
                </div>
                <div class="bg-black/45 rounded-lg px-4 py-3">
                    <span class="text-white text-sm font-bold">{{ __('visual.energy_management') }}</span>
                </div>
                <div class="bg-black/45 rounded-lg px-4 py-3">
                    <span class="text-white text-sm font-bold">{{ __('visual.sustainability') }}</span>
                </div>
            </div>
        </div>

        {{-- Lado Formulário --}}
        <div class="w-full lg:w-[calc(40%+28px)] lg:-ml-7 bg-white dark:bg-[#0f1117] flex items-center justify-center relative lg:rounded-l-[28px] z-20 overflow-y-auto py-10">
            {{-- Seletor de Idioma --}}
            <div class="absolute top-6 right-8 z-10">
                <div class="relative">
                    <button type="button" id="lang-toggle" class="flex items-center gap-2 bg-gray-100 dark:bg-[#2a2d34] text-gray-800 dark:text-white border border-gray-200 dark:border-none rounded-lg px-3 py-2 cursor-pointer text-sm hover:bg-gray-200 dark:hover:bg-[#3a3d45] transition-colors">
                        <span class="text-base leading-none">@if(app()->getLocale() === 'pt') 🇧🇷 @elseif(app()->getLocale() === 'en') 🇺🇸 @else 🇪🇸 @endif</span>
                        <span>{{ __('language.' . app()->getLocale()) }}</span>
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>
                    <div id="lang-menu" class="hidden absolute right-0 top-full mt-2 w-[160px] bg-white dark:bg-[#2a2d34] border border-gray-200 dark:border-none rounded-lg py-1 shadow-lg">
                        <a href="{{ route('locale.set', 'pt') }}" class="flex items-center gap-3 px-4 py-2.5 text-gray-800 dark:text-white text-sm hover:bg-gray-100 dark:hover:bg-[#3a3d45] transition-colors {{ app()->getLocale() === 'pt' ? 'font-bold' : '' }}">
                            <span class="text-base leading-none">🇧🇷</span> {{ __('language.pt') }}
                        </a>
                        <a href="{{ route('locale.set', 'en') }}" class="flex items-center gap-3 px-4 py-2.5 text-gray-800 dark:text-white text-sm hover:bg-gray-100 dark:hover:bg-[#3a3d45] transition-colors {{ app()->getLocale() === 'en' ? 'font-bold' : '' }}">
                            <span class="text-base leading-none">🇺🇸</span> {{ __('language.en') }}
                        </a>
                        <a href="{{ route('locale.set', 'es') }}" class="flex items-center gap-3 px-4 py-2.5 text-gray-800 dark:text-white text-sm hover:bg-gray-100 dark:hover:bg-[#3a3d45] transition-colors {{ app()->getLocale() === 'es' ? 'font-bold' : '' }}">
                            <span class="text-base leading-none">🇪🇸</span> {{ __('language.es') }}
                        </a>
                    </div>
                </div>
            </div>

            {{-- Toggle de Tema --}}
            <div class="absolute top-6 left-8 z-10">
                <button type="button" id="theme-toggle" class="text-gray-400 dark:text-[#A1A09A] hover:text-gray-800 dark:hover:text-white transition-colors cursor-pointer">
                    <svg class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                    <svg class="w-5 h-5 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                    </svg>
                </button>
            </div>

            <div class="w-[85%] max-w-[460px]" id="recover-container" data-verified="{{ session('password_recovery_verified') ? 'true' : 'false' }}">
                <h2 class="text-3xl mb-3 text-gray-900 dark:text-white">{{ __('auth.recover_title') }}</h2>
                <div class="w-[45px] h-[3px] bg-[#e53935] mb-5"></div>
                <h3 class="text-lg mb-2 font-normal text-gray-900 dark:text-white">{{ __('auth.platform_name') }}</h3>
                <p class="text-gray-500 dark:text-[#a0a0a0] mb-8">{{ __('auth.recover_subtitle') }}</p>

                @if (session('success'))
                    <div class="mb-4 px-4 py-3 rounded-lg border border-green-500/30 bg-green-50 dark:bg-green-500/10 text-green-700 dark:text-green-400 text-sm">
                        {{ session('success') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="mb-4 px-4 py-3 rounded-lg border border-red-500/30 bg-red-50 dark:bg-red-500/10 text-red-700 dark:text-red-400 text-sm">
                        {{ session('error') }}
                    </div>
                @endif

                {{-- Verificação de identidade --}}
                <form action="{{ route('user.password.verify') }}" method="POST" class="flex flex-col">
                    @csrf

                    <label for="email" class="text-sm mb-2 text-gray-900 dark:text-white">{{ __('auth.email') }}</label>
                    <input
                        type="email"
                        name="email"
                        id="email"
                        class="w-full p-3.5 mb-6 border border-gray-300 dark:border-[#343843] rounded-md bg-gray-50 dark:bg-[#181b22] text-gray-900 dark:text-white focus:outline-none focus:border-[#e53935] @error('email') border-[#e53935] @enderror"
                        placeholder="{{ __('auth.email_placeholder') }}"
                        value="{{ old('email') ?? session('password_recovery_email') }}"
                        {{ session('password_recovery_verified') ? 'readonly' : '' }}
                    >
                    @error('email')
                        <div class="text-[#ff5a55] text-xs -mt-4 mb-4">{{ $message }}</div>
                    @enderror

                    <label for="phone" class="text-sm mb-2 text-gray-900 dark:text-white">{{ __('auth.phone') }}</label>
                    <input
                        type="tel"
                        name="phone"
                        id="phone"
                        class="w-full p-3.5 mb-6 border border-gray-300 dark:border-[#343843] rounded-md bg-gray-50 dark:bg-[#181b22] text-gray-900 dark:text-white focus:outline-none focus:border-[#e53935] @error('phone') border-[#e53935] @enderror"
                        placeholder="{{ __('auth.phone_placeholder') }}"
                        maxlength="20"
                        {{ session('password_recovery_verified') ? 'readonly' : '' }}
                    >
                    @error('phone')
                        <div class="text-[#ff5a55] text-xs -mt-4 mb-4">{{ $message }}</div>
                    @enderror

                    <button type="submit" id="verify-btn" class="w-full py-3.5 border-none rounded-lg bg-[#e53935] text-white text-base font-bold cursor-pointer transition-colors hover:bg-[#c62828] {{ session('password_recovery_verified') ? 'hidden' : '' }}">
                        {{ __('auth.verify_button') }}
                    </button>
                </form>

                {{-- Alteração de senha (bloqueada até a verificação) --}}
                @if (! session('password_recovery_verified'))
                    <div class="mt-6 flex items-center gap-2 px-4 py-3 rounded-lg border border-gray-300 dark:border-[#343843] bg-gray-50 dark:bg-[#181b22] text-gray-500 dark:text-[#a0a0a0] text-xs">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                        <span>{{ __('auth.recover_locked_hint') }}</span>
                    </div>
                @endif

                <form action="{{ route('user.password.update') }}" method="POST" class="flex flex-col mt-6" id="password-form">
                    @csrf

                    <label for="password" class="text-sm mb-2 text-gray-900 dark:text-white">{{ __('auth.new_password') }}</label>
                    <input
                        type="password"
                        name="password"
                        id="new-password"
                        class="w-full p-3.5 mb-6 border border-gray-300 dark:border-[#343843] rounded-md bg-gray-50 dark:bg-[#181b22] text-gray-900 dark:text-white focus:outline-none focus:border-[#e53935] disabled:opacity-50 disabled:cursor-not-allowed @error('password') border-[#e53935] @enderror"
                        placeholder="{{ __('auth.new_password_placeholder') }}"
                        {{ session('password_recovery_verified') ? '' : 'disabled' }}
                    >
                    @error('password')
                        <div class="text-[#ff5a55] text-xs -mt-4 mb-4">{{ $message }}</div>
                    @enderror

                    <label for="password_confirmation" class="text-sm mb-2 text-gray-900 dark:text-white">{{ __('auth.password_confirmation') }}</label>
                    <input
                        type="password"
                        name="password_confirmation"
                        id="password_confirmation"
                        class="w-full p-3.5 mb-6 border border-gray-300 dark:border-[#343843] rounded-md bg-gray-50 dark:bg-[#181b22] text-gray-900 dark:text-white focus:outline-none focus:border-[#e53935] disabled:opacity-50 disabled:cursor-not-allowed @error('password') border-[#e53935] @enderror"
                        placeholder="{{ __('auth.password_confirmation_placeholder') }}"
                        {{ session('password_recovery_verified') ? '' : 'disabled' }}
                    >

                    <button type="submit" id="reset-btn" class="w-full py-3.5 border-none rounded-lg bg-[#e53935] text-white text-base font-bold cursor-pointer transition-colors hover:bg-[#c62828] disabled:opacity-50 disabled:cursor-not-allowed" {{ session('password_recovery_verified') ? '' : 'disabled' }}>
                        {{ __('auth.reset_button') }}
                    </button>
                </form>

                <p class="text-center mt-6 text-sm text-gray-500 dark:text-[#a0a0a0]">
                    {{ __('auth.remembered_password') }}
                    <a href="{{ route('user.login') }}" class="text-[#e53935] no-underline font-bold">{{ __('auth.login_link') }}</a>
                </p>
            </div>

            <div class="absolute bottom-6 left-1/2 -translate-x-1/2 w-[85%] text-center">
                <p class="text-sm text-gray-400 dark:text-[#b0b4bc] mb-2">{{ __('app.footer_tagline') }}</p>
                <span class="text-xs text-gray-400 dark:text-[#8a8f98]">{{ __('app.footer_copyright') }}</span>
            </div>
        </div>
    </div>

    <script src="{{ asset('js/theme.js') }}"></script>
</body>
</html>