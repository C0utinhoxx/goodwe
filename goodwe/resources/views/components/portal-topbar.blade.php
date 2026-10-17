@props(['title', 'manager' => false])

<header class="pt-topbar">
    <div class="pt-topbar-left">
        <button type="button" class="pt-icon-button pt-mobile-menu" data-mobile-open aria-label="Abrir menu" aria-controls="portal-sidebar" aria-expanded="false"><i data-lucide="menu" aria-hidden="true"></i></button>
        <button type="button" class="pt-icon-button gw-desktop-menu" data-sidebar-toggle aria-label="Recolher menu lateral" aria-expanded="true" aria-controls="portal-sidebar" title="Recolher menu lateral"><i data-lucide="arrow-left" aria-hidden="true"></i></button>
        <nav class="pt-topbar-breadcrumb" aria-label="Localização"><span>{{ $manager ? 'Painel do gestor' : 'Portal do usuário' }}</span><i data-lucide="chevron-right" aria-hidden="true"></i><strong aria-current="page">{{ $title }}</strong></nav>
    </div>
    <div class="pt-topbar-actions">
        <button type="button" class="gw-command-trigger" data-commands-open aria-label="Buscar páginas e ações" aria-keyshortcuts="Control+k Meta+k"><i data-lucide="search" aria-hidden="true"></i><span>Buscar no portal</span><kbd>⌘/Ctrl K</kbd></button>
        <x-lang-switcher variant="boxed" />
        <x-theme-toggle variant="boxed" />
        <div class="gw-account">
            <button type="button" class="pt-user-chip" data-account-toggle aria-label="Abrir menu da conta" aria-expanded="false" aria-controls="account-menu" title="{{ Auth::user()->name }}"><span class="pt-avatar">{{ mb_strtoupper(mb_substr(Auth::user()->name ?? 'U',0,1)) }}</span><span class="pt-user-chip-text">{{ Str::limit(Auth::user()->name,22) }}<small>{{ $manager ? 'Gestor do estabelecimento' : 'Minha conta' }}</small></span><i data-lucide="chevron-down" aria-hidden="true"></i></button>
            <div id="account-menu" class="gw-account-menu" hidden>
                <div class="gw-account-heading"><strong>{{ Auth::user()->name }}</strong><small>{{ $manager ? 'Conta de gestor' : 'Conta de usuário' }}</small></div>
                <nav aria-label="Atalhos da conta">
                    @if($manager)
                        <a href="{{ route('manager.establishment.setup') }}"><i data-lucide="store" aria-hidden="true"></i>Meu estabelecimento</a>
                        @unless(request()->routeIs('manager.establishment.setup'))<a href="{{ route('manager.establishment.edit') }}#configurar-tarifas"><i data-lucide="chart-no-axes-column" aria-hidden="true"></i>Configurar tarifas</a>@endunless
                    @else
                        <a href="{{ route('user.profile') }}"><i data-lucide="user" aria-hidden="true"></i>Meu perfil</a>
                        <a href="{{ route('user.profile') }}#meus-veiculos"><i data-lucide="car-front" aria-hidden="true"></i>Meus veículos</a>
                        <a href="{{ route('user.profile') }}#preferencias"><i data-lucide="moon" aria-hidden="true"></i>Preferências</a>
                    @endif
                </nav>
                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit"><i data-lucide="log-out" aria-hidden="true"></i>{{ __('app.logout') }}</button></form>
            </div>
        </div>
    </div>
</header>
