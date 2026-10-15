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

            if (
                theme === 'dark' ||
                (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)
            ) {
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

        <aside class="fixed inset-y-0 hidden w-64 shrink-0 flex-col border-r border-[#e3e3e0] bg-white dark:border-[#343843] dark:bg-[#1a1a1a] lg:flex">

            <div class="px-6 pb-6 pt-8">
            
				<a href="{{ route('manager.dashboard') }}" class="flex items-center gap-2.5">
                    <x-partner-brands compact />
				</a>
				
				<p class="mt-2 text-xs text-gray-500 dark:text-[#a0a0a0]">
				    {{ __('auth.manager_environment') }}
				</p>
            </div>

            @php
                $navActive = 'flex items-center gap-3 rounded-lg border border-[#e53935]/40 bg-[#e53935]/10 px-4 py-2.5 text-sm font-medium text-[#e53935]';
                $navInactive = 'flex items-center gap-3 rounded-lg px-4 py-2.5 text-sm font-medium text-[#706f6c] hover:bg-[#e3e3e0]/50 dark:text-[#a0a0a0] dark:hover:bg-[#343843]';
            @endphp
            <nav aria-label="Painel Gestor" class="space-y-1 px-4">
                <a href="{{ route('manager.establishment.setup') }}" class="{{ request()->routeIs('manager.establishment.*') ? $navActive : $navInactive }}">Meu Estabelecimento</a>
                @unless(request()->routeIs('manager.establishment.setup'))
                    @foreach(['manager.dashboard'=>'Visão geral','manager.chargers.index'=>'Carregadores','manager.demand.index'=>'Demanda e tarifas','manager.chatbot.page'=>'Assistente IA'] as $destination=>$label)
                        <a href="{{ route($destination) }}" class="{{ request()->routeIs(str_replace(['index','page'], '*', $destination)) || request()->routeIs($destination) ? $navActive : $navInactive }}">{{ $label }}</a>
                    @endforeach
                @endunless
            </nav>

            {{-- PARTE INFERIOR DA SIDEBAR --}}
            <div class="mt-auto space-y-1 border-t border-[#e3e3e0] px-4 pb-6 pt-4 dark:border-[#343843]">

                {{-- SAIR --}}
                <form
                    method="POST"
                    action="{{ route('logout') }}"
                >
                    @csrf

                    <button
                        type="submit"
                        class="flex w-full items-center gap-3 rounded-lg px-4 py-2.5 text-sm font-medium text-[#706f6c] transition-colors hover:bg-[#e3e3e0]/50 hover:text-[#1b1b18] dark:text-[#a0a0a0] dark:hover:bg-[#343843] dark:hover:text-white"
                    >

                        <svg
                            class="h-5 w-5"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"
                            />
                        </svg>

                        <span>{{ __('app.logout') }}</span>

                    </button>

                </form>

            </div>

        </aside>

        {{-- CONTEÚDO PRINCIPAL --}}
        <main class="min-w-0 flex-1 lg:ml-64">

            <div class="mx-auto max-w-[1280px] px-5 py-8 sm:px-6 lg:px-8">

                <a href="{{ route('manager.dashboard') }}" class="mb-6 inline-flex lg:hidden">
                    <x-partner-brands compact />
                </a>

                <header class="mb-6 flex flex-wrap items-center justify-between gap-4">

                    <div class="min-w-0 max-w-full">
                        {{ $header ?? '' }}
                    </div>

                    <div class="flex flex-wrap items-center gap-2 sm:gap-3">

                        {{-- IDIOMA --}}
                        <x-lang-switcher variant="boxed" />

                        {{-- TEMA --}}
                        <x-theme-toggle variant="boxed" />

                        <div class="hidden h-8 border-l border-[#e3e3e0] dark:border-[#343843] sm:block"></div>

                        {{-- PERFIL --}}
                        <span
                            class="flex h-10 w-10 items-center justify-center rounded-full border border-[#e53935]/40 bg-[#e53935]/10 text-sm font-semibold text-[#e53935] transition-colors hover:bg-[#e53935]/20"
                            title="{{ Auth::user()->name }}"
                        >
                            {{ mb_strtoupper(mb_substr(Auth::user()->name ?? 'G', 0, 2)) }}
                        </span>

                        {{-- LOGOUT --}}
                        <form
                            method="POST"
                            action="{{ route('logout') }}"
                            class="inline"
                        >
                            @csrf

                            <button
                                type="submit"
                                class="flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg border border-gray-200 bg-gray-50 text-gray-500 transition-all hover:border-[#e53935]/40 hover:text-[#e53935] dark:border-[#343843] dark:bg-[#181b22] dark:text-[#a0a0a0] dark:hover:text-[#e53935]"
                                title="{{ __('app.logout') }}"
                            >

                                <svg
                                    class="h-4 w-4"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"
                                    />
                                </svg>

                            </button>

                        </form>

                    </div>

                </header>

                <x-flash-messages />

                <nav class="mb-6 flex flex-wrap gap-3 text-sm font-medium text-[#c62828] dark:text-red-300 lg:hidden" aria-label="Painel Gestor">
                    <a href="{{ route('manager.establishment.setup') }}">Meu Estabelecimento</a>
                    @unless(request()->routeIs('manager.establishment.setup'))
                        <a href="{{ route('manager.dashboard') }}">Visão geral</a>
                        <a href="{{ route('manager.chargers.index') }}">Carregadores</a>
                        <a href="{{ route('manager.demand.index') }}">Demanda e tarifas</a>
                        <a href="{{ route('manager.chatbot.page') }}">Assistente IA</a>
                    @endunless
                </nav>
                {{ $slot }}

            </div>

        </main>

    </div>

    <dialog data-manager-confirmation class="m-auto w-[calc(100%_-_2rem)] max-w-md rounded-xl border border-gray-200 bg-white p-6 text-gray-900 shadow-xl backdrop:bg-black/50 dark:border-[#343843] dark:bg-[#1a1a1a] dark:text-white" aria-labelledby="manager-confirm-title">
        <h2 id="manager-confirm-title" class="text-xl font-semibold">Confirmar alteração</h2>
        <p data-manager-confirmation-message class="mt-4 text-sm text-gray-600 dark:text-gray-300"></p>
        <div class="mt-6 flex flex-wrap justify-end gap-3"><button type="button" data-manager-confirmation-cancel class="rounded-lg border border-gray-300 px-4 py-2 text-sm dark:border-[#343843]">Voltar</button><button type="button" data-manager-confirmation-accept class="rounded-lg bg-[#c62828] px-4 py-2 text-sm font-semibold text-white">Confirmar</button></div>
    </dialog>
    <script src="{{ asset('js/theme.js') }}"></script>

</body>
</html>