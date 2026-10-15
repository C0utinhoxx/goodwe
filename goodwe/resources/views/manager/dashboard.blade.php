<x-user-layout>
    <x-slot name="header">
        <div class="gw-dashboard-heading">
            <div><p class="pt-eyebrow">SUA OPERAÇÃO</p><h1>Visão geral</h1><p class="pt-muted pt-subheading">{{ $establishment->name }} · {{ $establishment->address }}</p></div>
            <div class="gw-dashboard-filters">
                <form method="GET" action="{{ route('manager.dashboard') }}" class="gw-period-filter"><label for="period" class="sr-only">{{ __('manager.period_label') }}</label><select name="period" id="period" class="gw-select">@foreach([7,30,90] as $days)<option value="{{ $days }}" @selected($period === $days)>{{ __('manager.period_'.$days) }}</option>@endforeach</select><button type="submit" class="gw-button gw-button-secondary">Aplicar</button></form>
                <a href="{{ route('manager.dashboard.export', ['period'=>$period]) }}" class="gw-button gw-button-secondary"><i data-lucide="arrow-down" aria-hidden="true"></i>{{ __('manager.export_csv') }}</a>
            </div>
        </div>
    </x-slot>
    <section class="gw-metric-grid" aria-label="Resumo da operação">
        @foreach([
            ['label'=>__('manager.total_sessions'),'value'=>$totalSessions,'icon'=>'battery-charging'],
            ['label'=>__('manager.total_revenue'),'value'=>'R$ '.number_format($totalRevenue,2,',','.'),'icon'=>'wallet'],
            ['label'=>__('manager.total_energy'),'value'=>number_format($totalEnergy,1,',','.').' kWh','icon'=>'zap'],
            ['label'=>__('manager.avg_cost'),'value'=>'R$ '.number_format($avgCostPerKwh,2,',','.'),'unit'=>'/kWh','icon'=>'chart-no-axes-column'],
        ] as $metric)
            <article class="gw-metric gw-card"><span class="gw-metric-icon"><i data-lucide="{{ $metric['icon'] }}" aria-hidden="true"></i></span><p>{{ $metric['label'] }}</p><strong>{{ $metric['value'] }}@isset($metric['unit'])<small>{{ $metric['unit'] }}</small>@endisset</strong></article>
        @endforeach
    </section>
    <details class="gw-methodology"><summary>Como os indicadores são calculados</summary><p>Receita considera pagamentos de recarga com transação aprovada. Valores pendentes não compõem a receita. Energia considera o consumo registrado, inclusive de sessões ainda não pagas. O custo médio considera somente sessões pagas.</p></details>
    <section class="gw-status-strip gw-card" aria-label="Disponibilidade dos carregadores">
        @foreach(['total'=>'Carregadores','available'=>'Disponíveis','reserved'=>'Reservados','in_use'=>'Em uso','unavailable'=>'Indisponíveis'] as $status=>$label)
            <div><span class="gw-status-indicator gw-status-{{ $status }}"></span><p>{{ $label }}</p><strong>{{ $chargerCounts[$status] }}</strong></div>
        @endforeach
    </section>
    <x-manager-pricing-summary :establishment="$establishment" :detector-configured="$detectorConfigured" />
    <section class="gw-card mt-5 rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-[#343843] dark:bg-[#1a1a1a]">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">{{ __('manager.period_summary') }}</h2>
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-md bg-gray-50 dark:bg-[#181b22] p-4">
                <p class="text-xs text-gray-500 dark:text-[#a0a0a0]">{{ __('manager.period_sessions') }}</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ $periodSessionsCount }}</p>
            </div>
            <div class="rounded-md bg-gray-50 dark:bg-[#181b22] p-4">
                <p class="text-xs text-gray-500 dark:text-[#a0a0a0]">{{ __('manager.period_revenue') }}</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">R$ {{ number_format($periodRevenue, 2, ',', '.') }}</p>
            </div>
            <div class="rounded-md bg-gray-50 dark:bg-[#181b22] p-4">
                <p class="text-xs text-gray-500 dark:text-[#a0a0a0]">{{ __('manager.period_energy') }}</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($periodEnergy, 1, ',', '.') }} kWh</p>
            </div>
            <div class="rounded-md bg-gray-50 dark:bg-[#181b22] p-4">
                <p class="text-xs text-gray-500 dark:text-[#a0a0a0]">{{ __('manager.active_users') }}</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ $periodActiveUsers }} <span class="text-sm font-normal text-gray-400">/ {{ $totalUsers }} {{ __('manager.total_users') }}</span></p>
            </div>
        </div>
    </section>

    <section class="gw-card mt-5 rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-[#343843] dark:bg-[#1a1a1a]">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">{{ __('auth.manager_chargers') }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-[#a0a0a0]">{{ __('auth.manager_chargers_subtitle') }}</p>
            </div>
            <span class="inline-flex items-center gap-1.5 rounded-full border border-[#e53935]/30 bg-[#e53935]/5 px-3 py-1.5">
                <span class="relative flex h-2.5 w-2.5">
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-[#e53935]"></span>
                </span>
                <span class="text-xs text-[#e53935] font-medium">{{ __('auth.recorded_status') }}</span>
            </span>
        </div>

        <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @forelse($liveChargers as $charger)
                <div class="rounded-lg border border-gray-200 dark:border-[#343843] p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">{{ $charger['name'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-[#a0a0a0]">{{ $charger['location'] }}</p>
                        </div>
                        <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium {{ $charger['is_available'] ? 'bg-emerald-500/10 text-emerald-500' : 'bg-gray-200 dark:bg-[#343843] text-gray-400 dark:text-[#a0a0a0]' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $charger['is_available'] ? 'bg-emerald-500' : 'bg-gray-400 dark:bg-[#a0a0a0]' }}"></span>
                            {{ __('auth.charger_status_' . $charger['status']) }}
                        </span>
                    </div>
                    <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-[#a0a0a0]">
                        <span class="rounded-md bg-gray-100 dark:bg-[#181b22] px-2 py-1">{{ $charger['power_kw'] }} kW</span>
                        <span class="rounded-md bg-gray-100 dark:bg-[#181b22] px-2 py-1">{{ $charger['connector'] }}</span>
                        <span class="rounded-md bg-gray-100 dark:bg-[#181b22] px-2 py-1 font-mono">{{ $charger['id'] }}</span>
                    </div>
                </div>
            @empty
                <p class="col-span-full py-6 text-center text-sm text-gray-500 dark:text-[#a0a0a0]">{{ __('manager.no_chargers') }}</p>
            @endforelse
        </div>
    </section>

    <section class="mt-5 grid grid-cols-1 gap-5 xl:grid-cols-2">
        <div class="gw-card rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-[#343843] dark:bg-[#1a1a1a]">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                {{ __('manager.energy_usage') }}
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-[#a0a0a0]">{{ __('manager.period_' . $period) }}</p>
            <div class="mt-4" style="height: 280px;">
                <canvas id="energyChart" role="img" aria-label="Consumo de energia no período"></canvas>
            </div>
        </div>

        <div class="gw-card rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-[#343843] dark:bg-[#1a1a1a]">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                {{ __('manager.revenue_chart') }}
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-[#a0a0a0]">{{ __('manager.period_' . $period) }}</p>
            <div class="mt-4" style="height: 280px;">
                <canvas id="revenueChart" role="img" aria-label="Receita diária no período"></canvas>
            </div>
        </div>
    </section>

    <section class="mt-5 grid grid-cols-1 gap-5 xl:grid-cols-2">
        <div class="gw-card rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-[#343843] dark:bg-[#1a1a1a]">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                {{ __('manager.charger_distribution') }}
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-[#a0a0a0]">{{ __('manager.sessions_by_charger') }}</p>
            <div class="gw-chart-frame mt-4 flex items-center justify-center" style="height: 280px;">
                @if($chargerDistribution->isEmpty())<p class="gw-chart-empty">Nenhuma sessão registrada neste período.</p>@endif
                <canvas id="chargerChart" role="img" aria-label="Distribuição de sessões por carregador"></canvas>
            </div>
        </div>

        <div class="gw-card rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-[#343843] dark:bg-[#1a1a1a]">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                {{ __('manager.status_distribution') }}
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-[#a0a0a0]">{{ __('manager.sessions_by_status') }}</p>
            <div class="gw-chart-frame mt-4 flex items-center justify-center" style="height: 280px;">
                @if($statusDistribution->isEmpty())<p class="gw-chart-empty">Nenhuma sessão registrada neste período.</p>@endif
                <canvas id="statusChart" role="img" aria-label="Distribuição de sessões por status"></canvas>
            </div>
        </div>
    </section>

    <section class="mt-5 grid grid-cols-1 gap-5 xl:grid-cols-2">
        <div class="gw-card rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-[#343843] dark:bg-[#1a1a1a]">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                {{ __('manager.top_users') }}
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-[#a0a0a0]">{{ __('manager.top_users_subtitle') }}</p>
            <p class="gw-table-hint">Deslize a tabela para ver todas as colunas.</p><div class="mt-5 overflow-x-auto" tabindex="0" role="region" aria-label="Tabela com rolagem horizontal">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-200 text-gray-500 dark:border-[#343843] dark:text-[#a0a0a0]">
                        <tr>
                            <th class="py-2 font-medium">{{ __('manager.rank') }}</th>
                            <th class="py-2 font-medium">{{ __('manager.table_user') }}</th>
                            <th class="py-2 font-medium">{{ __('manager.table_sessions') }}</th>
                            <th class="py-2 font-medium">{{ __('manager.table_energy') }}</th>
                            <th class="py-2 font-medium">{{ __('manager.table_value') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topUsers as $index => $row)
                            <tr class="border-b border-gray-200 dark:border-[#343843]">
                                <td class="py-3 text-gray-500 dark:text-[#a0a0a0]">{{ $index + 1 }}</td>
                                <td class="py-3 text-gray-900 dark:text-white">{{ $row->user->name ?? '-' }}</td>
                                <td class="py-3 text-gray-900 dark:text-white">{{ $row->sessions_count }}</td>
                                <td class="py-3 text-gray-900 dark:text-white">{{ number_format($row->total_kwh, 1, ',', '.') }} kWh</td>
                                <td class="py-3 text-gray-900 dark:text-white">R$ {{ number_format($row->total_spent, 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-gray-500 dark:text-[#a0a0a0]">{{ __('manager.no_users') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="gw-card rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-[#343843] dark:bg-[#1a1a1a]">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                {{ __('manager.establishment_info') }}
            </h2>
            <div class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <p class="text-sm text-gray-500 dark:text-[#a0a0a0]">{{ __('manager.info_name') }}</p>
                    <p class="mt-1 font-medium text-gray-900 dark:text-white">{{ $establishment->name }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-[#a0a0a0]">{{ __('manager.info_address') }}</p>
                    <p class="mt-1 font-medium text-gray-900 dark:text-white">{{ $establishment->address }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-[#a0a0a0]">{{ __('manager.info_type') }}</p>
                    <p class="mt-1 font-medium text-gray-900 dark:text-white">{{ ucfirst($establishment->type) }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-[#a0a0a0]">{{ __('manager.info_hours') }}</p>
                    <p class="mt-1 font-medium text-gray-900 dark:text-white">{{ $establishment->operating_hours }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-[#a0a0a0]">{{ __('manager.info_phone') }}</p>
                    <p class="mt-1 font-medium text-gray-900 dark:text-white">{{ $establishment->phone ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-[#a0a0a0]">Tarifa-base</p>
                    <p class="mt-1 font-medium text-gray-900 dark:text-white">R$ {{ number_format($establishment->cost_per_kwh, 4, ',', '.') }}/kWh</p>
                </div>
            </div>
        </div>
    </section>

    <section class="gw-card mt-5 rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-[#343843] dark:bg-[#1a1a1a]">
        <div class="flex items-center justify-between gap-4">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                {{ __('manager.recent_sessions') }}
            </h2>
        </div>
        <p class="pt-form-note mt-2">{{ $includeTestSessions ? 'Neste ambiente de testes, as recargas de teste também compõem os totais e gráficos do painel.' : 'Recargas de teste aparecem nesta lista, mas não compõem os indicadores nem o faturamento real.' }}</p>

        <p class="gw-table-hint">Deslize a tabela para ver todas as colunas.</p><div class="mt-5 overflow-x-auto" tabindex="0" role="region" aria-label="Tabela com rolagem horizontal">
            <table class="w-full min-w-[700px] text-left text-sm">
                <thead class="border-b border-gray-200 text-gray-500 dark:border-[#343843] dark:text-[#a0a0a0]">
                    <tr>
                        <th class="py-3 font-medium">{{ __('manager.table_date') }}</th>
                        <th class="py-3 font-medium">{{ __('manager.table_user') }}</th>
                        <th class="py-3 font-medium">{{ __('manager.table_charger') }}</th>
                        <th class="py-3 font-medium">{{ __('manager.table_energy') }}</th>
                        <th class="py-3 font-medium">{{ __('manager.table_value') }}</th>
                        <th class="py-3 font-medium">{{ __('manager.table_status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentSessions as $session)
                        <tr class="border-b border-gray-200 dark:border-[#343843]">
                            <td class="py-4 text-gray-900 dark:text-white">{{ $session->created_at->format('d/m/Y H:i') }}</td>
                            <td class="py-4 text-gray-900 dark:text-white">{{ $session->user->name ?? '-' }}</td>
                            <td class="py-4 text-gray-900 dark:text-white">{{ $session->charger_name }}@if($session->isWebSimulation())<small class="pt-origin"><span class="pt-status neutral">Recarga de teste</span></small>@endif</td>
                            <td class="py-4 text-gray-900 dark:text-white">{{ number_format($session->energy_kwh, 1, ',', '.') }} kWh</td>
                            <td class="py-4 text-gray-900 dark:text-white">R$ {{ number_format($session->amount_charged, 2, ',', '.') }}</td>
                            <td class="py-4">
                                @if($session->status === 'completed')
                                    <span class="rounded-lg bg-emerald-500/10 px-3 py-1 text-emerald-500">{{ __('manager.status_completed') }}</span>
                                @elseif($session->status === 'pending')
                                    <span class="rounded-lg bg-amber-500/10 px-3 py-1 text-amber-400">{{ __('manager.status_pending') }}</span>
                                @else
                                    <span class="rounded-lg bg-red-500/10 px-3 py-1 text-red-400">{{ __('manager.status_cancelled') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-gray-500 dark:text-[#a0a0a0]">
                                {{ __('manager.no_sessions') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @php($chartData = ['labels'=>$dailyLabels,'kwhData'=>$dailyKwh,'revenueData'=>$dailyRevenue,'chargerData'=>$chargerDistribution,'statusData'=>$statusDistribution])
    <script type="application/json" id="manager-chart-data">@json($chartData)</script>
</x-user-layout>
