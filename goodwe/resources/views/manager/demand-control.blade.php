<x-manager-legacy-layout>
    <x-slot name="header"><div><p class="mb-2 text-sm font-semibold text-[#c62828] dark:text-red-300">ACOMPANHAMENTO</p><h1 class="text-3xl font-semibold">Controle de consumo</h1><p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Demanda ativa em tempo real dos totens de {{ $establishment->name }}</p></div></x-slot>

    @if(session('success'))<p class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-700/40 dark:bg-emerald-500/10 dark:text-emerald-200">{{ session('success') }}</p>@endif
    @if(session('error'))<p class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900 dark:border-red-700/40 dark:bg-red-500/10 dark:text-red-200">{{ session('error') }}</p>@endif

    @if($status['overload'])
        <p class="mb-6 rounded-lg border border-red-300 bg-red-50 p-4 text-sm font-semibold text-red-900 dark:border-red-700/60 dark:bg-red-500/10 dark:text-red-200">Atenção: a demanda ativa agregada excede a capacidade configurada da instalação. Novas recargas serão bloqueadas até o consumo recuar.</p>
    @elseif(! $status['limit_configured'])
        <p class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-700/40 dark:bg-amber-500/10 dark:text-amber-200">Nenhum limite elétrico configurado. Defina a potência e a corrente máximas em <a class="font-semibold underline" href="{{ route('manager.establishment.edit') }}#configurar-tarifas">Meu Estabelecimento</a> para bloquear recargas acima da capacidade.</p>
    @endif

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <section class="gw-card rounded-xl border border-gray-200 bg-white p-5 dark:border-[#343843] dark:bg-[#1a1a1a]">
            <p class="text-sm text-gray-500 dark:text-gray-400">Potência ativa total</p>
            <p class="mt-2 text-2xl font-semibold {{ $status['overload'] ? 'text-[#c62828] dark:text-red-300' : '' }}">{{ number_format($status['total_active_power_kw'], 3, ',', '.') }} <small class="text-base font-normal">kW</small></p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $status['max_power_kw'] !== null ? 'Limite: '.number_format($status['max_power_kw'], 2, ',', '.').' kW' : 'Sem limite configurado' }} @if($status['power_headroom_kw'] !== null) · folga {{ number_format($status['power_headroom_kw'], 3, ',', '.') }} kW @endif</p>
        </section>
        <section class="gw-card rounded-xl border border-gray-200 bg-white p-5 dark:border-[#343843] dark:bg-[#1a1a1a]">
            <p class="text-sm text-gray-500 dark:text-gray-400">Corrente ativa total</p>
            <p class="mt-2 text-2xl font-semibold {{ $status['overload'] ? 'text-[#c62828] dark:text-red-300' : '' }}">{{ number_format($status['total_active_current_a'], 3, ',', '.') }} <small class="text-base font-normal">A</small></p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $status['max_current_a'] !== null ? 'Limite: '.number_format($status['max_current_a'], 2, ',', '.').' A' : 'Sem limite configurado' }} @if($status['current_headroom_a'] !== null) · folga {{ number_format($status['current_headroom_a'], 3, ',', '.') }} A @endif</p>
        </section>
        <section class="gw-card rounded-xl border border-gray-200 bg-white p-5 dark:border-[#343843] dark:bg-[#1a1a1a]">
            <p class="text-sm text-gray-500 dark:text-gray-400">Totens em recarga (EM_ANDAMENTO)</p>
            <p class="mt-2 text-2xl font-semibold">{{ $status['active_sessions_count'] }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Sessões ativas no momento</p>
        </section>
        <section class="gw-card rounded-xl border border-gray-200 bg-white p-5 dark:border-[#343843] dark:bg-[#1a1a1a]">
            <p class="text-sm text-gray-500 dark:text-gray-400">Estado da instalação</p>
            <p class="mt-2 text-2xl font-semibold {{ $status['overload'] ? 'text-[#c62828] dark:text-red-300' : 'text-emerald-600 dark:text-emerald-400' }}">{{ $status['overload'] ? 'Sobrecarga' : 'Normal' }}</p>
            <form method="POST" action="{{ route('manager.demand-control.sync') }}" class="mt-3">@csrf<button type="submit" class="text-sm font-semibold text-[#c62828] dark:text-red-300">Sincronizar totens (Firestore)</button></form>
        </section>
    </div>

    <section id="leituras-totens" class="gw-card mt-6 min-w-0 rounded-xl border border-gray-200 bg-white p-5 dark:border-[#343843] dark:bg-[#1a1a1a]">
        <h2 class="mb-5 text-lg font-semibold">Leituras ativas por totem</h2>
        <p class="gw-table-hint">Deslize a tabela para ver todas as colunas.</p>
        <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Tabela com rolagem horizontal">
            <table class="w-full min-w-[620px] text-left text-sm">
                <thead><tr class="border-b border-gray-200 text-gray-500 dark:border-[#343843] dark:text-gray-400">
                    @foreach(['Totem','Potência (kW)','Corrente (A)','Tensão (V)','Medido em','Origem'] as $heading)<th class="px-3 py-3 font-medium">{{ $heading }}</th>@endforeach
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
                        <tr><td colspan="6" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">Nenhuma leitura ativa registrada nos últimos minutos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section id="sessoes-em-andamento" class="gw-card mt-6 min-w-0 rounded-xl border border-gray-200 bg-white p-5 dark:border-[#343843] dark:bg-[#1a1a1a]">
        <h2 class="mb-5 text-lg font-semibold">Sessões em andamento</h2>
        <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Tabela com rolagem horizontal">
            <table class="w-full min-w-[620px] text-left text-sm">
                <thead><tr class="border-b border-gray-200 text-gray-500 dark:border-[#343843] dark:text-gray-400">
                    @foreach(['Sessão','Totem','Usuário','Energia (kWh)','Iniciada em'] as $heading)<th class="px-3 py-3 font-medium">{{ $heading }}</th>@endforeach
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
                        <tr><td colspan="5" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">Nenhuma recarga em andamento.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-manager-legacy-layout>
