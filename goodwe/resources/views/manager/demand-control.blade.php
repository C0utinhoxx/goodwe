<x-manager-legacy-layout>
    <x-slot name="header"><div><p class="mb-2 text-sm font-semibold text-[#c62828] dark:text-red-300">{{ __('bulk.acompanhamento') }}</p><h1 class="text-3xl font-semibold">{{ __('bulk.controle_de_consumo') }}</h1><p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('review.active_live', ['name' => $establishment->name]) }}</p></div></x-slot>

    @if(session('success'))<p class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-700/40 dark:bg-emerald-500/10 dark:text-emerald-200">{{ session('success') }}</p>@endif
    @if(session('error'))<p class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900 dark:border-red-700/40 dark:bg-red-500/10 dark:text-red-200">{{ session('error') }}</p>@endif

    @if($status['overload'])
        <p class="mb-6 rounded-lg border border-red-300 bg-red-50 p-4 text-sm font-semibold text-red-900 dark:border-red-700/60 dark:bg-red-500/10 dark:text-red-200">{{ __('review.overload') }}</p>
    @elseif(! $status['limit_configured'])
        <p class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-700/40 dark:bg-amber-500/10 dark:text-amber-200">{{ __('review.no_limit_before') }} <a class="font-semibold underline" href="{{ route('manager.establishment.edit') }}#configurar-tarifas">{{ __('bulk.meu_estabelecimento') }}</a> {{ __('review.no_limit_after') }}</p>
    @endif

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <section class="gw-card rounded-xl border border-gray-200 bg-white p-5 dark:border-[#343843] dark:bg-[#1a1a1a]">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('bulk.potencia_ativa_total') }}</p>
            <p class="mt-2 text-2xl font-semibold {{ $status['overload'] ? 'text-[#c62828] dark:text-red-300' : '' }}">{{ number_format($status['total_active_power_kw'], 3, ',', '.') }} <small class="text-base font-normal">kW</small></p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $status['max_power_kw'] !== null ? __('review.limit', ['value' => number_format($status['max_power_kw'], 2, ',', '.'), 'unit' => 'kW']) : __('review.no_limit') }} @if($status['power_headroom_kw'] !== null) · {{ __('review.headroom') }} {{ number_format($status['power_headroom_kw'], 3, ',', '.') }} kW @endif</p>
        </section>
        <section class="gw-card rounded-xl border border-gray-200 bg-white p-5 dark:border-[#343843] dark:bg-[#1a1a1a]">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('bulk.corrente_ativa_total') }}</p>
            <p class="mt-2 text-2xl font-semibold {{ $status['overload'] ? 'text-[#c62828] dark:text-red-300' : '' }}">{{ number_format($status['total_active_current_a'], 3, ',', '.') }} <small class="text-base font-normal">A</small></p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $status['max_current_a'] !== null ? __('review.limit', ['value' => number_format($status['max_current_a'], 2, ',', '.'), 'unit' => 'A']) : __('review.no_limit') }} @if($status['current_headroom_a'] !== null) · {{ __('review.headroom') }} {{ number_format($status['current_headroom_a'], 3, ',', '.') }} A @endif</p>
        </section>
        <section class="gw-card rounded-xl border border-gray-200 bg-white p-5 dark:border-[#343843] dark:bg-[#1a1a1a]">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('bulk.totens_em_recarga_em_andamento') }}</p>
            <p class="mt-2 text-2xl font-semibold">{{ $status['active_sessions_count'] }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('bulk.sessoes_ativas_no_momento') }}</p>
        </section>
        <section class="gw-card rounded-xl border border-gray-200 bg-white p-5 dark:border-[#343843] dark:bg-[#1a1a1a]">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('bulk.estado_da_instalacao') }}</p>
            <p class="mt-2 text-2xl font-semibold {{ $status['overload'] ? 'text-[#c62828] dark:text-red-300' : 'text-emerald-600 dark:text-emerald-400' }}">{{ $status['overload'] ? __('review.overload_status') : __('review.status_normal') }}</p>
            <form method="POST" action="{{ route('manager.demand-control.sync') }}" class="mt-3">@csrf<button type="submit" class="text-sm font-semibold text-[#c62828] dark:text-red-300">{{ __('bulk.sincronizar_totens_firestore') }}</button></form>
        </section>
    </div>

    <section id="leituras-totens" class="gw-card mt-6 min-w-0 rounded-xl border border-gray-200 bg-white p-5 dark:border-[#343843] dark:bg-[#1a1a1a]">
        <h2 class="mb-5 text-lg font-semibold">{{ __('bulk.leituras_ativas_por_totem') }}</h2>
        <p class="gw-table-hint">{{ __('bulk.deslize_a_tabela_para_ver_todas_as_colunas') }}</p>
        <div class="overflow-x-auto" tabindex="0" role="region" aria-label="{{ __('review.horizontal_table') }}">
            <table class="w-full min-w-[620px] text-left text-sm">
                <thead><tr class="border-b border-gray-200 text-gray-500 dark:border-[#343843] dark:text-gray-400">
                    @foreach([__('review.heading_totem'),__('review.heading_power'),__('review.heading_current'),__('review.heading_voltage'),__('review.heading_measured'),__('review.heading_source')] as $heading)<th class="px-3 py-3 font-medium">{{ $heading }}</th>@endforeach
                </tr></thead>
                <tbody class="divide-y divide-gray-100 dark:divide-[#23262d]">
                    @forelse($status['readings'] as $reading)
                        <tr>
                            <td class="px-3 py-3 font-medium">{{ $reading['charger_name'] ?? '—' }}</td>
                            <td class="px-3 py-3">{{ number_format($reading['active_power_kw'], 3, ',', '.') }}</td>
                            <td class="px-3 py-3">{{ number_format($reading['current_a'], 3, ',', '.') }}</td>
                            <td class="px-3 py-3">{{ $reading['voltage_v'] !== null ? number_format($reading['voltage_v'], 2, ',', '.') : '—' }}</td>
                            <td class="px-3 py-3">{{ $reading['recorded_at'] ? \Illuminate\Support\Carbon::parse($reading['recorded_at'])->format('d/m/Y H:i:s') : '—' }}</td>
                            <td class="px-3 py-3">{{ ucfirst($reading['source']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">{{ __('bulk.nenhuma_leitura_ativa_registrada_nos_ultimos_minutos') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section id="sessoes-em-andamento" class="gw-card mt-6 min-w-0 rounded-xl border border-gray-200 bg-white p-5 dark:border-[#343843] dark:bg-[#1a1a1a]">
        <h2 class="mb-5 text-lg font-semibold">{{ __('bulk.sessoes_em_andamento') }}</h2>
        <div class="overflow-x-auto" tabindex="0" role="region" aria-label="{{ __('review.horizontal_table') }}">
            <table class="w-full min-w-[620px] text-left text-sm">
                <thead><tr class="border-b border-gray-200 text-gray-500 dark:border-[#343843] dark:text-gray-400">
                    @foreach([__('review.heading_session'),__('review.heading_totem'),__('review.heading_user'),__('review.heading_energy'),__('review.heading_started')] as $heading)<th class="px-3 py-3 font-medium">{{ $heading }}</th>@endforeach
                </tr></thead>
                <tbody class="divide-y divide-gray-100 dark:divide-[#23262d]">
                    @forelse($status['active_sessions'] as $session)
                        <tr>
                            <td class="px-3 py-3 font-medium">#{{ $session['id'] }}</td>
                            <td class="px-3 py-3">{{ $session['charger_name'] }}</td>
                            <td class="px-3 py-3">{{ $session['user_name'] ?? '—' }}</td>
                            <td class="px-3 py-3">{{ $session['measured_energy_kwh'] !== null ? number_format($session['measured_energy_kwh'], 4, ',', '.') : '—' }}</td>
                            <td class="px-3 py-3">{{ $session['started_at'] ? \Illuminate\Support\Carbon::parse($session['started_at'])->format('d/m/Y H:i:s') : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">{{ __('bulk.nenhuma_recarga_em_andamento') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-manager-legacy-layout>
