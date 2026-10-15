<x-user-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-semibold text-gray-900 dark:text-white">
                    {{ __('chargers.title') }}
                </h1>

                <p class="mt-2 text-sm text-gray-500 dark:text-[#a0a0a0]">
                    {{ __('chargers.available_count', ['count' => count($chargers)]) }}
                </p>
            </div>

            <div class="flex items-center gap-2 rounded-full border border-[#e53935]/30 bg-[#e53935]/5 px-3 py-1.5">
                <span class="relative flex h-2.5 w-2.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#e53935] opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-[#e53935]"></span>
                </span>
                <span class="text-xs text-[#e53935] font-medium">{{ __('chargers.live_status') }}</span>
            </div>
        </div>
    </x-slot>

    <x-flash-messages />

    <section class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-2">
        @foreach ($chargers as $charger)
            @php
                $power = $charger->powerKw;
                $tier = $power >= 50
                    ? ['label' => __('chargers.tier_ultra'), 'class' => 'bg-[#e53935]/10 text-[#e53935]']
                    : ($power >= 22
                        ? ['label' => __('chargers.tier_fast'), 'class' => 'bg-amber-500/15 text-amber-400']
                        : ['label' => __('chargers.tier_standard'), 'class' => 'bg-emerald-500/15 text-emerald-400']);
                $price = number_format($power * 0.9, 2, ',', '.');
                $available = $charger->isAvailable();
                $remaining = $charger->availableInMinutes();
                $progress = $remaining !== null ? min(100, max(0, round($remaining / 120 * 100))) : 0;
            @endphp

            <div class="group relative overflow-hidden rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-[#343843] dark:bg-[#1a1a1a] transition-all duration-300 hover:border-[#e53935]/40 dark:hover:border-[#e53935]/30 hover:shadow-[0_0_20px_rgba(229,57,53,0.08)]">
                <div class="absolute inset-0 rounded-lg bg-gradient-to-br from-[#e53935]/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none"></div>

                <div class="relative flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg {{ $available ? 'bg-[#e53935]/10 text-[#e53935]' : 'bg-gray-200 dark:bg-[#343843] text-gray-400 dark:text-[#a0a0a0]' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="font-medium text-gray-900 dark:text-white">{{ $charger->name }}</h2>
                            <p class="text-xs text-gray-500 dark:text-[#a0a0a0]">{{ $charger->location }}</p>
                            <p class="mt-0.5 text-[11px] font-mono text-gray-400 dark:text-[#8a8f98]">{{ __('chargers.id_label') }}: {{ $charger->id }}</p>
                        </div>
                    </div>

                    <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium {{ $available ? 'bg-[#e53935]/10 text-[#e53935]' : 'bg-gray-200 dark:bg-[#343843] text-gray-400 dark:text-[#a0a0a0]' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $available ? 'bg-[#e53935]' : 'bg-gray-400 dark:bg-[#a0a0a0]' }}"></span>
                        {{ $available ? __('chargers.available') : __('chargers.occupied') }}
                    </span>
                </div>

                <div class="relative mt-4 flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium {{ $tier['class'] }}">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                        </svg>
                        {{ $tier['label'] }}
                    </span>
                    <span class="inline-flex items-center gap-1 rounded-md bg-gray-100 dark:bg-[#181b22] px-2 py-1 text-xs font-medium text-gray-600 dark:text-[#a0a0a0]">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17l-5.384 3.18A1.5 1.5 0 014 16.994V6.506a1.5 1.5 0 012.036-1.356l5.384 3.18" />
                        </svg>
                        {{ $power }} kW
                    </span>
                    <span class="inline-flex items-center gap-1 rounded-md bg-gray-100 dark:bg-[#181b22] px-2 py-1 text-xs font-medium text-gray-600 dark:text-[#a0a0a0]">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
                        </svg>
                        {{ $charger->connector }}
                    </span>
                </div>

                <div class="relative mt-3 flex items-center justify-between rounded-md bg-gray-50 dark:bg-[#181b22] px-3 py-2">
                    <span class="text-xs text-gray-500 dark:text-[#a0a0a0]">{{ __('chargers.estimated_cost') }}</span>
                    <span class="text-sm font-semibold text-gray-900 dark:text-white">R$ {{ $price }} <span class="text-xs font-normal text-gray-400">/h</span></span>
                </div>

                {{-- Controle remoto do totem deste ponto de recarga --}}
                <a
                    href="{{ route('totens.carregar', $charger->id) }}"
                    class="relative mt-3 inline-flex w-full items-center justify-center gap-2 rounded-lg border border-[#e53935]/30 px-4 py-2.5 text-sm font-medium text-[#e53935] transition-all hover:border-[#e53935]/50 hover:bg-[#e53935]/10 active:scale-[0.98]"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                    </svg>
                    {{ __('totens.open_control') }}
                </a>

                @if ($available)
                    <form method="POST" action="{{ route('chargers.schedule', $charger->id) }}" class="relative mt-4 flex flex-col gap-2 sm:flex-row">
                        @csrf
                        <input
                            type="text"
                            name="vehicle_plate"
                            placeholder="{{ __('chargers.plate_placeholder') }}"
                            required
                            maxlength="8"
                            class="flex-1 rounded-md border border-gray-300 dark:border-[#343843] bg-gray-50 dark:bg-[#181b22] px-3.5 py-2.5 text-sm text-gray-900 dark:text-white placeholder:text-gray-400 dark:placeholder:text-[#8a8f98] focus:outline-none focus:border-[#e53935] transition-all"
                        >
                        <select
                            name="duration_minutes"
                            class="rounded-md border border-gray-300 dark:border-[#343843] bg-gray-50 dark:bg-[#181b22] px-3.5 py-2.5 text-sm text-gray-900 dark:text-white focus:outline-none focus:border-[#e53935] transition-all cursor-pointer"
                        >
                            <option value="15">15 min</option>
                            <option value="30" selected>30 min</option>
                            <option value="45">45 min</option>
                            <option value="60">60 min</option>
                            <option value="120">120 min</option>
                        </select>
                        <button
                            type="submit"
                            class="rounded-lg bg-[#e53935] px-5 py-2.5 text-sm font-medium text-white transition-all hover:bg-[#c62828] hover:shadow-[0_0_15px_rgba(229,57,53,0.3)] active:scale-[0.98]"
                        >
                            {{ __('chargers.schedule_button') }}
                        </button>
                    </form>
                @else
                    <div class="relative mt-4">
                        <div class="flex items-center justify-between text-xs text-gray-500 dark:text-[#a0a0a0] mb-1.5">
                            <span class="inline-flex items-center gap-1.5">
                                <span class="relative flex h-2 w-2">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#e53935] opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-[#e53935]"></span>
                                </span>
                                {{ __('chargers.occupied_session') }}
                            </span>
                            <span>{{ __('chargers.releases_in', ['minutes' => $remaining]) }}</span>
                        </div>
                        <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-[#343843]">
                            <div class="h-full rounded-full bg-[#e53935] transition-all" style="width: {{ $progress }}%"></div>
                        </div>

                        <div class="mt-3 flex items-center justify-between gap-3 rounded-md border border-gray-200 dark:border-[#343843] bg-gray-100 dark:bg-[#181b22] p-3">
                            <p class="text-sm text-gray-500 dark:text-[#a0a0a0]">
                                {{ __('chargers.occupied_by') }}
                                <span class="font-medium text-gray-900 dark:text-white">{{ $charger->vehiclePlate }}</span>
                            </p>
                            <form method="POST" action="{{ route('chargers.release', $charger->id) }}">
                                @csrf
                                <button type="submit" class="shrink-0 rounded-md border border-[#e53935]/30 px-3 py-1.5 text-xs font-medium text-[#e53935] transition-all hover:bg-[#e53935]/10 hover:border-[#e53935]/50 active:scale-[0.98]">
                                    {{ __('chargers.release_button') }}
                                </button>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </section>

    <div class="mt-8 text-center">
        <p class="text-sm text-gray-400 dark:text-[#b0b4bc] mb-1">{{ __('app.footer_tagline') }}</p>
        <span class="text-xs text-gray-400 dark:text-[#8a8f98]">{{ __('app.footer_copyright') }}</span>
    </div>
</x-user-layout>
