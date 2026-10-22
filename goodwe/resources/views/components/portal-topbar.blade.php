
@props(['title', 'manager' => false])

@php
    $usuario = Auth::user();
    $nomeUsuario = $usuario?->name ?? 'Visitante';
    $isSupport = (bool) ($usuario?->isSuporte() ?? false);

    if ($isSupport) {
        $nomePainel = __('screen.central_de_suporte');
        $tipoConta = __('screen.equipe_de_suporte');
    } elseif ($manager) {
        $nomePainel = __('sidebar.manager_panel');
        $tipoConta = __('topbar.establishment_manager');
    } else {
        $nomePainel = __('sidebar.user_portal');
        $tipoConta = __('topbar.my_account');
    }
@endphp

<header class="pt-topbar">
    <div class="pt-topbar-left">
        <button
            type="button"
            class="pt-icon-button pt-mobile-menu"
            data-mobile-open
            aria-label="{{ __('topbar.open_menu') }}"
            aria-controls="portal-sidebar"
            aria-expanded="false"
        >
            <i data-lucide="menu" aria-hidden="true"></i>
        </button>

        <button
            type="button"
            class="pt-icon-button gw-desktop-menu"
            data-sidebar-toggle
            aria-label="{{ __('topbar.collapse_sidebar') }}"
            aria-expanded="true"
            aria-controls="portal-sidebar"
            title="{{ __('topbar.collapse_sidebar') }}"
        >
            <i data-lucide="arrow-left" aria-hidden="true"></i>
        </button>

        <nav
            class="pt-topbar-breadcrumb"
            aria-label="{{ __('topbar.breadcrumb') }}"
        >
            <span>{{ $nomePainel }}</span>

            <i data-lucide="chevron-right" aria-hidden="true"></i>

            <strong aria-current="page">{{ $title }}</strong>
        </nav>
    </div>

    <div class="pt-topbar-actions">
        <button
            type="button"
            class="gw-command-trigger"
            data-commands-open
            aria-label="{{ __('topbar.search_actions') }}"
            aria-keyshortcuts="Control+k Meta+k"
        >
            <i data-lucide="search" aria-hidden="true"></i>
            <span>{{ __('topbar.search_portal') }}</span>
            <kbd>⌘/Ctrl K</kbd>
        </button>

        <x-lang-switcher variant="boxed" />

        <x-theme-toggle variant="boxed" />

        @if($usuario)
            <div class="gw-account">
                <button
                    type="button"
                    class="pt-user-chip"
                    data-account-toggle
                    aria-label="{{ __('topbar.open_account_menu') }}"
                    aria-expanded="false"
                    aria-controls="account-menu"
                    title="{{ $nomeUsuario }}"
                >
                    <span class="pt-avatar">
                        {{ mb_strtoupper(mb_substr($nomeUsuario, 0, 1)) }}
                    </span>

                    <span class="pt-user-chip-text">
                        {{ Str::limit($nomeUsuario, 22) }}

                        <small>{{ $tipoConta }}</small>
                    </span>

                    <i data-lucide="chevron-down" aria-hidden="true"></i>
                </button>

                <div id="account-menu" class="gw-account-menu" hidden>
                    <div class="gw-account-heading">
                        <strong>{{ $nomeUsuario }}</strong>

                        <small>
                            @if($isSupport)
                                Conta de Suporte
                            @elseif($manager)
                                {{ __('topbar.manager_account') }}
                            @else
                                {{ __('topbar.user_account') }}
                            @endif
                        </small>
                    </div>

                    <nav aria-label="{{ __('topbar.account_shortcuts') }}">
                        @if($isSupport)
                            <a href="{{ route('support.dashboard') }}">
                                <i data-lucide="life-buoy" aria-hidden="true"></i>
                                {{ __('support.sidebar_subtitle') }}
                            </a>
                        @elseif($manager)
                            <a href="{{ route('manager.profile') }}">
                                <i data-lucide="user" aria-hidden="true"></i>
                                {{ __('topbar.my_profile') }}
                            </a>

                            <a href="{{ route('manager.establishment.setup') }}">
                                <i data-lucide="store" aria-hidden="true"></i>
                                {{ __('topbar.my_establishment') }}
                            </a>

                            @unless(request()->routeIs('manager.establishment.setup'))
                                <a href="{{ route('manager.establishment.edit') }}#configurar-tarifas">
                                    <i data-lucide="chart-no-axes-column" aria-hidden="true"></i>
                                    {{ __('topbar.configure_rates') }}
                                </a>
                            @endunless
                        @else
                            <a href="{{ route('user.profile') }}">
                                <i data-lucide="user" aria-hidden="true"></i>
                                {{ __('topbar.my_profile') }}
                            </a>

                            <a href="{{ route('user.profile') }}#meus-veiculos">
                                <i data-lucide="car-front" aria-hidden="true"></i>
                                {{ __('topbar.my_vehicles') }}
                            </a>

                            <a href="{{ route('user.profile') }}#preferencias">
                                <i data-lucide="moon" aria-hidden="true"></i>
                                {{ __('topbar.preferences') }}
                            </a>
                        @endif
                    </nav>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf

                        <button type="submit">
                            <i data-lucide="log-out" aria-hidden="true"></i>
                            {{ __('app.logout') }}
                        </button>
                    </form>
                </div>
            </div>
        @endif
    </div>
</header>
