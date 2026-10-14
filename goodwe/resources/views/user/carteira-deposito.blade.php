<x-user-layout>
    <x-slot name="pageTitle">Adicionar saldo</x-slot>
    <x-slot name="header"><p class="pt-eyebrow">CARTEIRA DIGITAL</p><h1 class="pt-heading">Adicionar saldo</h1><p class="pt-muted pt-subheading">Adicione créditos para suas próximas recargas.</p></x-slot>
    @php($paymentAvailable = filled(config('mercadopago.access_token')))
    <div class="pt-form-layout">
        <section class="pt-card pt-form-card">
            <div class="pt-card-heading"><div><p class="pt-eyebrow">NOVO DEPÓSITO</p><h2>Quanto deseja adicionar?</h2></div></div>
            <form method="POST" action="{{ route('checkout') }}">
                @csrf
                <label class="pt-field">Valor do depósito (R$)<input id="deposit-amount" type="number" name="amount" min="1" max="10000" step="0.01" value="{{ old('amount', 50) }}" required aria-describedby="deposit-help"></label>
                <div class="pt-amount-presets">@foreach([20,50,100,200] as $amount)<button type="button" data-set-amount="{{ $amount }}">R$ {{ $amount }}</button>@endforeach</div>
                <p class="pt-form-note" id="deposit-help">O checkout utiliza a integração existente com Mercado Pago. O saldo é creditado somente após a confirmação do pagamento pelo serviço.</p>
                @unless($paymentAvailable)<div class="pt-info-panel pt-mt"><div><strong>Pagamento externo indisponível</strong><p>A conexão com Mercado Pago ainda precisa ser configurada neste ambiente.</p></div></div>@endunless
                <div class="pt-form-actions"><a href="{{ route('wallet.index') }}" class="pt-button pt-button-outline">Voltar</a><button type="submit" class="pt-button pt-button-primary" @disabled(!$paymentAvailable)>Continuar no Mercado Pago <i data-lucide="arrow-right"></i></button></div>
                @if(app()->environment(['local', 'testing']))
                    <details class="pt-mt"><summary class="pt-text-link">Depósito de demonstração</summary><p class="pt-form-note">Este fluxo de teste credita a carteira sem cobrar dinheiro. Disponível no ambiente de desenvolvimento.</p><button type="submit" formaction="{{ route('wallet.deposit.store') }}" class="pt-button pt-button-outline pt-mt">Adicionar crédito de teste</button></details>
                @endif
            </form>
        </section>
        <aside class="pt-right-stack"><div class="pt-wallet-side"><span>SEU SALDO ATUAL</span><strong>R$ {{ number_format($user->balance ?? 0, 2, ',', '.') }}</strong><p>O saldo funciona como crédito pré-pago.</p></div><section class="pt-info-panel"><span class="pt-info-icon"><i data-lucide="gift"></i></span><div><strong>Depósitos e pontos</strong><p>Cada depósito confirmado gera 10% do valor em pontos. R$ 85,50 gera 8,55 pontos.</p><a href="{{ route('points.index') }}">Consultar programa</a></div></section></aside>
    </div>
</x-user-layout>
