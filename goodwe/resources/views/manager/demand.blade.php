<x-manager-legacy-layout>
    <x-slot name="header">
        <div>
            <p class="mb-2 text-sm font-semibold text-[#c62828] dark:text-red-300">
                {{ __('bulk.acompanhamento') }}
            </p>

            <h1 class="text-3xl font-semibold">
                {{ __('bulk.demanda_e_tarifas') }}
            </h1>

            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Alertas e decisões de {{ $establishment->name }}
            </p>
        </div>
    </x-slot>

    <x-manager-pricing-summary
        :establishment="$establishment"
        :detector-configured="$detectorConfigured"
    />


<section class="gw-card mb-6 rounded-xl border border-gray-200 bg-white p-5 dark:border-[#343843] dark:bg-[#1a1a1a]">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold">
                {{ __('screen.notificacoes_de_demanda') }}
            </h2>

            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                {{ __('screen.receba_alertas_de_pico_no_notebook_ou_celular_mesmo_quando_o_good') }}
            </p>

            <p
                id="goodwe-push-status"
                class="mt-2 text-sm text-gray-500 dark:text-gray-400"
                role="status"
                aria-live="polite"
            >
                {{ __('screen.ative_as_notificacoes_neste_dispositivo_para_receber_alertas') }}
            </p>
        </div>

        <button
            id="goodwe-push-button"
            type="button"
            class="rounded-lg bg-[#c62828] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#a91f1f] disabled:cursor-not-allowed disabled:opacity-50"
        >
            {{ __('screen.ativar_notificacoes') }}
        </button>
    </div>
