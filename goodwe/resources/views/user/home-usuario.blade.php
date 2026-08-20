<x-user-layout>
    <x-slot name="header">
        <h1 class="text-3xl font-semibold">
            Olá, Lucas! 👋
        </h1>

        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
            Pronto para sua próxima recarga?
        </p>
    </x-slot>

    <section
        class="mt-7 min-h-[275px] overflow-hidden rounded-lg border border-gray-200 bg-cover bg-center p-6 shadow-sm dark:border-[#20262d] sm:p-8"
        style="background-image:
            linear-gradient(to right, rgba(7, 10, 13, 0.98) 0%, rgba(7, 10, 13, 0.92) 36%, rgba(7, 10, 13, 0.38) 66%, rgba(7, 10, 13, 0.08) 100%),
            url('{{ asset('imagens/banner-home-goodwe.png') }}');"
    >
        <div class="max-w-md">
            <p class="text-sm font-medium text-green-500">
                RECARREGUE COM FACILIDADE
            </p>

            <h2 class="mt-4 text-3xl font-semibold leading-tight text-white">
                Encontre um carregador e inicie sua recarga.
            </h2>

            <button
                type="button"
                class="mt-8 flex items-center gap-3 rounded-lg bg-green-500 px-6 py-3 text-sm font-semibold text-white hover:bg-green-600"
            >
                <i data-lucide="search" class="h-5 w-5"></i>
                <span>Encontrar carregador</span>
            </button>
        </div>
    </section>

    <section class="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-3">
        <div class="relative min-h-[150px] overflow-hidden rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-[#20262d] dark:bg-[#11161c]">
            <div class="flex items-center gap-4">
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-green-500/15 text-green-500">
                    <i data-lucide="wallet" class="h-7 w-7"></i>
                </div>

                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Saldo disponível
                    </p>

                    <p class="mt-1 text-3xl font-semibold">
                        R$ 120,50
                    </p>

                    <button
                        type="button"
                        class="mt-4 inline-flex items-center gap-2 text-sm font-medium text-green-500 hover:text-green-600"
                    >
                        <span>+ Adicionar saldo</span>
                        <i data-lucide="arrow-right" class="h-4 w-4"></i>
                    </button>
                </div>
            </div>

            <i data-lucide="wallet" class="absolute right-5 top-8 hidden h-20 w-20 text-gray-400 opacity-10 sm:block"></i>
        </div>

        <div class="relative min-h-[150px] overflow-hidden rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-[#20262d] dark:bg-[#11161c]">
            <div class="flex items-center gap-4">
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-violet-500/20 text-violet-400">
                    <i data-lucide="calendar-clock" class="h-7 w-7"></i>
                </div>

                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Próxima recarga
                    </p>

                    <p class="mt-1 text-3xl font-semibold">
                        Hoje, 18:30
                    </p>

                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Carregador 02
                    </p>

                    <button
                        type="button"
                        class="mt-3 inline-flex items-center gap-2 text-sm font-medium text-violet-400 hover:text-violet-300"
                    >
                        <span>Ver detalhes</span>
                        <i data-lucide="arrow-right" class="h-4 w-4"></i>
                    </button>
                </div>
            </div>

            <i data-lucide="calendar-clock" class="absolute right-5 top-8 hidden h-20 w-20 text-gray-400 opacity-10 sm:block"></i>
        </div>

        <div class="relative min-h-[150px] overflow-hidden rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-[#20262d] dark:bg-[#11161c]">
            <div class="flex items-center gap-4">
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-amber-500/20 text-amber-400">
                    <i data-lucide="star" class="h-7 w-7"></i>
                </div>

                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Pontos acumulados
                    </p>

                    <p class="mt-1 text-3xl font-semibold">
                        240 pts
                    </p>

                    <button
                        type="button"
                        class="mt-4 inline-flex items-center gap-2 text-sm font-medium text-amber-400 hover:text-amber-300"
                    >
                        <span>Ver benefícios</span>
                        <i data-lucide="arrow-right" class="h-4 w-4"></i>
                    </button>
                </div>
            </div>

            <i data-lucide="gift" class="absolute right-5 top-8 hidden h-20 w-20 text-gray-400 opacity-10 sm:block"></i>
        </div>
    </section>

    <section class="mt-5 grid grid-cols-1 gap-5 xl:grid-cols-[1fr_1.05fr]">
        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-[#20262d] dark:bg-[#11161c]">
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold">
                    Carregadores disponíveis próximos
                </h2>

                <button
                    type="button"
                    class="text-sm font-medium text-green-500 hover:text-green-600"
                >
                    Ver todos
                </button>
            </div>

            <div class="mt-5 space-y-5 border-t border-gray-200 pt-5 dark:border-[#20262d]">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-[1fr_auto_auto] sm:items-center">
                    <div class="flex items-center gap-4">
                        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-green-500/15 text-green-500">
                            <i data-lucide="battery-charging" class="h-7 w-7"></i>
                        </div>

                        <div>
                            <p class="font-semibold">
                                Carregador 01
                            </p>

                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Estacionamento FIAP • Piso G1
                            </p>

                            <div class="mt-2 flex items-center gap-1 text-sm text-green-500">
                                <i data-lucide="map-pin" class="h-4 w-4"></i>
                                <span>150 m</span>
                            </div>
                        </div>
                    </div>

                    <div class="sm:min-w-32">
                        <p class="flex items-center gap-2 text-sm font-medium text-green-500">
                            <span class="h-2 w-2 rounded-full bg-green-500"></span>
                            Disponível
                        </p>

                        <p class="mt-2 text-sm">
                            7,4 kW
                        </p>

                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Potência máxima
                        </p>
                    </div>

                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-100 dark:border-[#30363d] dark:hover:bg-[#1a1f25]"
                    >
                        <span>Ver detalhes</span>
                        <i data-lucide="chevron-right" class="h-4 w-4"></i>
                    </button>
                </div>

                <div class="border-t border-gray-200 pt-5 dark:border-[#20262d]">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-[1fr_auto_auto] sm:items-center">
                        <div class="flex items-center gap-4">
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-green-500/15 text-green-500">
                                <i data-lucide="battery-charging" class="h-7 w-7"></i>
                            </div>

                            <div>
                                <p class="font-semibold">
                                    Carregador 04
                                </p>

                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Estacionamento FIAP • Piso G2
                                </p>

                                <div class="mt-2 flex items-center gap-1 text-sm text-green-500">
                                    <i data-lucide="map-pin" class="h-4 w-4"></i>
                                    <span>320 m</span>
                                </div>
                            </div>
                        </div>

                        <div class="sm:min-w-32">
                            <p class="flex items-center gap-2 text-sm font-medium text-green-500">
                                <span class="h-2 w-2 rounded-full bg-green-500"></span>
                                Disponível
                            </p>

                            <p class="mt-2 text-sm">
                                11 kW
                            </p>

                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Potência máxima
                            </p>
                        </div>

                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-100 dark:border-[#30363d] dark:hover:bg-[#1a1f25]"
                        >
                            <span>Ver detalhes</span>
                            <i data-lucide="chevron-right" class="h-4 w-4"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="mt-5 flex items-center gap-3 rounded-lg bg-green-500/10 px-4 py-3 text-sm text-green-500">
                <i data-lucide="smartphone-nfc" class="h-5 w-5 shrink-0"></i>

                <span>
                    Aproxime seu celular da tag NFC no carregador para iniciar sua recarga.
                </span>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-[#20262d] dark:bg-[#11161c]">
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold">
                    Atividade recente
                </h2>

                <button
                    type="button"
                    class="inline-flex items-center gap-2 text-sm font-medium text-green-500 hover:text-green-600"
                >
                    <span>Ver histórico completo</span>
                    <i data-lucide="chevron-right" class="h-4 w-4"></i>
                </button>
            </div>

            <div class="mt-5 overflow-x-auto">
                <table class="w-full min-w-[620px] text-left text-sm">
                    <thead class="border-b border-gray-200 text-gray-500 dark:border-[#20262d] dark:text-gray-400">
                        <tr>
                            <th class="py-3 font-medium">Data</th>
                            <th class="py-3 font-medium">Carregador</th>
                            <th class="py-3 font-medium">Energia</th>
                            <th class="py-3 font-medium">Valor</th>
                            <th class="py-3 font-medium">Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr class="border-b border-gray-200 dark:border-[#20262d]">
                            <td class="py-4">16/08/2026 14:25</td>
                            <td class="py-4">Carregador 02</td>
                            <td class="py-4">22,4 kWh</td>
                            <td class="py-4">R$ 37,80</td>
                            <td class="py-4">
                                <span class="rounded-lg bg-green-500/10 px-3 py-1 text-green-500">
                                    Concluída
                                </span>
                            </td>
                        </tr>

                        <tr class="border-b border-gray-200 dark:border-[#20262d]">
                            <td class="py-4">12/08/2026 18:10</td>
                            <td class="py-4">Carregador 01</td>
                            <td class="py-4">18,7 kWh</td>
                            <td class="py-4">R$ 31,20</td>
                            <td class="py-4">
                                <span class="rounded-lg bg-green-500/10 px-3 py-1 text-green-500">
                                    Concluída
                                </span>
                            </td>
                        </tr>

                        <tr>
                            <td class="py-4">09/08/2026 09:40</td>
                            <td class="py-4">Carregador 03</td>
                            <td class="py-4">15,2 kWh</td>
                            <td class="py-4">R$ 25,10</td>
                            <td class="py-4">
                                <span class="rounded-lg bg-green-500/10 px-3 py-1 text-green-500">
                                    Concluída
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="mt-5 flex flex-col gap-4 rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-[#20262d] dark:bg-[#11161c] sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-green-500/15 text-green-500">
                <i data-lucide="gem" class="h-7 w-7"></i>
            </div>

            <div>
                <p class="text-lg font-semibold">
                    Indique amigos e ganhe pontos!
                </p>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Convide amigos para usar a GoodWe e ganhe pontos para trocar por créditos.
                </p>
            </div>
        </div>

        <button
            type="button"
            class="inline-flex items-center justify-center gap-2 rounded-lg border border-green-500 px-6 py-3 text-sm font-semibold text-green-500 hover:bg-green-500/10"
        >
            <span>Indicar agora</span>
            <i data-lucide="chevron-right" class="h-4 w-4"></i>
        </button>
    </section>
</x-user-layout>
