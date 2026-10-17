<x-user-layout>
    <x-slot name="pageTitle">Carregadores</x-slot>

    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="pt-eyebrow">REDE POWERTAG</p>
                <h1 class="pt-heading">Carregadores</h1>
                <p class="pt-muted pt-subheading">Consulte a tarifa vigente e reserve um equipamento disponível agora.</p>
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

    @foreach($activeSessions as $session)
        <section class="pt-info-panel pt-mt"><span class="pt-info-icon"><i data-lucide="battery-charging"></i></span><div><strong>{{ $session->charger_name }} · {{ $session->charging_state === 'ended' ? 'Pagamento pendente' : ($session->isWebSimulation() ? 'Recarga em andamento · início de teste' : ($session->charging_state === 'stop_requested' ? 'Interrupção necessária; confirmação física pendente' : 'Recarga confirmada pelo equipamento')) }}</strong><p>Tarifa da sessão: R$ {{ number_format($session->tariff_per_kwh, 4, ',', '.') }}/kWh. Consumo registrado: {{ number_format($session->measured_energy_kwh, 4, ',', '.') }} kWh.</p><a href="{{ route('charging-sessions.show', $session) }}">{{ $session->charging_state === 'ended' ? 'Revisar e pagar recarga' : 'Consultar sessão' }} <i data-lucide="arrow-right"></i></a></div></section>
    @endforeach

    <x-flash-messages />

