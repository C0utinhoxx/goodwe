<x-user-layout>
    <x-slot name="pageTitle">Minha recarga</x-slot>
    {{-- Botão de reserva no canto superior direito da página. Ele abre o
         diálogo de confirmação com a taxa do dia (base: tarifa × duração
         média × potência; acréscimo seg–qui menor, sex–dom maior) sem
         tirar a pessoa do fluxo padrão de pagamento da recarga. --}}
    <x-slot name="header"><div class="flex items-start justify-between gap-4"><div><p class="pt-eyebrow">SESSÃO #{{ $session->id }}</p><h1 class="pt-heading">{{ $session->charger_name }}</h1><p class="pt-muted pt-subheading">{{ $session->establishment?->name ?? 'Estabelecimento não informado no registro anterior' }}</p></div><button type="button" class="pt-button pt-button-primary shrink-0" data-charger-open="reservar-sessao"><i data-lucide="calendar-plus"></i> Reservar ponto</button></div></x-slot>
    <x-flash-messages />
    <section class="pt-card">
        @if($session->isWebSimulation() && $session->charging_state !== 'ended')
            <x-charging-test-controls :session="$session" />
        @elseif(!$session->charging_state)
            <p class="pt-muted">Este é um registro anterior do simulador. Não representa uma confirmação física de consumo. Consulte os dados históricos.</p>
        @elseif($session->charging_state !== 'ended')
            <h2>{{ $session->charging_state === 'stop_requested' ? 'Interrupção necessária' : 'Recarga em andamento' }}</h2><p class="pt-muted">A sessão foi confirmada pelo equipamento. O pagamento fica disponível após a confirmação de encerramento e do consumo final.</p>
            @if($session->stop_requested_at)<p class="pt-form-note">O consumo atingiu ou ultrapassou o saldo disponível. A interrupção ainda não foi confirmada; os dados de consumo recebidos continuarão registrados.</p>@endif
        @else
            <h2>{{ $session->payment_status === 'paid' ? 'Pagamento confirmado' : 'Recarga encerrada · pagamento pendente' }}</h2><p class="pt-muted">{{ $session->isWebSimulation() ? 'Recarga de teste encerrada em' : 'Encerramento confirmado em' }} <time data-local-time datetime="{{ $session->ended_at?->toIso8601String() }}">{{ $session->ended_at?->format('d/m/Y H:i') }} ({{ config('app.timezone') }})</time>. {{ $session->payment_status === 'paid' ? 'A transação foi registrada na sua carteira.' : 'Confira os valores e confirme o pagamento. A cobrança não acontece automaticamente.' }}</p>
        @endif
        <dl class="pt-payment-summary"><div><dt>Energia {{ $session->isWebSimulation() ? 'simulada' : ($session->charging_state ? 'medida' : 'registrada') }}</dt><dd data-charging-energy>{{ number_format($session->measured_energy_kwh ?? $session->energy_kwh, 4, ',', '.') }} kWh</dd></div><div><dt>Tarifa da sessão</dt><dd>{{ $session->tariff_per_kwh !== null ? 'R$ '.number_format($session->tariff_per_kwh, 4, ',', '.').'/kWh' : 'Não registrada no fluxo anterior' }}</dd></div><div><dt>Valor original</dt><dd data-charging-amount>R$ {{ number_format($session->original_amount, 2, ',', '.') }}</dd></div><div><dt>Saldo na carteira</dt><dd>R$ {{ number_format($user->balance, 2, ',', '.') }}</dd></div></dl>
        @if($session->charging_state === 'ended' && $session->payment_status === 'pending')
            <form method="POST" action="{{ route('charging-sessions.pay', $session) }}" data-session-payment data-original-cents="{{ App\Services\DecimalAmount::units($session->original_amount) }}">@csrf
                <input type="hidden" name="idempotency_key" value="{{ Illuminate\Support\Str::uuid() }}">
                <label class="pt-field">Aplicar cupom <select name="coupon_id" data-payment-coupon><option value="" data-percent="0">Sem cupom</option>@foreach($coupons as $coupon)<option value="{{ $coupon->id }}" data-percent="{{ $coupon->discount_percent }}">{{ $coupon->name }} · {{ $coupon->discount_percent }}% OFF · {{ $coupon->code }}</option>@endforeach</select></label>
                <p class="pt-form-note">{{ $coupons->count() }} {{ $coupons->count() === 1 ? 'cupom disponível' : 'cupons disponíveis' }}. Os pontos já foram descontados no resgate; não serão debitados novamente.</p>
                <dl class="pt-payment-summary" aria-live="polite"><div><dt>Desconto do cupom</dt><dd data-session-discount>R$ 0,00</dd></div><div class="pt-payment-total"><dt>Total a pagar</dt><dd data-session-total>R$ {{ number_format($session->original_amount, 2, ',', '.') }}</dd></div></dl>
                @if($session->balance_exceeded)<p class="pt-form-note">O consumo registrado ultrapassou o saldo que existia ao encerrar. O valor foi preservado integralmente. Se necessário, adicione créditos para confirmar o pagamento.</p>@endif
                <div class="pt-form-actions"><a href="{{ route('wallet.deposit.form') }}" class="pt-button pt-button-outline">Adicionar saldo</a><button type="submit" class="pt-button pt-button-primary">Confirmar pagamento</button></div>
            </form>
        @elseif($session->payment_status === 'paid')
            <dl class="pt-payment-summary"><div><dt>Cupom utilizado</dt><dd>{{ $session->coupon ? $session->coupon->name.' · '.$session->coupon->discount_percent.'% OFF' : 'Sem cupom' }}</dd></div><div><dt>Desconto</dt><dd>R$ {{ number_format($session->discount_amount, 2, ',', '.') }}</dd></div><div class="pt-payment-total"><dt>Valor pago</dt><dd>R$ {{ number_format($session->amount_charged, 2, ',', '.') }}</dd></div><div><dt>Transação</dt><dd>#{{ $session->transaction_id }}</dd></div></dl>
        @endif
        <a class="pt-text-link pt-mt" href="{{ route('wallet.history') }}">Consultar histórico <i data-lucide="arrow-right"></i></a>
    </section>

    {{-- Diálogo de reserva do ponto desta sessão, com a taxa informada
         antes da confirmação. Sessões legadas sem carregador cadastrado
         recebem a alternativa de escolher um ponto na lista. --}}
    <dialog id="reservar-sessao" class="pt-dialog" aria-labelledby="reservar-sessao-title"><div class="pt-dialog-body">
        @if($reserveCharger)
            <div class="pt-dialog-top"><div><p class="pt-eyebrow">RESERVA IMEDIATA</p><h2 id="reservar-sessao-title">{{ $reserveCharger->name }}</h2><p class="pt-muted">{{ $reserveCharger->establishment->name }} · {{ $reserveCharger->location }}</p></div><button type="button" class="pt-dialog-close" data-dialog-close aria-label="Fechar"><i data-lucide="x"></i></button></div>
            <dl class="pt-payment-summary"><div><dt>Potência</dt><dd>{{ (float) $reserveCharger->power_kw }} kW</dd></div><div><dt>Tarifa vigente</dt><dd>{{ (float) $reserveCharger->currentTariff() > 0 ? 'R$ '.number_format($reserveCharger->currentTariff(), 4, ',', '.').'/kWh' : 'Não configurada' }}</dd></div>@if($reserveFee['enabled'])<div><dt>Taxa de reserva</dt><dd>R$ {{ number_format((float) $reserveFee['amount'], 2, ',', '.') }}</dd></div><div><dt>Base da taxa</dt><dd>R$ {{ number_format((float) $reserveFee['base_amount'], 2, ',', '.') }} · média de {{ $reserveFee['average_minutes'] }} min × {{ (float) $reserveCharger->power_kw }} kW × tarifa</dd></div><div><dt>Regra por dia</dt><dd>Seg–qui +{{ config('reservations.fee_percent_weekday') }}% · Sex–dom +{{ config('reservations.fee_percent_weekend') }}%</dd></div>@endif<div><dt>Tolerância para comparecer</dt><dd>{{ $reserveCharger->establishment->reservation_tolerance_minutes ? $reserveCharger->establishment->reservation_tolerance_minutes.' minutos' : 'Não configurada' }}</dd></div></dl>
            @if($reserveFee['enabled'])
                <p class="pt-form-note">Hoje aplica-se <strong>+{{ $reserveFee['percent'] }}%</strong>. A reserva garante este ponto com a tarifa vigente; a taxa de <strong>R$ {{ number_format((float) $reserveFee['amount'], 2, ',', '.') }}</strong> é cobrada do seu saldo no ato da confirmação e <strong>não é devolvida</strong> em cancelamento ou ausência. Não há agendamento futuro nem cobrança da recarga nesta etapa.</p>
            @else
                <p class="pt-form-note">A tarifa será garantida para a recarga vinculada à reserva. Você pode cancelar gratuitamente antes do prazo e do início. Não há agendamento futuro nem cobrança nesta etapa.</p>
            @endif
            <form method="POST" action="{{ route('reservations.store', $reserveCharger->code) }}">@csrf<input type="hidden" name="idempotency_key" value="{{ Illuminate\Support\Str::uuid() }}"><div class="pt-form-actions"><button type="button" data-dialog-close class="pt-button pt-button-outline">Voltar</button><button type="submit" class="pt-button pt-button-primary">Confirmar reserva{{ $reserveFee['enabled'] ? ' · R$ '.number_format((float) $reserveFee['amount'], 2, ',', '.') : '' }}</button></div></form>
        @else
            <div class="pt-dialog-top"><div><p class="pt-eyebrow">RESERVA IMEDIATA</p><h2 id="reservar-sessao-title">Reservar um ponto</h2></div><button type="button" class="pt-dialog-close" data-dialog-close aria-label="Fechar"><i data-lucide="x"></i></button></div>
            <p class="pt-form-note">Este registro anterior não aponta um carregador cadastrado, então a reserva não pode ser feita direto por aqui. Escolha um ponto na lista de carregadores.</p>
            <div class="pt-form-actions"><button type="button" data-dialog-close class="pt-button pt-button-outline">Fechar</button><a href="{{ route('chargers.index') }}" class="pt-button pt-button-primary">Ver carregadores disponíveis</a></div>
        @endif
    </div></dialog>
</x-user-layout>
