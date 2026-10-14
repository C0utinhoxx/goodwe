<x-user-layout>
    <x-slot name="pageTitle">Meus pontos</x-slot>
    <x-slot name="header"><div><p class="pt-eyebrow">PROGRAMA DE FIDELIDADE</p><h1 class="pt-heading">Seus pontos valem energia <span class="pt-heading-icon">🎁</span></h1><p class="pt-muted pt-subheading">Deposite, acumule e escolha seu próximo benefício.</p></div></x-slot>
    <section class="pt-points-hero">
        <div><span class="pt-points-kicker"><i data-lucide="sparkles"></i> POWERTAG REWARDS</span><p>Pontos disponíveis</p><h2>{{ number_format($user->points, 2, ',', '.') }} <small>pontos</small></h2><p>R$ 100,00 em depósitos confirmados = 10,00 pontos.</p><a class="pt-button pt-button-outline" href="#historico-pontos">Consultar histórico <i data-lucide="arrow-down"></i></a></div>
        <div class="pt-loyalty-totals"><div><span>Total acumulado</span><strong>{{ number_format($earned, 2, ',', '.') }} pts</strong></div><div><span>Total utilizado em resgates</span><strong>{{ number_format($spent, 2, ',', '.') }} pts</strong></div></div>
    </section>
    <section class="pt-card pt-mt">
        <div class="pt-card-heading"><div><p class="pt-eyebrow">ESCOLHA SEU BENEFÍCIO</p><h2>Catálogo de recompensas</h2></div><span class="pt-status success">Sem prazo de validade</span></div>
        <div class="pt-loyalty-grid">
            @foreach($catalog as $tier => $benefit)
                @php($enough = (float) $user->points >= (float) $benefit['points'])
                <article class="pt-reward pt-reward-{{ $tier }}"><span class="pt-reward-icon"><i data-lucide="ticket-percent"></i></span><p class="pt-eyebrow">CUPOM {{ $benefit['name'] }}</p><h3>{{ $benefit['percent'] }}% <small>OFF</small></h3><p>Desconto em uma recarga</p><strong>{{ number_format($benefit['points'], 2, ',', '.') }} pontos</strong><p class="pt-form-note">{{ $enough ? 'Você já pode resgatar este benefício.' : 'Faltam '.number_format((float) $benefit['points'] - (float) $user->points, 2, ',', '.').' pontos.' }}</p><button type="button" class="pt-button {{ $enough ? 'pt-button-primary' : 'pt-button-muted' }}" data-reward-open="reward-{{ $tier }}" @disabled(!$enough)>Resgatar cupom <i data-lucide="arrow-right"></i></button></article>
            @endforeach
        </div>
        <p class="pt-form-note pt-mt">Os pontos são usados no resgate. Cada cupom pertence à sua conta e vale para uma única recarga; descontos não são cumulativos.</p>
    </section>
    <section class="pt-card pt-mt">
        <div class="pt-card-heading"><div><p class="pt-eyebrow">PRONTOS PARA SUA PRÓXIMA RECARGA</p><h2>Meus cupons</h2></div><a href="{{ route('chargers.index') }}" class="pt-text-link">Escolher carregador <i data-lucide="arrow-right"></i></a></div>
        <div class="pt-loyalty-grid">
            @forelse($coupons as $coupon)
                <article class="pt-coupon"><div class="pt-card-heading"><strong>{{ $coupon->name }} · {{ $coupon->discount_percent }}% OFF</strong><span class="pt-status {{ $coupon->status === 'available' ? 'success' : 'neutral' }}">{{ match($coupon->status) {'available' => 'Disponível', 'used' => 'Utilizado', 'cancelled' => 'Cancelado', default => 'Registrado'} }}</span></div><code>{{ $coupon->code }}</code><p class="pt-form-note">Resgatado em {{ $coupon->redeemed_at->format('d/m/Y H:i') }} · {{ number_format($coupon->points_cost, 2, ',', '.') }} pontos</p>@if($coupon->used_at)<p class="pt-form-note">Utilizado em {{ $coupon->used_at->format('d/m/Y H:i') }}</p>@endif</article>
            @empty
                <p class="pt-muted">Você ainda não resgatou cupons. Escolha uma recompensa acima quando tiver pontos suficientes.</p>
            @endforelse
        </div><div class="pt-mt">{{ $coupons->appends(request()->except('coupons_page'))->links() }}</div>
    </section>
    <section class="pt-info-panel pt-mt"><span class="pt-info-icon"><i data-lucide="info"></i></span><div><strong>Libere o carregador após concluir</strong><p>A permanência após a recarga poderá gerar penalidades em pontos. O período de tolerância e a quantidade de pontos ainda serão definidos; nenhuma penalidade automática é aplicada.</p></div></section>
    <section class="pt-card pt-mt" id="historico-pontos">
        <div class="pt-card-heading"><div><p class="pt-eyebrow">ATIVIDADE</p><h2>Histórico de pontos</h2></div></div>
        <p class="pt-muted">Somente depósitos confirmados geram pontos. Recargas não geram nova pontuação. Estornos e conversões ficam registrados aqui.</p>
        <div class="pt-table-wrap"><table class="pt-table"><thead><tr><th>Data</th><th>Operação</th><th>Descrição / origem</th><th>Pontos</th><th>Saldo após</th></tr></thead><tbody>
            @forelse($movements as $movement)
                <tr><td>{{ $movement->created_at?->format('d/m/Y H:i') }}</td><td>{{ match($movement->type) {'deposit' => 'Depósito', 'redemption' => 'Resgate', 'refund' => 'Estorno', 'penalty' => 'Penalidade', default => 'Ajuste'} }}</td><td>{{ $movement->description }}@if($movement->transaction_id)<small class="pt-origin">Depósito #{{ $movement->transaction_id }}</small>@endif @if($movement->coupon_id)<small class="pt-origin">Cupom #{{ $movement->coupon_id }}</small>@endif @if(isset($movement->metadata['previous_points']))<small class="pt-origin">Saldo antigo: {{ number_format($movement->metadata['previous_points'], 2, ',', '.') }} · recalculado: {{ number_format($movement->metadata['recalculated_points'], 2, ',', '.') }} pontos. Carteira financeira preservada.</small>@endif</td><td>{{ (float) $movement->amount > 0 ? '+' : '' }}{{ number_format($movement->amount, 2, ',', '.') }} pts</td><td>{{ number_format($movement->balance_after, 2, ',', '.') }} pts</td></tr>
            @empty
                <tr><td colspan="5" class="pt-table-empty">Nenhuma movimentação de pontos registrada.</td></tr>
            @endforelse
        </tbody></table></div><div class="pt-mt">{{ $movements->appends(request()->except('points_page'))->links() }}</div>
    </section>
    @foreach($catalog as $tier => $benefit)
        <dialog id="reward-{{ $tier }}" class="pt-dialog" aria-labelledby="reward-title-{{ $tier }}"><div class="pt-dialog-body"><form method="POST" action="{{ route('points.redeem') }}">@csrf
            <input type="hidden" name="tier" value="{{ $tier }}"><input type="hidden" name="idempotency_key" value="{{ Illuminate\Support\Str::uuid() }}">
            <div class="pt-dialog-top"><div><p class="pt-eyebrow">CONFIRMAR RESGATE</p><h2 id="reward-title-{{ $tier }}">Cupom {{ $benefit['name'] }} · {{ $benefit['percent'] }}% OFF</h2></div><button type="button" class="pt-dialog-close" data-dialog-close aria-label="Fechar"><i data-lucide="x"></i></button></div>
            <dl class="pt-payment-summary"><div><dt>Saldo atual</dt><dd>{{ number_format($user->points, 2, ',', '.') }} pts</dd></div><div><dt>Custo do resgate</dt><dd>− {{ number_format($benefit['points'], 2, ',', '.') }} pts</dd></div><div class="pt-payment-total"><dt>Saldo restante</dt><dd>{{ number_format(max(0, (float) $user->points - (float) $benefit['points']), 2, ',', '.') }} pts</dd></div></dl><p class="pt-form-note">Após confirmar, o cupom ficará disponível na sua conta. Os pontos não serão descontados novamente no pagamento.</p>
            <div class="pt-form-actions"><button type="button" data-dialog-close class="pt-button pt-button-outline">Cancelar</button><button type="submit" class="pt-button pt-button-primary" @disabled((float) $user->points < (float) $benefit['points'])>Confirmar resgate</button></div>
        </form></div></dialog>
    @endforeach
</x-user-layout>
