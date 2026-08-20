<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>GoodWe</title>

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

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])
</head>

<body class="min-h-screen bg-[#f5f7f8] text-[#1f2937] dark:bg-[#070a0d] dark:text-white">
    <div class="flex min-h-screen">
        <aside class="hidden w-64 shrink-0 flex-col border-r border-gray-200 bg-white dark:border-[#20262d] dark:bg-[#080c10] lg:flex">
            <div class="px-6 pb-8 pt-10">
                <img
                    src="{{ asset('imagens/goodwe-logo.png') }}"
                    alt="GoodWe"
                    class="w-28"
                >
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Portal do Usuário
                </p>
            </div>

            <nav class="space-y-2 px-4">
                <a href="#" class="flex items-center gap-4 rounded-lg border border-green-500/60 bg-green-500/10 px-4 py-3 font-medium text-green-500">
                    <i data-lucide="house" class="h-6 w-6"></i>
                    <span>Início</span>
                </a>
                <a href="#" class="flex items-center gap-4 rounded-lg px-4 py-3 font-medium
                                    text-gray-600 hover:bg-gray-100 hover:text-gray-900
                                    dark:text-gray-400 dark:hover:bg-[#151a20] dark:hover:text-white">
                    <i data-lucide="zap" class="h-6 w-6"></i>
                    <span>Recarga</span>
                </a>
                <a href="#" class="flex items-center gap-4 rounded-lg px-4 py-3 font-medium
                                    text-gray-600 hover:bg-gray-100 hover:text-gray-900
                                    dark:text-gray-400 dark:hover:bg-[#151a20] dark:hover:text-white">
                    <i data-lucide="wallet" class="h-6 w-6"></i>
                    <span>Carteira</span>
                </a>
                <a href="#" class="flex items-center gap-4 rounded-lg px-4 py-3 font-medium
                                    text-gray-600 hover:bg-gray-100 hover:text-gray-900
                                    dark:text-gray-400 dark:hover:bg-[#151a20] dark:hover:text-white">
                    <i data-lucide="history" class="h-6 w-6"></i>
                    <span>Histórico</span>
                </a>
                <a href="#" class="flex items-center gap-4 rounded-lg px-4 py-3 font-medium
                                    text-gray-600 hover:bg-gray-100 hover:text-gray-900
                                    dark:text-gray-400 dark:hover:bg-[#151a20] dark:hover:text-white">
                    <i data-lucide="circle-help" class="h-6 w-6"></i>
                    <span>Ajuda</span>
                </a>
            </nav>

            <div class="mt-auto space-y-2 border-t border-gray-200 px-4 pb-8 pt-6 dark:border-[#20262d]">
                <a href="#" class="flex items-center gap-4 rounded-lg px-4 py-3 font-medium
                                    text-gray-600 hover:bg-gray-100 hover:text-gray-900
                                    dark:text-gray-400 dark:hover:bg-[#151a20] dark:hover:text-white">
                    <i data-lucide="user" class="h-6 w-6"></i>
                    <span>Meu Perfil</span>
                </a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <button type="submit" class="flex w-full items-center gap-4 rounded-lg px-4 py-3 font-medium
                                                text-gray-600 hover:bg-gray-100 hover:text-gray-900
                                                dark:text-gray-400 dark:hover:bg-[#151a20] dark:hover:text-white">
                        <i data-lucide="log-out" class="h-6 w-6"></i>
                        <span>Sair</span>
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

                    <div class="flex items-center gap-4">
                        <button
                            type="button"
                            class="relative rounded-lg p-2 text-gray-500 hover:bg-gray-200 dark:text-gray-400 dark:hover:bg-[#151a20]"
                        >
                            <i data-lucide="bell" class="h-5 w-5"></i>
                            <span class="absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-green-500 text-xs font-semibold text-white">
                                2
                            </span>
                        </button>

                        <div class="hidden h-8 border-l border-gray-200 dark:border-[#20262d] sm:block"></div>

                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full border border-green-500 bg-green-500/15 text-sm font-semibold text-green-500">
                                LS
                            </div>

                            <div class="hidden sm:block">
                                <p class="text-sm font-medium">
                                    Lucas Silva
                                </p>

                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Usuário
                                </p>
                            </div>

                            <i data-lucide="chevron-down" class="hidden h-4 w-4 text-gray-500 dark:text-gray-400 sm:block"></i>
                        </div>
                    </div>
                </header>

                {{ $slot }}
            </div>
        </main>

    </div>
</body>

</html>
