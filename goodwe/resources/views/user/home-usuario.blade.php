<x-user-layout>
    <x-slot name="header">
        <h1 class="text-3xl font-semibold text-gray-900 dark:text-white">
            {{ __('home.greeting', ['name' => Auth::user()->name ?? '']) }}
        </h1>

        <p class="mt-2 text-sm text-gray-500 dark:text-[#a0a0a0]">
            {{ __('home.ready') }}
        </p>
    </x-slot>

    <section
        class="mt-7 min-h-[275px] overflow-hidden rounded-lg border border-gray-200 bg-cover bg-center p-6 shadow-sm dark:border-[#343843] sm:p-8"
        style="background-image:
            linear-gradient(to right, rgba(7, 10, 13, 0.98) 0%, rgba(7, 10, 13, 0.92) 36%, rgba(7, 10, 13, 0.38) 66%, rgba(7, 10, 13, 0.08) 100%),
            url('{{ asset('imagens/banner-home-goodwe.png') }}');"
    >
        <div class="max-w-md">
            <p class="text-sm font-medium text-[#e53935]">
                {{ __('home.recharge_easy') }}
            </p>

            <h2 class="mt-4 text-3xl font-semibold leading-tight text-white">
                {{ __('home.find_charger_desc') }}
            </h2>

            <a
                href="{{ route('chargers.index') }}"
                class="mt-8 inline-flex items-center gap-3 rounded-lg bg-[#e53935] px-6 py-3 text-sm font-semibold text-white hover:bg-[#c62828] transition-colors"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
                <span>{{ __('home.find_charger') }}</span>
            </a>
        </div>
    </section>

    <section class="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-3">
        <div class="relative min-h-[150px] overflow-hidden rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-[#343843] dark:bg-[#1a1a1a]">
            <div class="flex items-center gap-4">
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-[#e53935]/10 text-[#e53935]">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 01-1.5 1.5H5.25a1.5 1.5 0 01-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 109.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1114.625 7.5H12m0 0V21m-8.625-9.75h18c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-18c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                    </svg>
                </div>

                <div>
                    <p class="text-sm text-gray-500 dark:text-[#a0a0a0]">
                        {{ __('home.available_balance') }}
                    </p>

                    <p class="mt-1 text-3xl font-semibold text-gray-900 dark:text-white">
                        R$ {{ number_format(Auth::user()->balance ?? 0, 2, ',', '.') }}
                    </p>

                    <a
                        href="{{ route('wallet.deposit.form') }}"
                        class="mt-4 inline-flex items-center gap-2 text-sm font-medium text-[#e53935] hover:text-[#c62828]"
                    >
                        <span>{{ __('home.add_balance') }}</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                        </svg>
                    </a>
                </div>
            </div>
        </div>

        <div class="relative min-h-[150px] overflow-hidden rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-[#343843] dark:bg-[#1a1a1a]">
            <div class="flex items-center gap-4">
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-violet-500/20 text-violet-400">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                    </svg>
                </div>

                <div>
                    <p class="text-sm text-gray-500 dark:text-[#a0a0a0]">
                        {{ __('home.next_charge') }}
                    </p>

                    <p class="mt-1 text-3xl font-semibold text-gray-900 dark:text-white">
                        Hoje, 18:30
                    </p>

                    <p class="mt-1 text-sm text-gray-500 dark:text-[#a0a0a0]">
                        Carregador 02
                    </p>

                    <button
                        type="button"
                        class="mt-3 inline-flex items-center gap-2 text-sm font-medium text-violet-400 hover:text-violet-300"
                    >
                        <span>{{ __('home.see_details') }}</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <div class="relative min-h-[150px] overflow-hidden rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-[#343843] dark:bg-[#1a1a1a]">
            <div class="flex items-center gap-4">
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-amber-500/20 text-amber-400">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
                    </svg>
                </div>

                <div>
                    <p class="text-sm text-gray-500 dark:text-[#a0a0a0]">
                        {{ __('home.points_accumulated') }}
                    </p>

                    <p class="mt-1 text-3xl font-semibold text-gray-900 dark:text-white">
                        {{ number_format(Auth::user()->points ?? 0, 0, ',', '.') }} pts
                    </p>

                    <a
                        href="{{ route('wallet.index') }}"
                        class="mt-4 inline-flex items-center gap-2 text-sm font-medium text-amber-400 hover:text-amber-300"
                    >
                        <span>{{ __('home.see_benefits') }}</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="mt-5 grid grid-cols-1 gap-5 xl:grid-cols-[1fr_1.05fr]">
        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-[#343843] dark:bg-[#1a1a1a]">
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                    {{ __('home.nearby_chargers') }}
                </h2>

                <a
                    href="{{ route('chargers.index') }}"
                    class="text-sm font-medium text-[#e53935] hover:text-[#c62828]"
                >
                    {{ __('home.see_all') }}
                </a>
            </div>

            <div class="mt-5 space-y-5 border-t border-gray-200 pt-5 dark:border-[#343843]">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-[1fr_auto_auto] sm:items-center">
                    <div class="flex items-center gap-4">
                        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-[#e53935]/10 text-[#e53935]">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                            </svg>
                        </div>

                        <div>
                            <p class="font-semibold text-gray-900 dark:text-white">
                                Carregador 01
                            </p>

                            <p class="mt-1 text-sm text-gray-500 dark:text-[#a0a0a0]">
                                Estacionamento FIAP &bull; Piso G1
                            </p>

                            <div class="mt-2 flex items-center gap-1 text-sm text-[#e53935]">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                                </svg>
                                <span>150 m</span>
                            </div>
                        </div>
                    </div>

                    <div class="sm:min-w-32">
                        <p class="flex items-center gap-2 text-sm font-medium text-[#e53935]">
                            <span class="h-2 w-2 rounded-full bg-[#e53935]"></span>
                            {{ __('home.available_status') }}
                        </p>

                        <p class="mt-2 text-sm text-gray-900 dark:text-white">
                            7,4 kW
                        </p>

                        <p class="text-xs text-gray-500 dark:text-[#a0a0a0]">
                            {{ __('home.max_power') }}
                        </p>
                    </div>

                    <a
                        href="{{ route('chargers.index') }}"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-100 dark:border-[#343843] dark:hover:bg-[#22252d] transition-colors text-gray-900 dark:text-white"
                    >
                        <span>{{ __('home.see_details') }}</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </a>
                </div>

                <div class="border-t border-gray-200 pt-5 dark:border-[#343843]">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-[1fr_auto_auto] sm:items-center">
                        <div class="flex items-center gap-4">
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-[#e53935]/10 text-[#e53935]">
                                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                                </svg>
                            </div>

                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">
                                    Carregador 04
                                </p>

                                <p class="mt-1 text-sm text-gray-500 dark:text-[#a0a0a0]">
                                    Estacionamento FIAP &bull; Piso G2
                                </p>

                                <div class="mt-2 flex items-center gap-1 text-sm text-[#e53935]">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                                    </svg>
                                    <span>320 m</span>
                                </div>
                            </div>
                        </div>

                        <div class="sm:min-w-32">
                            <p class="flex items-center gap-2 text-sm font-medium text-[#e53935]">
                                <span class="h-2 w-2 rounded-full bg-[#e53935]"></span>
                                {{ __('home.available_status') }}
                            </p>

                            <p class="mt-2 text-sm text-gray-900 dark:text-white">
                                11 kW
                            </p>

                            <p class="text-xs text-gray-500 dark:text-[#a0a0a0]">
                                {{ __('home.max_power') }}
                            </p>
                        </div>

                        <a
                            href="{{ route('chargers.index') }}"
                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-100 dark:border-[#343843] dark:hover:bg-[#22252d] transition-colors text-gray-900 dark:text-white"
                        >
                            <span>{{ __('home.see_details') }}</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                            </svg>
                        </a>
                    </div>
                </div>
            </div>

            <div class="mt-5 flex items-center gap-3 rounded-lg bg-[#e53935]/10 px-4 py-3 text-sm text-[#e53935]">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />
                </svg>

                <span>
                    {{ __('home.nfc_tip') }}
                </span>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-[#343843] dark:bg-[#1a1a1a]">
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                    {{ __('home.recent_activity') }}
                </h2>

                <button
                    type="button"
                    class="inline-flex items-center gap-2 text-sm font-medium text-[#e53935] hover:text-[#c62828]"
                >
                    <span>{{ __('home.see_full_history') }}</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                    </svg>
                </button>
            </div>

            <div class="mt-5 overflow-x-auto">
                <table class="w-full min-w-[620px] text-left text-sm">
                    <thead class="border-b border-gray-200 text-gray-500 dark:border-[#343843] dark:text-[#a0a0a0]">
                        <tr>
                            <th class="py-3 font-medium">{{ __('home.table_date') }}</th>
                            <th class="py-3 font-medium">{{ __('home.table_charger') }}</th>
                            <th class="py-3 font-medium">{{ __('home.table_energy') }}</th>
                            <th class="py-3 font-medium">{{ __('home.table_value') }}</th>
                            <th class="py-3 font-medium">{{ __('home.table_status') }}</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr class="border-b border-gray-200 dark:border-[#343843]">
                            <td class="py-4 text-gray-900 dark:text-white">16/08/2026 14:25</td>
                            <td class="py-4 text-gray-900 dark:text-white">Carregador 02</td>
                            <td class="py-4 text-gray-900 dark:text-white">22,4 kWh</td>
                            <td class="py-4 text-gray-900 dark:text-white">R$ 37,80</td>
                            <td class="py-4">
                                <span class="rounded-lg bg-[#e53935]/10 px-3 py-1 text-[#e53935]">
                                    {{ __('home.status_completed') }}
                                </span>
                            </td>
                        </tr>

                        <tr class="border-b border-gray-200 dark:border-[#343843]">
                            <td class="py-4 text-gray-900 dark:text-white">12/08/2026 18:10</td>
                            <td class="py-4 text-gray-900 dark:text-white">Carregador 01</td>
                            <td class="py-4 text-gray-900 dark:text-white">18,7 kWh</td>
                            <td class="py-4 text-gray-900 dark:text-white">R$ 31,20</td>
                            <td class="py-4">
                                <span class="rounded-lg bg-[#e53935]/10 px-3 py-1 text-[#e53935]">
                                    {{ __('home.status_completed') }}
                                </span>
                            </td>
                        </tr>

                        <tr>
                            <td class="py-4 text-gray-900 dark:text-white">09/08/2026 09:40</td>
                            <td class="py-4 text-gray-900 dark:text-white">Carregador 03</td>
                            <td class="py-4 text-gray-900 dark:text-white">15,2 kWh</td>
                            <td class="py-4 text-gray-900 dark:text-white">R$ 25,10</td>
                            <td class="py-4">
                                <span class="rounded-lg bg-[#e53935]/10 px-3 py-1 text-[#e53935]">
                                    {{ __('home.status_completed') }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="mt-5 flex flex-col gap-4 rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-[#343843] dark:bg-[#1a1a1a] sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-[#e53935]/10 text-[#e53935]">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z" />
                </svg>
            </div>

            <div>
                <p class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ __('home.referral_title') }}
                </p>

                <p class="mt-1 text-sm text-gray-500 dark:text-[#a0a0a0]">
                    {{ __('home.referral_desc') }}
                </p>
            </div>
        </div>

        <button
            type="button"
            class="inline-flex items-center justify-center gap-2 rounded-lg border border-[#e53935] px-6 py-3 text-sm font-semibold text-[#e53935] hover:bg-[#e53935]/10 transition-colors"
        >
            <span>{{ __('home.referral_button') }}</span>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
        </button>
    </section>
</x-user-layout>