<section class="pt-card pt-filter-panel pt-mt">
        <div class="pt-filter-top"><div class="pt-filter-search"><i data-lucide="search"></i><label for="charger-search" class="sr-only">Buscar carregador</label><input id="charger-search" type="search" placeholder="Nome, estabelecimento ou localização..." data-charger-search></div><label class="pt-select-label">Status <select data-charger-status><option value="all">Todos</option><option value="available">Disponíveis</option><option value="reserved">Reservados</option><option value="in_use">Em uso</option><option value="unavailable">Indisponíveis</option></select></label><label class="pt-select-label">Potência <select data-charger-power><option value="all">Todas</option>@foreach($chargers->pluck('power_kw')->unique()->sort() as $power)<option value="{{ (float) $power }}">{{ (float) $power }} kW</option>@endforeach</select></label></div>
        <div class="pt-filter-foot"><span aria-live="polite"><strong data-charger-count>{{ $chargers->count() }}</strong> carregadores cadastrados</span><span>Disponibilidade e regras conferidas novamente ao confirmar.</span><button type="button" class="pt-text-link" data-charger-reset>Limpar filtros</button></div>
    </section>
    <section class="pt-chargers-grid" data-chargers-list>
        @foreach($chargers as $charger)
            @php($state = $charger->status())
            <article class="pt-card pt-charger" data-charger-card data-search="{{ mb_strtolower($charger->name.' '.$charger->establishment->name.' '.$charger->location.' '.$charger->connector) }}" data-status="{{ $state }}" data-power="{{ (float) $charger->power_kw }}">
                <div class="pt-charger-top"><span class="pt-station-icon"><i data-lucide="plug-zap"></i></span><span class="pt-status {{ match($state) {'available' => 'success', 'reserved' => 'reserved', 'in_use' => 'in-use', default => 'neutral'} }}">{{ match($state) {'available' => 'Disponível', 'reserved' => 'Reservado', 'in_use' => 'Em uso', default => 'Indisponível'} }}</span></div>
                <h2>{{ $charger->name }}</h2><p class="pt-station-location">{{ $charger->establishment->name }}</p><p class="pt-station-location"><i data-lucide="map-pin"></i> {{ $charger->location }}</p>
                <div class="pt-station-specs"><span><i data-lucide="zap"></i> {{ (float) $charger->power_kw }} kW</span><span><i data-lucide="cable"></i> {{ $charger->connector }}</span></div>
                <div class="pt-station-foot"><div><small>Tarifa vigente</small><strong>@if((float) $charger->currentTariff() > 0)R$ {{ number_format($charger->currentTariff(), 4, ',', '.') }} <span>/ kWh</span>@else Não configurada @endif</strong></div><button type="button" class="pt-button {{ $charger->canReserve() ? 'pt-button-primary' : 'pt-button-outline' }}" data-charger-open="charger-{{ $loop->index }}">{{ $charger->canReserve() ? 'Reservar agora' : 'Ver detalhes' }}</button></div>
                @if($charger->isAvailable() && !$charger->canReserve())<p class="pt-form-note">Reserva aguardando configuração de tarifa ou tolerância pelo estabelecimento.</p>@endif
            </article>

        @endforeach
    </section>
    @if($chargers->isEmpty())<section class="pt-card pt-mt"><div class="pt-empty"><i data-lucide="plug-zap"></i><strong>Nenhum carregador cadastrado para reservas</strong><p>Os estabelecimentos ainda precisam disponibilizar seus equipamentos e configurar as regras de reserva.</p></div></section>@endif
    <div class="pt-empty" data-charger-empty hidden>Nenhum carregador corresponde aos filtros selecionados.</div>

    <section class="pt-card pt-mt" id="minhas-reservas">
        <div class="pt-card-heading"><div><p class="pt-eyebrow">SUA JORNADA</p><h2>Minhas reservas</h2></div></div>
        <div class="pt-reservations-grid">
            @forelse($reservations as $reservation)
                <article class="pt-reservation" data-reservation-card data-reservation-status="{{ $reservation->status }}" data-expires-at="{{ $reservation->expires_at->toIso8601String() }}" data-server-time="{{ now()->toIso8601String() }}" data-status-url="{{ route('reservations.status', $reservation) }}">
                    <div class="pt-card-heading"><strong>{{ $reservation->charger->name }}</strong><span class="pt-status neutral" data-reservation-state>{{ match($reservation->status) {'active' => 'Reserva confirmada', 'started' => 'Recarga iniciada', 'cancelled' => 'Cancelada', 'expired' => 'Expirada por ausência', default => 'Registrada'} }}</span></div>
                    <p class="pt-muted">{{ $reservation->establishment->name }} · {{ $reservation->charger->location }}</p>
                    <p class="pt-form-note">{{ in_array($reservation->status, ['active', 'started']) ? 'Tarifa garantida' : 'Tarifa registrada; garantia encerrada' }}: <strong>R$ {{ number_format($reservation->tariff_per_kwh, 4, ',', '.') }}/kWh</strong></p>
                    @if($reservation->status === 'active')
                        <p class="pt-reservation-countdown">Tempo restante: <strong data-reservation-countdown>Conferindo prazo…</strong></p>
                        <p class="pt-form-note">Compareça até <time data-local-time datetime="{{ $reservation->expires_at->toIso8601String() }}">{{ $reservation->expires_at->format('d/m/Y H:i:s') }} ({{ config('app.timezone') }})</time>. No local, realize a autorização pelo fluxo presencial do aplicativo e do carregador.</p>@if(App\Services\WebChargingService::available())<form method="POST" action="{{ route('reservations.start-test', $reservation) }}" data-reservation-start class="pt-mt">@csrf<button class="pt-button pt-button-primary" type="submit">Iniciar recarga</button><p class="pt-form-note">Início de teste pelo portal, sem acionar o equipamento físico. Ao encerrar, você poderá pagar com seu saldo e aplicar um cupom.</p></form>@endif
                        <p class="pt-form-note">{{ $reservation->no_show_penalty_points === null ? 'Penalidade por ausência ainda não configurada.' : 'Ausência poderá descontar até '.number_format($reservation->no_show_penalty_points, 2, ',', '.').' pontos, limitada ao seu saldo.' }}</p>
                        <form method="POST" action="{{ route('reservations.cancel', $reservation) }}" data-reservation-cancel>@csrf<button class="pt-button pt-button-outline" type="submit">Cancelar gratuitamente</button></form>
                    @else
                        <p class="pt-form-note">Reservada em {{ $reservation->reserved_at->format('d/m/Y H:i') }}.</p>
                        @if($reservation->status === 'expired')<p class="pt-form-note">Ausência registrada. Pontos descontados: {{ number_format($reservation->penalty_applied_points, 2, ',', '.') }}.</p>@endif
                    @endif
                </article>
            @empty
                <p class="pt-muted">Você ainda não possui reservas. A reserva é imediata e não cobra a recarga antecipadamente.</p>
            @endforelse
        </div><div class="pt-mt">{{ $reservations->links() }}</div>
    </section>
    @foreach($chargers as $charger)
        <dialog id="charger-{{ $loop->index }}" class="pt-dialog" aria-labelledby="charger-title-{{ $loop->index }}"><div class="pt-dialog-body">
            <div class="pt-dialog-top"><div><p class="pt-eyebrow">RESERVA IMEDIATA</p><h2 id="charger-title-{{ $loop->index }}">{{ $charger->name }}</h2><p class="pt-muted">{{ $charger->establishment->name }} · {{ $charger->location }}</p></div><button type="button" class="pt-dialog-close" data-dialog-close aria-label="Fechar"><i data-lucide="x"></i></button></div>
            <dl class="pt-payment-summary"><div><dt>Potência</dt><dd>{{ (float) $charger->power_kw }} kW</dd></div><div><dt>Tarifa vigente</dt><dd>{{ (float) $charger->currentTariff() > 0 ? 'R$ '.number_format($charger->currentTariff(), 4, ',', '.').'/kWh' : 'Não configurada' }}</dd></div><div><dt>Tolerância para comparecer</dt><dd>{{ $charger->establishment->reservation_tolerance_minutes ? $charger->establishment->reservation_tolerance_minutes.' minutos' : 'Não configurada' }}</dd></div><div><dt>Penalidade por ausência</dt><dd>{{ $charger->establishment->no_show_penalty_points !== null ? 'Até '.number_format($charger->establishment->no_show_penalty_points, 2, ',', '.').' pontos' : 'Não configurada' }}</dd></div></dl>
            @if($charger->canReserve())
                <p class="pt-form-note">A tarifa será garantida para a recarga vinculada à reserva. Você pode cancelar gratuitamente antes do prazo e do início. Não há agendamento futuro, duração fixa ou cobrança nesta etapa.</p>
                <form method="POST" action="{{ route('reservations.store', $charger->code) }}">@csrf<input type="hidden" name="idempotency_key" value="{{ Illuminate\Support\Str::uuid() }}"><div class="pt-form-actions"><button type="button" data-dialog-close class="pt-button pt-button-outline">Voltar</button><button type="submit" class="pt-button pt-button-primary">Confirmar reserva</button></div></form>
            @else
                <p class="pt-form-note">{{ $charger->isAvailable() ? 'O estabelecimento ainda precisa definir tarifa e tolerância para habilitar reservas.' : 'Este carregador não está disponível para uma nova reserva.' }}</p><div class="pt-form-actions"><button type="button" data-dialog-close class="pt-button pt-button-outline">Fechar detalhes</button></div>
            @endif
        </div></dialog>
    @endforeach
</x-user-layout>
