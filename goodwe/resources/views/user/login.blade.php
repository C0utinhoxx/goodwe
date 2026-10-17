@php($managerAccess = $managerAccess ?? false)
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __($managerAccess ? 'auth.manager_login_title' : 'auth.login_title') }}</title>
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
<body class="min-h-screen font-sans bg-[#FDFDFC] dark:bg-[#0f1117]">
    <div class="flex min-h-screen">
        {{-- Lado Visual --}}
        <div class="hidden lg:flex w-[60%] bg-gray-100 dark:bg-[#1a1a1a] bg-cover bg-[20%_center] bg-no-repeat items-center justify-center relative overflow-hidden" style="background-image: url('/imagens/login-goodwe.png')">
            <div class="absolute inset-0 bg-black/20"></div>

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

        {{-- Lado Formulario --}}
        <div class="w-full lg:w-[calc(40%+28px)] lg:-ml-7 bg-[#FDFDFC] dark:bg-[#0f1117] flex flex-col items-center justify-center relative lg:rounded-l-[28px] z-20 pt-24 pb-10">
            {{-- Seletor de Idioma --}}
            <div class="absolute top-6 right-8 z-10">
                <x-lang-switcher variant="boxed" />
            </div>

            {{-- Toggle de Tema --}}
            <div class="absolute top-6 left-8 z-10">
                <x-theme-toggle />
            </div>

            <div class="w-[85%] max-w-[460px]">
                <x-partner-brands class="mb-6" />
                <h2 class="text-3xl mb-3 text-gray-900 dark:text-white">{{ __($managerAccess ? 'auth.manager_login_title' : 'auth.welcome') }}</h2>
                <div class="w-[45px] h-[3px] bg-[#e53935] mb-5"></div>
                <h3 class="text-lg mb-2 font-normal text-gray-900 dark:text-white">{{ __('auth.platform_name') }}</h3>
                <p class="text-gray-500 dark:text-[#a0a0a0] mb-8">{{ __($managerAccess ? 'auth.manager_login_subtitle' : 'auth.driver_login_subtitle') }}</p>

                <nav aria-label="{{ __('auth.access_choice') }}" class="mb-6 grid grid-cols-2 gap-2 rounded-lg bg-gray-100 p-1 dark:bg-[#181b22]">
                    <a href="{{ route('user.login') }}" @if(! $managerAccess) aria-current="page" @endif class="rounded-md px-3 py-2 text-center text-sm font-semibold {{ ! $managerAccess ? 'bg-[#c62828] text-white' : 'text-gray-600 dark:text-gray-300' }}">{{ __('auth.driver_access') }}</a>
                    <a href="{{ route('manager.login') }}" @if($managerAccess) aria-current="page" @endif class="rounded-md px-3 py-2 text-center text-sm font-semibold {{ $managerAccess ? 'bg-[#c62828] text-white' : 'text-gray-600 dark:text-gray-300' }}">{{ __('auth.manager_access') }}</a>
                </nav>

                <x-flash-messages />

                <form class="flex flex-col" action="{{ route($managerAccess ? 'manager.login.store' : 'login-user') }}" method="POST">
                    @csrf

                    <label for="email" class="text-sm mb-2 text-gray-900 dark:text-white">{{ __('auth.email') }}</label>
                    <input
                        type="email"
                        name="email"
                        required autocomplete="email"
                        id="email"
                        class="w-full p-3.5 mb-6 border border-gray-300 dark:border-[#343843] rounded-md bg-gray-50 dark:bg-[#181b22] text-gray-900 dark:text-white focus:outline-none focus:border-[#e53935] @error('email') border-[#e53935] @enderror"
                        placeholder="{{ __('auth.email_placeholder') }}"
                        value="{{ old('email') }}"
                    >
                    @error('email')
                        <div class="text-[#ff5a55] text-xs -mt-4 mb-4">{{ $message }}</div>
                    @enderror

                    <label for="password" class="text-sm mb-2 text-gray-900 dark:text-white">{{ __('auth.password') }}</label>
                    <input
                        type="password"
                        name="password"
                        required autocomplete="current-password"
                        id="password"
                        class="w-full p-3.5 mb-6 border border-gray-300 dark:border-[#343843] rounded-md bg-gray-50 dark:bg-[#181b22] text-gray-900 dark:text-white focus:outline-none focus:border-[#e53935] @error('password') border-[#e53935] @enderror"
                        placeholder="{{ __('auth.password_placeholder') }}"
                    >
                    @error('password')
                        <div class="text-[#ff5a55] text-xs -mt-4 mb-4">{{ $message }}</div>
                    @enderror

                    <div class="flex justify-between items-center mb-6 text-sm">
                        <label class="flex items-center gap-2 text-gray-900 dark:text-white cursor-pointer">
                            <input
                                type="checkbox"
                                name="lembrar"
                                class="appearance-none w-4 h-4 border border-gray-300 dark:border-[#555b66] rounded-sm bg-gray-50 dark:bg-[#181b22] cursor-pointer checked:bg-[#e53935] checked:border-[#e53935] relative after:content-['✓'] after:text-white after:text-xs after:font-bold after:absolute after:left-0.5 after:-top-0.5 after:hidden checked:after:block"
                            >
                            {{ __('auth.remember_me') }}
                        </label>
                        <a href="{{ route('user.password.recovery') }}" class="text-[#e53935] no-underline hover:underline">{{ __('auth.forgot_password') }}</a>
                    </div>

                    <button type="submit" class="w-full py-3.5 border-none rounded-lg bg-[#e53935] text-white text-base font-bold cursor-pointer transition-colors hover:bg-[#c62828]">
                        {{ __('auth.login_button') }}
                    </button>
                </form>

                <p class="text-center mt-6 text-sm text-gray-500 dark:text-[#a0a0a0]">
                    {{ __('auth.no_account') }}
                    <a href="{{ route($managerAccess ? 'manager.register' : 'user.cadastro') }}" class="text-[#e53935] no-underline font-bold">{{ __('auth.register_link') }}</a>
                </p>
            </div>

            <div class="mt-10 w-[85%] text-center">
                <p class="text-sm text-gray-400 dark:text-[#b0b4bc] mb-2">{{ __('app.footer_tagline') }}</p>
                <span class="text-xs text-gray-400 dark:text-[#8a8f98]">{{ __('app.footer_copyright') }}</span>
            </div>
        </div>
    </div>

    <script src="{{ asset('js/theme.js') }}"></script>
</body>
</html>
