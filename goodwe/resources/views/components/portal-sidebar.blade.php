
@props(['links', 'home', 'manager' => false])

@php
    $isSupport = (bool) (auth()->user()?->isSuporte() ?? false);

    if ($isSupport) {
        $workspaceTitle = __('screen.central_de_suporte');
        $workspaceDescription = __('screen.gestao_de_atendimentos');
        $workspaceIcon = 'headset';
        $navigationLabel = __('support.service');
        $bottomTitle = __('screen.equipe_de_suporte');
        $bottomDescription = __('screen.acompanhe_e_responda_os_chamados_recebidos');
        $bottomIcon = 'shield-check';
    } elseif ($manager) {
        $workspaceTitle = __('sidebar.manager_panel');
        $workspaceDescription = __('sidebar.establishment_management');
        $workspaceIcon = 'store';
        $navigationLabel = __('sidebar.management');
        $bottomTitle = __('sidebar.manager_space');
        $bottomDescription = __('sidebar.manager_space_description');
        $bottomIcon = 'shield-check';
    } else {
        $workspaceTitle = __('sidebar.user_portal');
        $workspaceDescription = __('sidebar.electric_journey');
        $workspaceIcon = 'zap';
        $navigationLabel = __('sidebar.main_menu');
        $bottomTitle = __('sidebar.need_help');
        $bottomDescription = __('sidebar.help_description');
        $bottomIcon = 'headset';
    }
@endphp

<aside
    class="pt-sidebar"
    id="portal-sidebar"
    aria-label="{{ $manager ? __('sidebar.manager_navigation') : $navigationLabel }}"
>
    <div class="gw-sidebar-heading">
        <a href="{{ route($home) }}" class="pt-brand pt-brand-partners">
            <x-partner-brands compact />
        </a>

        <button
            type="button"
            class="pt-icon-button gw-menu-close"
            data-mobile-close-button
            aria-label="{{ __('sidebar.close_menu') }}"
        >
            <i data-lucide="x" aria-hidden="true"></i>
        </button>
    </div>

    <div class="gw-workspace">
        <span class="gw-workspace-icon">
            <i data-lucide="{{ $workspaceIcon }}" aria-hidden="true"></i>
        </span>

        <div>
            <strong>{{ $workspaceTitle }}</strong>
            <small>{{ $workspaceDescription }}</small>
        </div>
    </div>

    <p class="pt-nav-section-label">
        {{ $navigationLabel }}
    </p>

    <nav
        class="pt-nav"
        aria-label="{{ $navigationLabel }}"
    >
        @foreach($links as $link)
            @php
                $active = request()->routeIs(...(array) $link['active']);
                $children = $link['children'] ?? [];
            @endphp

            <div class="gw-nav-group" data-nav-group>
                <div class="gw-nav-row">
                    <a
                        href="{{ route($link['route']) }}"
                        class="pt-nav-link {{ $active ? 'is-active' : '' }}"
                        title="{{ $link['name'] }} · {{ $link['description'] ?? '' }}"
                        data-command-label="{{ $link['name'] }}"
                        data-command-category="{{ $navigationLabel }}"
                        data-command-keywords="{{ $link['description'] ?? '' }}"
                        @if($active)
                            aria-current="{{ request()->routeIs($link['route']) ? 'page' : 'true' }}"
                        @endif
                    >
                        <i data-lucide="{{ $link['icon'] }}" aria-hidden="true"></i>
                        <span data-nav-label>{{ $link['name'] }}</span>
                    </a>

                    @if($children)
                        <button
                            type="button"
                            class="gw-submenu-toggle"
                            data-submenu-toggle
                            aria-label="{{ __('sidebar.shortcuts_for', ['name' => $link['name']]) }}"
                            aria-expanded="{{ $active ? 'true' : 'false' }}"
                            aria-controls="nav-submenu-{{ $loop->index }}"
                        >
                            <i data-lucide="chevron-down" aria-hidden="true"></i>
                        </button>
                    @endif
                </div>

                @if($children)
                    <div
                        id="nav-submenu-{{ $loop->index }}"
                        class="gw-submenu"
                        data-nav-submenu
                        @unless($active) hidden @endunless
                    >
                        @foreach($children as $child)
                            <a
                                href="{{ route($child['route']).(isset($child['hash']) ? '#'.$child['hash'] : '') }}"
                                class="gw-subnav-link"
                                data-command-label="{{ $child['name'] }}"
                                data-command-category="{{ $link['name'] }}"
                                @if(isset($child['hash']))
                                    data-section-link
                                @elseif(request()->routeIs($child['route']))
                                    aria-current="page"
                                @endif
                            >
                                {{ $child['name'] }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </nav>

    <div class="pt-sidebar-bottom">
        <div class="gw-sidebar-note">
            <i data-lucide="{{ $bottomIcon }}" aria-hidden="true"></i>

            <div>
                <strong>{{ $bottomTitle }}</strong>
                <p>{{ $bottomDescription }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button class="pt-nav-link pt-logout" type="submit">
                <i data-lucide="log-out" aria-hidden="true"></i>
                <span>{{ __('app.logout') }}</span>
            </button>
        </form>

        <div class="gw-sidebar-signature">
            POWERTAG <span>·</span> GOODWE CHALLENGE
        </div>
    </div>
</aside>
