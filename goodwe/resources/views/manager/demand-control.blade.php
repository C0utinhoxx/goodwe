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

    @php
        $simulationBanner = match ($simulation['status']) {
            'blocked' => ['label' => 'Nova recarga bloqueada', 'classes' => 'border-red-300 bg-red-50 text-red-900 dark:border-red-700/60 dark:bg-red-500/10 dark:text-red-200'],
            'balanced' => ['label' => 'Redistribuição necessária', 'classes' => 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-700/40 dark:bg-amber-500/10 dark:text-amber-200'],
            'within_capacity' => ['label' => 'Dentro da capacidade', 'classes' => 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-700/40 dark:bg-emerald-500/10 dark:text-emerald-200'],
            'unlimited' => ['label' => 'Sem limite configurado', 'classes' => 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-700/40 dark:bg-amber-500/10 dark:text-amber-200'],
            'empty' => ['label' => 'Aguardando seleção', 'classes' => 'border-gray-200 bg-gray-50 text-gray-700 dark:border-[#343843] dark:bg-[#181b22] dark:text-gray-300'],
            default => ['label' => 'Aguardando seleção', 'classes' => 'border-gray-200 bg-gray-50 text-gray-700 dark:border-[#343843] dark:bg-[#181b22] dark:text-gray-300'],
        };
    @endphp

    <section id="distribuicao-inteligente" class="gw-card mt-6 min-w-0 rounded-xl border border-gray-200 bg-white p-5 dark:border-[#343843] dark:bg-[#1a1a1a]">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <p class="mb-2 text-sm font-semibold text-[#c62828] dark:text-red-300">SIMULAÇÃO</p>
                <h2 class="text-lg font-semibold">Distribuição inteligente de energia</h2>
                <p class="mt-2 max-w-3xl text-sm text-gray-500 dark:text-gray-400">Informe quantos carregadores estão solicitando recarga e a potência pedida por cada um. Se a demanda total ultrapassar a capacidade da instalação, o sistema sugere uma divisão equilibrada, respeitando o limite de cada equipamento.</p>
            </div>
            <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800 dark:border-amber-700/40 dark:bg-amber-500/10 dark:text-amber-200">Demonstração</span>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-[#343843] dark:bg-[#181b22]">
                <p class="text-sm text-gray-500 dark:text-gray-400">Capacidade da instalação</p>
                <p class="mt-2 text-2xl font-semibold">{{ $simulation['capacity_kw'] !== null ? number_format($simulation['capacity_kw'], 2, ',', '.') : '—' }} @if($simulation['capacity_kw'] !== null)<small class="text-base font-normal">kW</small>@endif</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Definida em Meu Estabelecimento</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-[#343843] dark:bg-[#181b22]">
                <p class="text-sm text-gray-500 dark:text-gray-400">Demanda solicitada</p>
                <p class="mt-2 text-2xl font-semibold">{{ number_format($simulation['total_requested_kw'], 3, ',', '.') }} <small class="text-base font-normal">kW</small></p>
                <p class="mt-1 text-xs {{ $simulation['deficit_kw'] > 0 ? 'font-semibold text-[#c62828] dark:text-red-300' : 'text-gray-500 dark:text-gray-400' }}">{{ $simulation['capacity_kw'] === null ? 'Sem limite configurado' : ($simulation['deficit_kw'] > 0 ? 'Excedente de '.number_format($simulation['deficit_kw'], 3, ',', '.').' kW' : 'Dentro da capacidade') }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-[#343843] dark:bg-[#181b22]">
                <p class="text-sm text-gray-500 dark:text-gray-400">Potência distribuída</p>
                <p class="mt-2 text-2xl font-semibold">{{ number_format($simulation['allocated_total_kw'], 3, ',', '.') }} <small class="text-base font-normal">kW</small></p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Soma das sugestões por carregador</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-[#343843] dark:bg-[#181b22]">
                <p class="text-sm text-gray-500 dark:text-gray-400">Capacidade disponível</p>
                <p class="mt-2 text-2xl font-semibold">{{ $simulation['unused_capacity_kw'] !== null ? number_format($simulation['unused_capacity_kw'], 3, ',', '.') : 'Sem limite' }} @if($simulation['unused_capacity_kw'] !== null)<small class="text-base font-normal">kW</small>@endif</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Após a distribuição simulada</p>
            </div>
        </div>

        <div class="mt-5 rounded-lg border p-4 text-sm {{ $simulationBanner['classes'] }}" role="status">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                <span class="font-semibold">{{ $simulationBanner['label'] }}</span>
                <span>{{ $simulation['message'] }}</span>
            </div>
            @if($simulation['capacity_kw'] === null)
                <p class="mt-2"><a class="font-semibold underline" href="{{ route('manager.establishment.edit') }}#configurar-tarifas">Configure a potência máxima da instalação</a> para visualizar o balanceamento com limite.</p>
            @elseif($simulation['status'] === 'blocked')
                <p class="mt-2">No cenário simulado, a capacidade não acomoda todos os carregadores com a potência mínima de {{ number_format($simulation['minimum_allocation_kw'], 2, ',', '.') }} kW. Uma nova recarga seria recusada.</p>
            @endif
        </div>

        <form method="GET" action="{{ route('manager.demand-control.simulate') }}" class="mt-6">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold">Carregadores no cenário</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Marque os equipamentos em uso e ajuste a potência solicitada por cada um.</p>
                </div>
                <button type="submit" class="gw-button gw-button-primary rounded-lg bg-[#c62828] px-5 py-3 text-sm font-semibold text-white hover:bg-[#b71c1c]">Simular distribuição</button>
            </div>

            <div class="space-y-3">
                @forelse($simulationChargers as $charger)
                    <div class="grid grid-cols-1 gap-4 rounded-lg border border-gray-200 p-4 md:grid-cols-3 md:items-end dark:border-[#343843]">
                        <label class="flex min-w-0 items-start gap-3 text-sm">
                            <input type="checkbox" name="chargers[{{ $charger['id'] }}][enabled]" value="1" @checked($charger['enabled']) class="mt-1 h-4 w-4 shrink-0 rounded border-gray-300 text-[#c62828] focus:ring-[#c62828]">
                            <span class="min-w-0">
                                <span class="block break-words font-semibold">{{ $charger['name'] }}</span>
                                <span class="mt-1 block break-all font-mono text-xs text-gray-500 dark:text-gray-400">{{ $charger['code'] }}</span>
                            </span>
                        </label>
                        <label class="gw-field flex min-w-0 flex-col gap-2 text-sm font-medium">
                            <span>Potência solicitada (kW)</span>
                            <input type="number" name="chargers[{{ $charger['id'] }}][requested_kw]" value="{{ number_format($charger['requested_kw'], 2, '.', '') }}" min="0.01" max="999999.99" step="0.01" class="w-full min-w-0 rounded-lg border border-gray-300 bg-gray-50 px-3 py-3 text-gray-900 outline-none focus:border-[#c62828] dark:border-[#343843] dark:bg-[#181b22] dark:text-white">
                        </label>
                        <p class="text-sm text-gray-500 md:pb-3 dark:text-gray-400">Potência nominal: <strong class="text-gray-900 dark:text-white">{{ number_format($charger['power_kw'], 2, ',', '.') }} kW</strong></p>
                    </div>
                @empty
                    <p class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-[#343843] dark:text-gray-400">Nenhum carregador cadastrado. Cadastre os equipamentos para simular a distribuição.</p>
                @endforelse
            </div>
        </form>

        <div class="mt-6">
            <h3 class="text-base font-semibold">Distribuição sugerida por carregador</h3>
            <p class="gw-table-hint">Deslize a tabela para ver todas as colunas.</p>
            <div class="mt-3 overflow-x-auto" tabindex="0" role="region" aria-label="Tabela de distribuição sugerida">
                <table class="w-full min-w-[680px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-gray-500 dark:border-[#343843] dark:text-gray-400">
                            @foreach(['Carregador', 'Solicitada (kW)', 'Sugerida (kW)', 'Redução (kW)', 'Aproveitamento'] as $heading)
                                <th class="px-3 py-3 font-medium">{{ $heading }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-[#23262d]">
                        @forelse($simulation['allocations'] as $allocation)
                            <tr>
                                <td class="px-3 py-3">
                                    <span class="block font-medium">{{ $allocation['name'] }}</span>
                                    @if($allocation['below_minimum'])
                                        <span class="mt-1 inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700 dark:bg-red-500/15 dark:text-red-300">Abaixo do mínimo</span>
                                    @elseif($allocation['limited_by_capacity'])
                                        <span class="mt-1 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">Reduzida</span>
                                    @else
                                        <span class="mt-1 inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300">Integral</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">{{ number_format($allocation['requested_kw'], 3, ',', '.') }}</td>
                                <td class="px-3 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="w-16 shrink-0 font-semibold">{{ number_format($allocation['allocated_kw'], 3, ',', '.') }}</span>
                                        <span class="h-2 min-w-16 flex-1 overflow-hidden rounded-full bg-gray-100 dark:bg-[#23262d]">
                                            <span class="block h-full rounded-full {{ $allocation['below_minimum'] ? 'bg-red-500' : 'bg-[#c62828]' }}" style="width: {{ min(100, $allocation['allocation_percent']) }}%"></span>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-3 py-3">{{ number_format($allocation['reduction_kw'], 3, ',', '.') }}</td>
                                <td class="px-3 py-3">{{ number_format($allocation['allocation_percent'], 1, ',', '.') }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">Selecione ao menos um carregador para ver a distribuição sugerida.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-700/40 dark:bg-amber-500/10 dark:text-amber-200">Esta é uma simulação de software. Nenhum comando de potência é enviado aos carregadores nesta etapa; o controle físico será habilitado em uma integração futura com o hardware.</p>
        </div>
    </section>

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