</section>

    <section
        id="alertas-demanda"
        class="gw-card mb-6 rounded-xl border border-gray-200 bg-white p-5 dark:border-[#343843] dark:bg-[#1a1a1a]"
    >
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs font-semibold text-[#c62828] dark:text-red-300">
                    {{ __('screen.monitoramento') }}
                </p>

                <h2 class="mt-2 text-lg font-semibold">
                    {{ __('screen.alertas_de_pico_de_demanda') }}
                </h2>

                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('screen.acompanhe_situacoes_de_alta_utilizacao_dos_carregadores_e_da_capa') }}
                </p>
            </div>

            <span class="rounded-full bg-gray-100 px-3 py-1 text-sm font-semibold text-gray-700 dark:bg-[#252a34] dark:text-gray-200">
                {{ $activeAlerts->count() }}
                {{ $activeAlerts->count() === 1 ? 'alerta ativo' : 'alertas ativos' }}
            </span>
        </div>

        @forelse($activeAlerts as $alert)
            @php
                $critical = $alert->level === 'critical';

                $alertType = match ($alert->type) {
                    'charging_peak' => 'Pico de recargas',
                    'energy_peak' => 'Pico de energia',
                    default => 'Alerta de demanda',
                };

                $details = $alert->details ?? [];
            @endphp

            <div
                class="mb-4 rounded-xl border p-4
                {{ $critical
                    ? 'border-red-300 bg-red-50 text-red-950 dark:border-red-600/40 dark:bg-red-500/10 dark:text-red-100'
                    : 'border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-600/40 dark:bg-amber-500/10 dark:text-amber-100' }}"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex items-start gap-3">
                        <i
                            data-lucide="{{ $critical ? 'alert-triangle' : 'activity' }}"
                            class="mt-1 h-5 w-5 shrink-0"
                            aria-hidden="true"
                        ></i>

                        <div>
                            <p class="text-xs font-semibold uppercase">
                                {{ $alertType }}
                            </p>

                            <h3 class="mt-1 font-semibold">
                                {{ $alert->title }}
                            </h3>

                            <p class="mt-2 text-sm">
                                {{ $alert->message }}
                            </p>
                        </div>
                    </div>

                    <span class="rounded-full border border-current px-3 py-1 text-xs font-semibold">
                        {{ $critical ? 'Crítico' : 'Atenção' }}
                    </span>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <p class="text-xs opacity-75">
                            {{ __('screen.utilizacao_registrada') }}
                        </p>

                        <p class="mt-1 font-semibold">
                            {{ $alert->measured_value === null
                                ? 'Não informada'
                                : number_format((float) $alert->measured_value, 2, ',', '.') . $alert->unit }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs opacity-75">
                            {{ __('screen.inicio_do_alerta') }}
                        </p>

                        <p class="mt-1 font-semibold">
                            {{ $alert->started_at?->format('d/m/Y H:i:s') ?? '—' }}
                        </p>
                    </div>
                </div>

                @if($alert->type === 'charging_peak')
                    <p class="mt-3 text-sm">
                        {{ __('screen.carregadores_em_uso') }}
                        <strong>
                            {{ $details['chargers_in_use'] ?? '—' }}
                            /
                            {{ $details['total_chargers'] ?? '—' }}
                        </strong>
                    </p>
                @elseif($alert->type === 'energy_peak')
                    <p class="mt-3 text-sm">
                        {{ __('screen.potencia_medida') }}
                        <strong>
                            {{ isset($details['measured_power_kw'])
                                ? number_format((float) $details['measured_power_kw'], 3, ',', '.') . ' kW'
                                : '—' }}
                        </strong>

                        <span class="mx-1">·</span>

                        {{ __('screen.limite_configurado') }}
                        <strong>
                            {{ isset($details['maximum_power_kw'])
                                ? number_format((float) $details['maximum_power_kw'], 3, ',', '.') . ' kW'
                                : '—' }}
                        </strong>
                    </p>
                @endif
            </div>
        @empty
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-5 dark:border-[#343843] dark:bg-[#181b22]">
                <div class="flex items-start gap-3">
                    <i
                        data-lucide="info"
                        class="mt-0.5 h-5 w-5 shrink-0 text-gray-500 dark:text-gray-400"
                        aria-hidden="true"
                    ></i>

                    <div>
                        <p class="font-semibold text-gray-900 dark:text-white">
                            {{ __('screen.nenhum_alerta_ativo_registrado') }}
                        </p>

                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                            {{ __('screen.quando_o_monitoramento_identificar_e_confirmar_um_pico_o_alerta_a') }}
                        </p>

                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            {{ __('screen.a_ausencia_de_alertas_nao_confirma_que_a_demanda_esteja_normal_o_') }}
                        </p>
                    </div>
                </div>
            </div>
        @endforelse
    </section>

    <section
        id="historico-alertas"
        class="gw-card mb-6 min-w-0 rounded-xl border border-gray-200 bg-white p-5 dark:border-[#343843] dark:bg-[#1a1a1a]"
    >
        <div class="mb-5">
            <h2 class="text-lg font-semibold">
                {{ __('screen.historico_de_alertas_de_demanda') }}
            </h2>

            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                {{ __('screen.ultimos_20_alertas_encerrados_no_estabelecimento') }}
            </p>
        </div>

        <p class="gw-table-hint">
            {{ __('bulk.deslize_a_tabela_para_ver_todas_as_colunas') }}
        </p>

        <div
            class="overflow-x-auto"
            tabindex="0"
            role="region"
            aria-label="Histórico de alertas com rolagem horizontal"
        >
            <table class="w-full min-w-[700px] text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-gray-500 dark:border-[#343843] dark:text-gray-400">
                        <th class="px-3 py-3 font-medium">{{ __('manager.info_type') }}</th>
                        <th class="px-3 py-3 font-medium">{{ __('screen.nivel') }}</th>
                        <th class="px-3 py-3 font-medium">{{ __('nav.home') }}</th>
                        <th class="px-3 py-3 font-medium">{{ __('screen.encerramento') }}</th>
                        <th class="px-3 py-3 font-medium">{{ __('screen.ultimo_valor') }}</th>
                        <th class="px-3 py-3 font-medium">{{ __('screen.situacao') }}</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($alertHistory as $alert)
                        <tr class="border-b border-gray-100 dark:border-[#343843]">
                            <td class="px-3 py-4">
                                {{ match ($alert->type) {
                                    'charging_peak' => 'Pico de recargas',
                                    'energy_peak' => 'Pico de energia',
                                    default => $alert->type,
                                } }}
                            </td>

                            <td class="px-3 py-4">
                                {{ $alert->level === 'critical' ? 'Crítico' : 'Atenção' }}
                            </td>

                            <td class="px-3 py-4">
                                {{ $alert->started_at?->format('d/m/Y H:i:s') ?? '—' }}
                            </td>

                            <td class="px-3 py-4">
                                {{ $alert->ended_at?->format('d/m/Y H:i:s') ?? '—' }}
                            </td>

                            <td class="px-3 py-4">
                                {{ $alert->measured_value === null
                                    ? '—'
                                    : number_format((float) $alert->measured_value, 2, ',', '.') . $alert->unit }}
                            </td>

                            <td class="px-3 py-4">
                                <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-800 dark:bg-green-500/10 dark:text-green-300">
                                    {{ __('screen.encerrado') }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="6"
                                class="px-3 py-12 text-center text-gray-500 dark:text-gray-400"
                            >
                                {{ __('screen.nenhum_alerta_encerrado_registrado') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section
        id="historico-tarifas"
        class="gw-card min-w-0 rounded-xl border border-gray-200 bg-white p-5 dark:border-[#343843] dark:bg-[#1a1a1a]"
    >
        <h2 class="mb-5 text-lg font-semibold">
            {{ __('bulk.historico_de_eventos') }}
        </h2>

        <p class="gw-table-hint">
            {{ __('bulk.deslize_a_tabela_para_ver_todas_as_colunas') }}
        </p>

        <div
            class="overflow-x-auto"
            tabindex="0"
            role="region"
            aria-label="Tabela com rolagem horizontal"
        >
            <table class="w-full min-w-[760px] text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-gray-500 dark:border-[#343843] dark:text-gray-400">
                        @foreach([
                            'Data',
                            'Evento',
                            'Tarifa anterior',
                            'Tarifa resultante',
                            'Percentual',
                            'Modo',
                            'Responsável'
                        ] as $heading)
                            <th class="px-3 py-3 font-medium">
                                {{ $heading }}
                            </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    @forelse($events as $event)
                        <tr class="border-b border-gray-100 dark:border-[#343843]">
                            <td class="px-3 py-4">
                                <time
                                    data-local-time
                                    datetime="{{ $event->occurred_at->toIso8601String() }}"
                                >
                                    {{ $event->occurred_at->format('d/m/Y H:i') }}
                                </time>
                            </td>

                            <td class="px-3 py-4">
                                {{ [
                                    'configuration_created' => 'Configuração inicial',
                                    'configuration_updated' => 'Configuração atualizada',
                                    'peak_identified' => 'Pico identificado',
                                    'approved' => 'Reajuste aprovado',
                                    'rejected' => 'Reajuste recusado',
                                    'automatic_adjustment' => 'Tarifa alterada automaticamente',
                                    'peak_ended' => 'Pico encerrado',
                                    'base_restored' => 'Retorno à tarifa-base',
                                ][$event->type] ?? $event->type }}
                            </td>

                            <td class="px-3 py-4">
                                {{ $event->previous_tariff === null
                                    ? '—'
                                    : 'R$ ' . number_format((float) $event->previous_tariff, 4, ',', '.') }}
                            </td>

                            <td class="px-3 py-4">
                                R$ {{ number_format((float) $event->new_tariff, 4, ',', '.') }}
                            </td>

                            <td class="px-3 py-4">
                                {{ $event->percentage === null
                                    ? '—'
                                    : number_format((float) $event->percentage, 2, ',', '.') . '%' }}
                            </td>

                            <td class="px-3 py-4">
                                {{ [
                                    'manual' => 'Manual',
                                    'automatic' => 'Automático',
                                ][$event->pricing_mode] ?? '—' }}
                            </td>

                            <td class="px-3 py-4">
                                {{ $event->actor?->name ?? 'Sistema' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="7"
                                class="px-3 py-12 text-center text-gray-500 dark:text-gray-400"
                            >
                                {{ __('bulk.nenhum_evento_registrado_para_este_estabelecimento') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-5">
            {{ $events->links() }}
        </div>
    </section>
    <script src="{{ asset('js/push-notifications.js') }}" defer></script>
</x-manager-legacy-layout>
