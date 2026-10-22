<x-user-layout>
    <x-slot name="pageTitle">{{ __('bulk.adicionar_saldo') }}</x-slot>
    <x-slot name="header"><p class="pt-eyebrow">{{ __('screen.carteira_digital') }}</p><h1 class="pt-heading">{{ __('bulk.adicionar_saldo') }}</h1><p class="pt-muted pt-subheading">{{ __('screen.adicione_creditos_para_suas_proximas_recargas') }}</p></x-slot>
    <div class="pt-form-layout">
        <section class="pt-card pt-form-card">
            <div class="pt-card-heading"><div><p class="pt-eyebrow">{{ __('screen.novo_deposito') }}</p><h2>{{ __('screen.quanto_deseja_adicionar') }}</h2></div></div>
            @if(app()->environment(['local', 'testing']))
                <form id="wallet-test-deposit" method="POST" action="{{ route('wallet.deposit.store') }}">@csrf<input type="hidden" name="idempotency_key" value="{{ Illuminate\Support\Str::uuid() }}"><input type="hidden" name="amount" value="{{ old('amount', 50) }}" data-amount-mirror></form>
            @endif
            @if(config('services.stripe.secret'))
                <form id="stripe-checkout" method="POST" action="{{ route('stripe.create') }}">@csrf</form>
            @endif
            <label class="pt-field">Valor do depósito (R$)<input id="deposit-amount" type="number" name="amount" @if(config('services.stripe.secret')) form="stripe-checkout" @elseif(app()->environment(['local', 'testing'])) form="wallet-test-deposit" @endif min="1" max="10000" step="0.01" value="{{ old('amount', 50) }}" required aria-describedby="deposit-help"></label>
            <div class="pt-amount-presets">@foreach([20,50,100,200] as $amount)<button type="button" data-set-amount="{{ $amount }}">R$ {{ $amount }}</button>@endforeach</div>
            @if(config('services.stripe.secret'))
                <p class="pt-form-note" id="deposit-help">{{ __('screen.o_pagamento_sera_realizado_pelo_checkout_da_stripe_o_saldo_sera_c') }}</p>
                <div class="pt-form-actions"><a href="{{ route('wallet.index') }}" class="pt-button pt-button-outline">{{ __('bulk.voltar') }}</a><button type="submit" form="stripe-checkout" class="pt-button pt-button-primary">{{ __('screen.continuar_com_stripe') }} <i data-lucide="arrow-right"></i></button></div>
            @else
                <p class="pt-form-note" id="stripe-status">{{ __('screen.stripe_em_integracao_os_depositos_com_cartao_serao_liberados_em_b') }}</p>
                <p class="pt-form-note" id="deposit-help">{{ __('screen.assim_que_a_stripe_for_ativada_o_saldo_sera_creditado_somente_apo') }}</p>
                <div class="pt-form-actions"><a href="{{ route('wallet.index') }}" class="pt-button pt-button-outline">{{ __('bulk.voltar') }}</a><button type="button" disabled aria-describedby="stripe-status" class="pt-button pt-button-primary">{{ __('screen.continuar_com_stripe') }} <i data-lucide="arrow-right"></i></button></div>
            @endif
            @if(app()->environment(['local', 'testing']))
                <div class="pt-mt"><p class="pt-form-note">{{ __('screen.para_testar_o_fluxo_adicione_o_valor_acima_a_sua_carteira_sem_cob') }}</p><button type="submit" form="wallet-test-deposit" class="pt-button pt-button-outline pt-mt" data-copy-amount>{{ __('screen.adicionar_credito_de_teste') }}</button></div>
            @endif
        </section>
        <aside class="pt-right-stack"><div class="pt-wallet-side"><span>{{ __('screen.seu_saldo_atual') }}</span><strong>R$ {{ number_format($user->balance ?? 0, 2, ',', '.') }}</strong><p>{{ __('screen.o_saldo_funciona_como_credito_pre_pago') }}</p></div><section class="pt-info-panel"><span class="pt-info-icon"><i data-lucide="gift"></i></span><div><strong>{{ __('screen.depositos_e_pontos') }}</strong><p>Cada depósito confirmado gera 5% do valor em pontos. R$ 85,50 gera 4,28 pontos.</p><a href="{{ route('points.index') }}">{{ __('screen.consultar_programa') }}</a></div></section></aside>
    </div>
</x-user-layout>
