<x-user-layout>
    <x-slot name="pageTitle">{{ __('screen.minha_recarga') }}</x-slot>
    <x-slot name="header"><div><p class="pt-eyebrow">SESSÃO #{{ $session->id }}</p><h1 class="pt-heading">{{ $session->charger_name }}</h1><p class="pt-muted pt-subheading">{{ $session->establishment?->name ?? 'Estabelecimento não informado no registro anterior' }}</p></div></x-slot>
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
        <dl class="pt-payment-summary"><div><dt>Energia {{ $session->isWebSimulation() ? 'simulada' : ($session->charging_state ? 'medida' : 'registrada') }}</dt><dd data-charging-energy>{{ number_format($session->measured_energy_kwh ?? $session->energy_kwh, 4, ',', '.') }} kWh</dd></div><div><dt>{{ __('screen.tarifa_da_sessao') }}</dt><dd>{{ $session->tariff_per_kwh !== null ? 'R$ '.number_format($session->tariff_per_kwh, 4, ',', '.').'/kWh' : 'Não registrada no fluxo anterior' }}</dd></div><div><dt>{{ __('screen.valor_original') }}</dt><dd data-charging-amount>R$ {{ number_format($session->original_amount, 2, ',', '.') }}</dd></div><div><dt>{{ __('screen.saldo_na_carteira') }}</dt><dd>R$ {{ number_format($user->balance, 2, ',', '.') }}</dd></div></dl>
        @if($session->charging_state === 'ended' && $session->payment_status === 'pending')
            <form method="POST" action="{{ route('charging-sessions.pay', $session) }}" data-session-payment data-original-cents="{{ App\Services\DecimalAmount::units($session->original_amount) }}">@csrf
                <input type="hidden" name="idempotency_key" value="{{ Illuminate\Support\Str::uuid() }}">
                <label class="pt-field">{{ __('screen.aplicar_cupom') }} <select name="coupon_id" data-payment-coupon><option value="" data-percent="0">{{ __('screen.sem_cupom') }}</option>@foreach($coupons as $coupon)<option value="{{ $coupon->id }}" data-percent="{{ $coupon->discount_percent }}">{{ $coupon->name }} · {{ $coupon->discount_percent }}% OFF · {{ $coupon->code }}</option>@endforeach</select></label>
                <p class="pt-form-note">{{ $coupons->count() }} {{ $coupons->count() === 1 ? 'cupom disponível' : 'cupons disponíveis' }}. Os pontos já foram descontados no resgate; não serão debitados novamente.</p>
                <dl class="pt-payment-summary" aria-live="polite"><div><dt>{{ __('screen.desconto_do_cupom') }}</dt><dd data-session-discount>R$ 0,00</dd></div><div class="pt-payment-total"><dt>{{ __('screen.total_a_pagar') }}</dt><dd data-session-total>R$ {{ number_format($session->original_amount, 2, ',', '.') }}</dd></div></dl>
                @if($session->balance_exceeded)<p class="pt-form-note">O consumo registrado ultrapassou o saldo que existia ao encerrar. O valor foi preservado integralmente. Se necessário, adicione créditos para confirmar o pagamento.</p>@endif
                <div class="pt-form-actions"><a href="{{ route('wallet.deposit.form') }}" class="pt-button pt-button-outline">{{ __('bulk.adicionar_saldo') }}</a><button type="submit" class="pt-button pt-button-primary">{{ __('bulk.confirmar_pagamento') }}</button></div>
            </form>
        @elseif($session->payment_status === 'paid')
            <dl class="pt-payment-summary"><div><dt>{{ __('screen.cupom_utilizado') }}</dt><dd>{{ $session->coupon ? $session->coupon->name.' · '.$session->coupon->discount_percent.'% OFF' : 'Sem cupom' }}</dd></div><div><dt>{{ __('screen.desconto') }}</dt><dd>R$ {{ number_format($session->discount_amount, 2, ',', '.') }}</dd></div><div class="pt-payment-total"><dt>{{ __('screen.valor_pago') }}</dt><dd>R$ {{ number_format($session->amount_charged, 2, ',', '.') }}</dd></div><div><dt>{{ __('screen.transacao') }}</dt><dd>#{{ $session->transaction_id }}</dd></div></dl>
        @endif
        <a class="pt-text-link pt-mt" href="{{ route('wallet.history') }}">{{ __('screen.consultar_historico') }} <i data-lucide="arrow-right"></i></a>
    </section>
</x-user-layout>
