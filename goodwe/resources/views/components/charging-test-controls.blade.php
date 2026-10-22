@props(['session'])
<section data-web-charging data-advance-url="{{ route('charging-sessions.advance-test', $session) }}" data-charging-speed="{{ $session->simulation['speed'] }}">
    <div class="pt-card-heading"><div><p class="pt-eyebrow">{{ __('bulk.acompanhamento') }}</p><h2>{{ __('screen.recarga_em_andamento') }}</h2></div><span class="pt-status in-use">{{ __('screen.inicio_de_teste') }}</span></div>
    <p class="pt-muted">{{ __('screen.acompanhe_o_consumo_e_encerre_para_pagar_pela_sua_carteira_este_i') }}</p>
    <dl class="pt-payment-summary"><div><dt>{{ __('bulk.potencia') }}</dt><dd>{{ (float) $session->simulation['power_kw'] }} kW</dd></div><div><dt>{{ __('screen.tempo_de_recarga') }}</dt><dd data-charging-elapsed>{{ gmdate('H:i:s', $session->simulation['elapsed_seconds']) }}</dd></div></dl>
    <form method="POST" action="{{ route('charging-sessions.advance-test', $session) }}" data-charging-speed-form class="pt-mt">@csrf
        <label class="pt-field" for="charging-speed">{{ __('screen.velocidade_do_teste') }}<select id="charging-speed" name="speed" data-charging-speed-select>@foreach(App\Services\WebChargingService::SPEEDS as $speed)<option value="{{ $speed }}" @selected($session->simulation['speed'] === $speed)>{{ $speed }}× · {{ $speed === 1 ? 'tempo real' : '1 segundo real = '.$speed.' segundos de recarga' }}</option>@endforeach</select></label>
        <noscript><p>{{ __('screen.ative_o_javascript_para_acompanhar_a_evolucao_automatica_do_consu') }}</p></noscript>
    </form>
    <p class="pt-form-note" data-charging-feedback role="status">{{ __('screen.consumo_atualizado_a_cada_2_segundos_o_saldo_so_e_debitado_apos_e') }}</p>
    <form method="POST" action="{{ route('charging-sessions.end-test', $session) }}" data-charging-end>@csrf<div class="pt-form-actions"><button type="submit" class="pt-button pt-button-primary">{{ __('bulk.encerrar_recarga') }}</button></div></form>
</section>
