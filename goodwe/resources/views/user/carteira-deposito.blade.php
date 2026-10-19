<x-user-layout>
    <x-slot name="pageTitle">Adicionar saldo</x-slot>
    <x-slot name="header"><p class="pt-eyebrow">CARTEIRA DIGITAL</p><h1 class="pt-heading">Adicionar saldo</h1><p class="pt-muted pt-subheading">Adicione créditos para suas próximas recargas.</p></x-slot>
    <div class="pt-form-layout">
        <section class="pt-card pt-form-card">
            <div class="pt-card-heading"><div><p class="pt-eyebrow">NOVO DEPÓSITO</p><h2>Quanto deseja adicionar?</h2></div></div>
            @if(app()->environment(['local', 'testing']))
                <form id="wallet-test-deposit" method="POST" action="{{ route('wallet.deposit.store') }}">@csrf<input type="hidden" name="idempotency_key" value="{{ Illuminate\Support\Str::uuid() }}"><input type="hidden" name="amount" value="{{ old('amount', 50) }}" data-amount-mirror></form>
            @endif
            <form id="stripe-checkout" method="POST" action="{{ route('stripe.create') }}">@csrf</form>
            <label class="pt-field">Valor do depósito (R$)<input id="deposit-amount" type="number" name="amount" form="stripe-checkout" min="1" max="10000" step="0.01" value="{{ old('amount', 50) }}" required aria-describedby="deposit-help"></label>
            <div class="pt-amount-presets">@foreach([20,50,100,200] as $amount)<button type="button" data-set-amount="{{ $amount }}">R$ {{ $amount }}</button>@endforeach</div>
            <p class="pt-form-note" id="deposit-help">O pagamento será realizado pelo checkout da Stripe. O saldo será creditado somente após a confirmação do pagamento.</p>
            <div class="pt-form-actions"><a href="{{ route('wallet.index') }}" class="pt-button pt-button-outline">Voltar</a><button type="submit" form="stripe-checkout" class="pt-button pt-button-primary">Continuar com Stripe <i data-lucide="arrow-right"></i></button></div>
            @if(app()->environment(['local', 'testing']))
                <div class="pt-mt"><p class="pt-form-note">Para testar o fluxo, adicione o valor acima à sua carteira sem cobrança. O crédito também gera os pontos correspondentes.</p><button type="submit" form="wallet-test-deposit" class="pt-button pt-button-outline pt-mt" data-copy-amount>Adicionar crédito de teste</button></div>
            @endif
        </section>
        <aside class="pt-right-stack"><div class="pt-wallet-side"><span>SEU SALDO ATUAL</span><strong>R$ {{ number_format($user->balance ?? 0, 2, ',', '.') }}</strong><p>O saldo funciona como crédito pré-pago.</p></div><section class="pt-info-panel"><span class="pt-info-icon"><i data-lucide="gift"></i></span><div><strong>Depósitos e pontos</strong><p>Cada depósito confirmado gera 5% do valor em pontos. R$ 85,50 gera 4,28 pontos.</p><a href="{{ route('points.index') }}">Consultar programa</a></div></section></aside>
    </div>
</x-user-layout>
