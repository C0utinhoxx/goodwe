<x-user-layout>
    <x-slot name="pageTitle">Início</x-slot>
    <x-slot name="header"><div><p class="pt-eyebrow">VISÃO GERAL</p><h1 class="pt-heading">Olá, {{ explode(' ', trim(Auth::user()->name))[0] }} <span class="pt-wave">👋</span></h1><p class="pt-muted pt-subheading">Sua energia, seus benefícios. Tudo em um só lugar.</p></div></x-slot>

    <section class="pt-hero">
        <div class="pt-hero-content"><span class="pt-hero-eyebrow"><span class="pt-dot"></span> BEM-VINDO À POWERTAG</span><h2>A próxima recarga<br><em>começa aqui.</em></h2><p>Gerencie suas recargas, acompanhe seu saldo e aproveite cada ponto da sua jornada elétrica.</p><div class="pt-hero-actions"><a href="{{ route('chargers.index') }}" class="pt-button pt-button-light"><i data-lucide="map-pin"></i> Encontrar carregador <i data-lucide="arrow-up-right"></i></a><a href="{{ route('wallet.deposit.form') }}" class="pt-hero-link">Adicionar saldo <i data-lucide="arrow-right"></i></a></div></div>
        <div class="pt-hero-illustration" aria-hidden="true"><div class="pt-orbit orbit-outer"></div><div class="pt-orbit orbit-inner"></div><div class="pt-charge-symbol"><i data-lucide="zap"></i></div><span class="pt-orbit-label label-one">100% elétrico</span><span class="pt-orbit-label label-two">POWER ON</span></div>
    </section>

    <section class="pt-stats-grid" aria-label="Resumo da conta">
        <article class="pt-stat"><div class="pt-stat-top"><span class="pt-stat-icon green"><i data-lucide="wallet"></i></span><span class="pt-mini-label">SALDO DISPONÍVEL</span></div><strong>R$ {{ number_format(Auth::user()->balance ?? 0, 2, ',', '.') }}</strong><a href="{{ route('wallet.deposit.form') }}">Adicionar saldo <i data-lucide="arrow-up-right"></i></a></article>
        <article class="pt-stat"><div class="pt-stat-top"><span class="pt-stat-icon purple"><i data-lucide="gift"></i></span><span class="pt-mini-label">MEUS PONTOS</span></div><strong>{{ number_format(Auth::user()->points ?? 0, 2, ',', '.') }} <small>pts</small></strong><a href="{{ route('points.index') }}">Ver benefícios <i data-lucide="arrow-up-right"></i></a></article>
        <article class="pt-stat"><div class="pt-stat-top"><span class="pt-stat-icon blue"><i data-lucide="zap"></i></span><span class="pt-mini-label">RECARGAS REALIZADAS</span></div><strong>{{ $totalSessions }}</strong><a href="{{ route('wallet.history') }}">Ver histórico <i data-lucide="arrow-up-right"></i></a></article>
        <article class="pt-stat"><div class="pt-stat-top"><span class="pt-stat-icon amber"><i data-lucide="plug-zap"></i></span><span class="pt-mini-label">LIVRES NA SIMULAÇÃO</span></div><strong>{{ $availableChargers }} <small>/ {{ $totalChargers }}</small></strong><a href="{{ route('chargers.index') }}">Consultar estações <i data-lucide="arrow-up-right"></i></a></article>
    </section>

    @if($pendingSession)
        <section class="pt-info-panel pt-mt"><span class="pt-info-icon"><i data-lucide="battery-charging"></i></span><div><strong>Recarga pendente: {{ $pendingSession->charger_name }}</strong><p>Sessão registrada em {{ $pendingSession->created_at?->format('d/m/Y H:i') }}. O acompanhamento em tempo real depende da integração IoT.</p><a href="{{ route('wallet.history') }}">Consultar histórico <i data-lucide="arrow-right"></i></a></div></section>
    @endif

    <div class="pt-section-grid">
        <section class="pt-card pt-recent"><div class="pt-card-heading"><div><p class="pt-eyebrow">SEU HISTÓRICO</p><h2>Últimas recargas</h2><p class="pt-form-note">Sessões do simulador contêm energia calculada, sem medição física.</p></div><a href="{{ route('wallet.history') }}" class="pt-text-link">Ver tudo <i data-lucide="arrow-up-right"></i></a></div>
            @forelse($sessions as $session)
                <div class="pt-activity-row"><span class="pt-activity-icon"><i data-lucide="battery-charging"></i></span><div class="pt-activity-copy"><strong>{{ $session->charger_name }}</strong><small>{{ $session->created_at?->format('d/m/Y · H:i') }} · {{ number_format($session->energy_kwh ?? 0, 2, ',', '.') }} kWh</small></div><div class="pt-activity-end"><strong>R$ {{ number_format($session->amount_charged ?? 0, 2, ',', '.') }}</strong><span class="pt-status {{ $session->status === 'completed' ? 'success' : 'neutral' }}">{{ match($session->status) {'completed' => 'Concluída', 'pending' => 'Pendente', 'cancelled' => 'Cancelada', default => ucfirst((string)$session->status)} }}</span></div></div>
            @empty
                <div class="pt-empty"><i data-lucide="battery-charging"></i><strong>Nenhuma recarga por aqui ainda</strong><p>Encontre um carregador e comece sua jornada com a PowerTag.</p><a href="{{ route('chargers.index') }}" class="pt-button pt-button-primary">Explorar carregadores</a></div>
            @endforelse
        </section>
        <div class="pt-right-stack">
            <section class="pt-card"><div class="pt-card-heading"><div><p class="pt-eyebrow">ATALHOS</p><h2>Acesso rápido</h2></div></div><div class="pt-quick-grid"><a href="{{ route('chargers.index') }}"><span class="pt-quick-icon green"><i data-lucide="map-pin"></i></span><strong>Carregadores</strong><small>Encontre uma estação</small><i data-lucide="arrow-up-right" class="pt-quick-arrow"></i></a><a href="{{ route('wallet.deposit.form') }}"><span class="pt-quick-icon blue"><i data-lucide="plus-circle"></i></span><strong>Adicionar saldo</strong><small>Recarregue sua carteira</small><i data-lucide="arrow-up-right" class="pt-quick-arrow"></i></a><a href="{{ route('points.index') }}"><span class="pt-quick-icon purple"><i data-lucide="gift"></i></span><strong>Benefícios</strong><small>Acompanhe seus pontos</small><i data-lucide="arrow-up-right" class="pt-quick-arrow"></i></a><a href="{{ route('user.profile') }}"><span class="pt-quick-icon amber"><i data-lucide="car-front"></i></span><strong>Meus veículos</strong><small>Gerencie seu cadastro</small><i data-lucide="arrow-up-right" class="pt-quick-arrow"></i></a></div></section>
            <section class="pt-info-panel"><span class="pt-info-icon"><i data-lucide="lightbulb"></i></span><div><strong>Sabia que seus depósitos geram pontos?</strong><p>Na PowerTag, sua carteira e seus benefícios caminham juntos. Consulte as regras do programa.</p><a href="{{ route('points.index') }}">Entenda como funciona <i data-lucide="arrow-right"></i></a></div></section>
        </div>
    </div>
</x-user-layout>
