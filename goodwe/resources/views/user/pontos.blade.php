<x-user-layout>
    <x-slot name="pageTitle">{{ __('bulk.meus_pontos') }}</x-slot>
    <x-slot name="header"><div><p class="pt-eyebrow">{{ __('screen.programa_de_fidelidade') }}</p><h1 class="pt-heading">{{ __('screen.seus_pontos_valem_energia') }}</h1><p class="pt-muted pt-subheading">{{ __('screen.deposite_acumule_e_escolha_seu_proximo_beneficio') }}</p></div></x-slot>
    <section class="pt-points-hero">
        <div><span class="pt-points-kicker"><i data-lucide="sparkles"></i> POWERTAG REWARDS</span><p>{{ __('screen.pontos_disponiveis') }}</p><h2>{{ number_format($user->points, 2, ',', '.') }} <small>{{ __('screen.pontos') }}</small></h2><p>{{ __('pointsui.earn_rule') }}</p><a class="pt-button pt-button-outline" href="#historico-pontos">{{ __('screen.consultar_historico') }} <i data-lucide="arrow-down"></i></a></div>
        <div class="pt-loyalty-totals"><div><span>{{ __('screen.total_acumulado') }}</span><strong>{{ number_format($earned, 2, ',', '.') }} pts</strong></div><div><span>{{ __('screen.total_utilizado_em_resgates') }}</span><strong>{{ number_format($spent, 2, ',', '.') }} pts</strong></div></div>
    </section>
    <section class="pt-card pt-mt" id="recompensas">
        <div class="pt-card-heading"><div><p class="pt-eyebrow">{{ __('screen.escolha_seu_beneficio') }}</p><h2>{{ __('screen.catalogo_de_recompensas') }}</h2></div><span class="pt-status success">{{ __('screen.sem_prazo_de_validade') }}</span></div>
        <div class="pt-loyalty-grid">
            @foreach($catalog as $tier => $benefit)
                @php($enough = (float) $user->points >= (float) $benefit['points'])
                <article class="pt-reward pt-reward-{{ $tier }}"><span class="pt-reward-icon"><i data-lucide="ticket-percent"></i></span><p class="pt-eyebrow">{{ __('pointsui.coupon') }} {{ __('pointsui.tier_'.strtolower($tier)) }}</p><h3>{{ $benefit['percent'] }}% <small>OFF</small></h3><p>{{ __('screen.desconto_em_uma_recarga') }}</p><strong>{{ __('pointsui.points_count', ['count' => number_format($benefit['points'], 2, ',', '.')]) }}</strong><p class="pt-form-note">{{ $enough ? __('pointsui.can_redeem') : __('pointsui.points_missing', ['count' => number_format((float) $benefit['points'] - (float) $user->points, 2, ',', '.')]) }}</p><button type="button" class="pt-button {{ $enough ? 'pt-button-primary' : 'pt-button-muted' }}" data-reward-open="reward-{{ $tier }}" @disabled(!$enough)>{{ __('screen.resgatar_cupom') }} <i data-lucide="arrow-right"></i></button></article>
            @endforeach
        </div>
        <p class="pt-form-note pt-mt">{{ __('screen.os_pontos_sao_usados_no_resgate_cada_cupom_pertence_a_sua_conta_e') }}</p>
    </section>
    <section class="pt-card pt-mt" id="meus-cupons">
        <div class="pt-card-heading"><div><p class="pt-eyebrow">{{ __('screen.prontos_para_sua_proxima_recarga') }}</p><h2>{{ __('bulk.meus_cupons') }}</h2></div><a href="{{ route('chargers.index') }}" class="pt-text-link">{{ __('screen.escolher_carregador') }} <i data-lucide="arrow-right"></i></a></div>
        <div class="pt-loyalty-grid">
            @forelse($coupons as $coupon)
                <article class="pt-coupon"><div class="pt-card-heading"><strong>{{ $coupon->name }} · {{ $coupon->discount_percent }}% OFF</strong><span class="pt-status {{ $coupon->status === 'available' ? 'success' : 'neutral' }}">{{ match($coupon->status) {'available' => __('pointsui.available'), 'used' => __('pointsui.used'), 'cancelled' => __('pointsui.cancelled'), default => __('pointsui.registered')} }}</span></div><code>{{ $coupon->code }}</code><p class="pt-form-note">{{ __('pointsui.redeemed_at', ['date' => $coupon->redeemed_at->format('d/m/Y H:i'), 'points' => number_format($coupon->points_cost, 2, ',', '.')]) }}</p>@if($coupon->used_at)<p class="pt-form-note">{{ __('pointsui.used_at', ['date' => $coupon->used_at->format('d/m/Y H:i')]) }}</p>@endif</article>
            @empty
                <p class="pt-muted">{{ __('screen.voce_ainda_nao_resgatou_cupons_escolha_uma_recompensa_acima_quand') }}</p>
            @endforelse
        </div><div class="pt-mt">{{ $coupons->appends(request()->except('coupons_page'))->links() }}</div>
    </section>
    <section class="pt-info-panel pt-mt"><span class="pt-info-icon"><i data-lucide="info"></i></span><div><strong>{{ __('screen.libere_o_carregador_apos_concluir') }}</strong><p>{{ __('pointsui.reservations_notice') }}</p></div></section>
    <section class="pt-card pt-mt" id="historico-pontos">
        <div class="pt-card-heading"><div><p class="pt-eyebrow">{{ __('screen.atividade') }}</p><h2>{{ __('bulk.historico_de_pontos') }}</h2></div></div>
        <p class="pt-muted">{{ __('screen.somente_depositos_confirmados_geram_pontos_recargas_nao_geram_nov') }}</p>
        <div class="pt-table-wrap"><table class="pt-table"><thead><tr><th>{{ __('bulk.data') }}</th><th>{{ __('screen.operacao') }}</th><th>{{ __('screen.descricao_origem') }}</th><th>{{ __('screen.pontos_164') }}</th><th>{{ __('screen.saldo_apos') }}</th></tr></thead><tbody>
            @forelse($movements as $movement)
                <tr><td>{{ $movement->created_at?->format('d/m/Y H:i') }}</td><td>{{ match($movement->type) {'deposit' => __('pointsui.deposit'), 'redemption' => __('pointsui.redemption'), 'refund' => __('pointsui.refund'), 'penalty' => __('pointsui.penalty'), default => __('pointsui.adjustment')} }}</td><td>{{ $movement->description }}@if($movement->transaction_id)<small class="pt-origin">{{ __('pointsui.deposit_reference', ['id' => $movement->transaction_id]) }}</small>@endif @if($movement->coupon_id)<small class="pt-origin">{{ __('pointsui.coupon_reference', ['id' => $movement->coupon_id]) }}</small>@endif @if(isset($movement->metadata['reservation_id']))<small class="pt-origin">{{ __('pointsui.reservation_penalty', ['id' => $movement->metadata['reservation_id'], 'points' => number_format($movement->metadata['configured_points'], 2, ',', '.')]) }}</small>@endif
