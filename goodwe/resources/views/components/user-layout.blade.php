{{-- Preserve the original manager layout and menu. Only ordinary user pages use the new PowerTag portal. --}}
@if(request()->routeIs('manager.*'))
    <x-manager-legacy-layout>
        @if(isset($header))
            <x-slot name="header">{{ $header }}</x-slot>
        @endif
        {{ $slot }}
    </x-manager-legacy-layout>
@else
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($pageTitle) ? $pageTitle.' · ' : '' }}PowerTag</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
    <script>
        (() => {
            const theme = localStorage.getItem('theme');
            document.documentElement.classList.toggle('dark', theme === 'dark' || (!theme && matchMedia('(prefers-color-scheme: dark)').matches));
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="pt-body min-h-screen antialiased">
    @php
        $links = [
            ['name' => __('portal.home'), 'route' => 'user.home', 'active' => 'user.home', 'icon' => 'house'],
            ['name' => __('nav.chargers'), 'route' => 'chargers.index', 'active' => 'chargers.*', 'icon' => 'zap'],
            ['name' => __('nav.wallet'), 'route' => 'wallet.index', 'active' => 'wallet.*', 'icon' => 'wallet'],
            ['name' => __('portal.points'), 'route' => 'points.index', 'active' => 'points.*', 'icon' => 'gift'],
            ['name' => __('nav.profile'), 'route' => 'user.profile', 'active' => 'user.profile', 'icon' => 'user'],
        ];
    @endphp
    <div class="pt-shell" data-portal-shell>
        <div class="pt-mobile-overlay" data-mobile-close hidden></div>
        <aside class="pt-sidebar" id="portal-sidebar" aria-label="Navegação principal">
            <a href="{{ route('user.home') }}" class="pt-brand">
                <img src="{{ asset('imagens/goodwe-logo.png') }}" alt="GoodWe" class="pt-brand-logo">
                <span><strong>Power<span>Tag</span></strong><small>GOODWE CHALLENGE</small></span>
            </a>
            <div class="pt-nav-section-label">MENU PRINCIPAL</div>
            <nav class="pt-nav">
                @foreach ($links as $link)
                    <a href="{{ route($link['route']) }}" class="pt-nav-link {{ request()->routeIs($link['active']) ? 'is-active' : '' }}" @if(request()->routeIs($link['active'])) aria-current="page" @endif>
                        <i data-lucide="{{ $link['icon'] }}"></i><span>{{ $link['name'] }}</span>
                    </a>
                @endforeach
            </nav>
            <div class="pt-sidebar-bottom">
                <div class="pt-sidebar-help"><span class="pt-sidebar-help-icon"><i data-lucide="headset"></i></span><strong>Precisa de ajuda?</strong><p>Converse com nosso assistente no canto da tela.</p></div>
                <form method="POST" action="{{ route('logout') }}">@csrf
                    <button class="pt-nav-link pt-logout" type="submit"><i data-lucide="log-out"></i><span>Sair da conta</span></button>
                </form>
            </div>
        </aside>
        <div class="pt-main-shell">
            <header class="pt-topbar">
                <div class="pt-topbar-left">
                    <button type="button" class="pt-icon-button pt-mobile-menu" data-mobile-open aria-label="Abrir menu" aria-controls="portal-sidebar" aria-expanded="false"><i data-lucide="menu"></i></button>
                    <div class="pt-topbar-breadcrumb"><span>Portal do usuário</span><i data-lucide="chevron-right"></i><strong>{{ $pageTitle ?? 'Início' }}</strong></div>
                </div>
                <div class="pt-topbar-actions">
                    <x-lang-switcher variant="boxed" />
                    <x-theme-toggle variant="boxed" />
                    <a href="{{ route('user.profile') }}" class="pt-user-chip" title="Meu perfil"><span class="pt-avatar">{{ mb_strtoupper(mb_substr(Auth::user()->name ?? 'U', 0, 1)) }}</span><span class="pt-user-chip-text">{{ Str::limit(Auth::user()->name, 20) }}<small>Minha conta</small></span><i data-lucide="chevron-down"></i></a>
                </div>
            </header>
            <main class="pt-content">
                @if(isset($header))<div class="pt-page-head">{{ $header }}</div>@endif
                @if(session('success'))<div class="pt-alert pt-alert-success" role="status">{{ session('success') }}</div>@endif
                @if(session('error'))<div class="pt-alert pt-alert-error" role="alert">{{ session('error') }}</div>@endif
                @if($errors->any())<div class="pt-alert pt-alert-error" role="alert"><strong>Confira os campos:</strong> {{ $errors->first() }}</div>@endif
                {{ $slot }}
                <footer class="pt-footer"><span>© {{ date('Y') }} PowerTag · Challenge GoodWe</span><span>Energia inteligente, conectando pessoas.</span></footer>
            </main>
        </div>
    </div>
    <x-user-chat-widget />
    <script src="{{ asset('js/theme.js') }}"></script>
</body>
</html>

@endif
