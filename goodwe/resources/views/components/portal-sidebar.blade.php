@props(['links', 'home', 'manager' => false])

<aside class="pt-sidebar" id="portal-sidebar" aria-label="{{ $manager ? 'Navegação do gestor' : 'Navegação principal' }}">
    <div class="gw-sidebar-heading">
        <a href="{{ route($home) }}" class="pt-brand pt-brand-partners"><x-partner-brands compact /></a>
        <button type="button" class="pt-icon-button gw-menu-close" data-mobile-close-button aria-label="Fechar menu"><i data-lucide="x" aria-hidden="true"></i></button>
    </div>
    <div class="gw-workspace"><span class="gw-workspace-icon"><i data-lucide="{{ $manager ? 'store' : 'zap' }}" aria-hidden="true"></i></span><div><strong>{{ $manager ? 'Painel do gestor' : 'Portal do usuário' }}</strong><small>{{ $manager ? 'Gestão do estabelecimento' : 'Sua jornada elétrica' }}</small></div></div>
    <p class="pt-nav-section-label">{{ $manager ? 'GESTÃO' : 'MENU PRINCIPAL' }}</p>
    <nav class="pt-nav" aria-label="{{ $manager ? 'Painel Gestor' : 'Menu principal' }}">
        @foreach($links as $link)
            @php
                $active = request()->routeIs(...(array) $link['active']);
                $children = $link['children'] ?? [];
            @endphp
            <div class="gw-nav-group" data-nav-group>
                <div class="gw-nav-row">
                    <a href="{{ route($link['route']) }}" class="pt-nav-link {{ $active ? 'is-active' : '' }}" title="{{ $link['name'] }} · {{ $link['description'] ?? '' }}" data-command-label="{{ $link['name'] }}" data-command-category="{{ $manager ? 'Gestão' : 'Menu principal' }}" data-command-keywords="{{ $link['description'] ?? '' }}" @if($active) aria-current="{{ request()->routeIs($link['route']) ? 'page' : 'true' }}" @endif><i data-lucide="{{ $link['icon'] }}" aria-hidden="true"></i><span data-nav-label>{{ $link['name'] }}</span></a>
                    @if($children)<button type="button" class="gw-submenu-toggle" data-submenu-toggle aria-label="Atalhos de {{ $link['name'] }}" aria-expanded="{{ $active ? 'true' : 'false' }}" aria-controls="nav-submenu-{{ $loop->index }}"><i data-lucide="chevron-down" aria-hidden="true"></i></button>@endif
                </div>
                @if($children)
                    <div id="nav-submenu-{{ $loop->index }}" class="gw-submenu" data-nav-submenu @unless($active) hidden @endunless>
                        @foreach($children as $child)
                            <a href="{{ route($child['route']).(isset($child['hash']) ? '#'.$child['hash'] : '') }}" class="gw-subnav-link" data-command-label="{{ $child['name'] }}" data-command-category="{{ $link['name'] }}" @if(isset($child['hash'])) data-section-link @elseif(request()->routeIs($child['route'])) aria-current="page" @endif>{{ $child['name'] }}</a>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </nav>
    <div class="pt-sidebar-bottom">
        <div class="gw-sidebar-note"><i data-lucide="{{ $manager ? 'shield-check' : 'headset' }}" aria-hidden="true"></i><div><strong>{{ $manager ? 'Seu espaço de gestão' : 'Precisa de uma ajuda?' }}</strong><p>{{ $manager ? 'Dados e configurações da sua operação em um só lugar.' : 'Use o assistente no canto da tela para tirar suas dúvidas.' }}</p></div></div>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="pt-nav-link pt-logout" type="submit"><i data-lucide="log-out" aria-hidden="true"></i><span>{{ __('app.logout') }}</span></button></form>
        <div class="gw-sidebar-signature">POWERTAG <span>·</span> GOODWE CHALLENGE</div>
    </div>
</aside>
