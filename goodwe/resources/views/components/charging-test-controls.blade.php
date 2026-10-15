@props(['session'])
<section data-web-charging data-advance-url="{{ route('charging-sessions.advance-test', $session) }}" data-charging-speed="{{ $session->simulation['speed'] }}">
    <div class="pt-card-heading"><div><p class="pt-eyebrow">ACOMPANHAMENTO</p><h2>Recarga em andamento</h2></div><span class="pt-status in-use">Início de teste</span></div>
    <p class="pt-muted">Acompanhe o consumo e encerre para pagar pela sua carteira. Este início de teste não aciona o equipamento físico.</p>
    <dl class="pt-payment-summary"><div><dt>Potência</dt><dd>{{ (float) $session->simulation['power_kw'] }} kW</dd></div><div><dt>Tempo de recarga</dt><dd data-charging-elapsed>{{ gmdate('H:i:s', $session->simulation['elapsed_seconds']) }}</dd></div></dl>
    <form method="POST" action="{{ route('charging-sessions.advance-test', $session) }}" data-charging-speed-form class="pt-mt">@csrf
        <label class="pt-field" for="charging-speed">Velocidade do teste<select id="charging-speed" name="speed" data-charging-speed-select>@foreach(App\Services\WebChargingService::SPEEDS as $speed)<option value="{{ $speed }}" @selected($session->simulation['speed'] === $speed)>{{ $speed }}× · {{ $speed === 1 ? 'tempo real' : '1 segundo real = '.$speed.' segundos de recarga' }}</option>@endforeach</select></label>
        <noscript><p>Ative o JavaScript para acompanhar a evolução automática do consumo.</p></noscript>
    </form>
    <p class="pt-form-note" data-charging-feedback role="status">Consumo atualizado a cada 2 segundos. O saldo só é debitado após encerrar e confirmar o pagamento.</p>
    <form method="POST" action="{{ route('charging-sessions.end-test', $session) }}" data-charging-end>@csrf<div class="pt-form-actions"><button type="submit" class="pt-button pt-button-primary">Encerrar recarga</button></div></form>
</section>