@if(isset($movement->metadata['previous_points']))<small class="pt-origin">{{ __('pointsui.recalculated_balance', ['previous' => number_format($movement->metadata['previous_points'], 2, ',', '.'), 'current' => number_format($movement->metadata['recalculated_points'], 2, ',', '.')]) }}</small>@endif</td><td>{{ (float) $movement->amount > 0 ? '+' : '' }}{{ number_format($movement->amount, 2, ',', '.') }} pts</td><td>{{ number_format($movement->balance_after, 2, ',', '.') }} pts</td></tr>
            @empty
                <tr><td colspan="5" class="pt-table-empty">{{ __('screen.nenhuma_movimentacao_de_pontos_registrada') }}</td></tr>
            @endforelse
        </tbody></table></div><div class="pt-mt">{{ $movements->appends(request()->except('points_page'))->links() }}</div>
    </section>
    @foreach($catalog as $tier => $benefit)
        <dialog id="reward-{{ $tier }}" class="pt-dialog" aria-labelledby="reward-title-{{ $tier }}"><div class="pt-dialog-body"><form method="POST" action="{{ route('points.redeem') }}">@csrf
            <input type="hidden" name="tier" value="{{ $tier }}"><input type="hidden" name="idempotency_key" value="{{ Illuminate\Support\Str::uuid() }}">
            <div class="pt-dialog-top"><div><p class="pt-eyebrow">{{ __('screen.confirmar_resgate') }}</p><h2 id="reward-title-{{ $tier }}">{{ __('pointsui.coupon') }} {{ __('pointsui.tier_'.strtolower($tier)) }} · {{ $benefit['percent'] }}% OFF</h2></div><button type="button" class="pt-dialog-close" data-dialog-close aria-label="{{ __('pointsui.close') }}"><i data-lucide="x"></i></button></div>
            <dl class="pt-payment-summary"><div><dt>{{ __('screen.saldo_atual') }}</dt><dd>{{ number_format($user->points, 2, ',', '.') }} pts</dd></div><div><dt>{{ __('screen.custo_do_resgate') }}</dt><dd>− {{ number_format($benefit['points'], 2, ',', '.') }} pts</dd></div><div class="pt-payment-total"><dt>{{ __('screen.saldo_restante') }}</dt><dd>{{ number_format(max(0, (float) $user->points - (float) $benefit['points']), 2, ',', '.') }} pts</dd></div></dl><p class="pt-form-note">{{ __('screen.apos_confirmar_o_cupom_ficara_disponivel_na_sua_conta_os_pontos_n') }}</p>
            <div class="pt-form-actions"><button type="button" data-dialog-close class="pt-button pt-button-outline">{{ __('bulk.cancelar') }}</button><button type="submit" class="pt-button pt-button-primary" @disabled((float) $user->points < (float) $benefit['points'])>{{ __('screen.confirmar_resgate_172') }}</button></div>
        </form></div></dialog>
    @endforeach
</x-user-layout>
