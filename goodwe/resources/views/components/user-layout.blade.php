
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($pageTitle) ? $pageTitle.' · ' : '' }}PowerTag</title>
    <link rel="icon" href="{{ asset('imagens/goodwe-logo.png') }}" type="image/png">

    <!-- PWA -->
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#0f1117">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="GoodWe">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icons/icon-192.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">

    <script>
        (() => {
            const theme = localStorage.getItem('theme');
            document.documentElement.classList.toggle(
                'dark',
                theme === 'dark' || (!theme && matchMedia('(prefers-color-scheme: dark)').matches)
            );
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="pt-body portal-ui min-h-screen antialiased">
    <a href="#main-content" class="gw-skip-link">{{ __('bulk.pular_para_o_conteudo') }}</a>

    @php
        $isSupport = (bool) auth()->user()?->isSuporte();

        if ($isSupport) {
            $links = [
                [
                    'name' => __('support.sidebar_subtitle'),
                    'route' => 'support.dashboard',
                    'active' => 'support.*',
                    'icon' => 'life-buoy',
                    'description' => __('pointsui.menu_support_description'),
                ],
            ];

            $homeRoute = 'support.dashboard';
            $layoutTitle = __('screen.central_de_suporte');
        } else {
            $links = [
                [
                    'name' => __('portal.home'),
                    'route' => 'user.home',
                    'active' => 'user.home',
                    'icon' => 'house',
                    'description' => __('pointsui.menu_home_description'),
                ],
                [
                    'name' => __('nav.recharge'),
                    'route' => 'totens.index',
                    'active' => ['totens.*', 'chargers.*', 'reservations.*', 'charging-sessions.*'],
                    'icon' => 'battery-charging',
                    'description' => __('nav.recharge_hint'),
                ],
                [
                    'name' => __('nav.wallet'),
                    'route' => 'wallet.index',
                    'active' => 'wallet.*',
                    'icon' => 'wallet',
                    'description' => __('pointsui.menu_wallet_description'),
                    'children' => [
                        ['name' => __('pointsui.menu_add_balance'), 'route' => 'wallet.deposit.form'],
                        ['name' => __('pointsui.menu_financial_history'), 'route' => 'wallet.history'],
                    ],
                ],
                [
                    'name' => __('portal.points'),
                    'route' => 'points.index',
                    'active' => 'points.*',
                    'icon' => 'gift',
                    'description' => __('pointsui.menu_rewards_description'),
                    'children' => [
                        ['name' => __('pointsui.menu_rewards'), 'route' => 'points.index', 'hash' => 'recompensas'],
                        ['name' => __('pointsui.menu_my_coupons'), 'route' => 'points.index', 'hash' => 'meus-cupons'],
                        ['name' => __('pointsui.menu_points_history'), 'route' => 'points.index', 'hash' => 'historico-pontos'],
                    ],
                ],
                [
                    'name' => __('nav.profile'),
                    'route' => 'user.profile',
                    'active' => 'user.profile',
                    'icon' => 'user',
                    'description' => __('pointsui.menu_profile_description'),
                    'children' => [
                        ['name' => __('pointsui.menu_personal_data'), 'route' => 'user.profile', 'hash' => 'dados-pessoais'],
                        ['name' => __('pointsui.menu_vehicles'), 'route' => 'user.profile', 'hash' => 'meus-veiculos'],
                        ['name' => __('pointsui.menu_preferences'), 'route' => 'user.profile', 'hash' => 'preferencias'],
                    ],
                ],
            ];

            $homeRoute = 'user.home';
            $layoutTitle = $pageTitle ?? __('nav.home');
        }
    @endphp

    <div
        class="pt-shell"
        data-portal-shell
        data-portal-role="{{ $isSupport ? 'support' : 'user' }}"
    >
        <div class="pt-mobile-overlay" data-mobile-close hidden></div>

        <x-portal-sidebar :links="$links" :home="$homeRoute" />

        <div class="pt-main-shell">
            <x-portal-topbar :title="$layoutTitle" />

            <main class="pt-content" id="main-content" tabindex="-1">

                @if(isset($header))
                    <div class="pt-page-head">{{ $header }}</div>
                @endif

                @if(session('success'))
                    <div class="pt-alert pt-alert-success" role="status">
                        {{ session('success') }}
                    </div>
                @endif

                @if(session('error'))
                    <div class="pt-alert pt-alert-error" role="alert">
                        {{ session('error') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="pt-alert pt-alert-error" role="alert">
                        <strong>{{ __('bulk.confira_os_campos') }}</strong>
                        {{ $errors->first() }}
                    </div>
                @endif

                {{ $slot }}

                <footer class="pt-footer">
                    <span>© {{ date('Y') }} PowerTag · Challenge GoodWe</span>
                    <span>{{ $isSupport ? __('screen.central_de_suporte') : __('footer.user_tagline') }}</span>
                </footer>
            </main>
        </div>
    </div>

    <x-portal-commands />

    @unless($isSupport)
        <x-user-chat-widget />
    @endunless

    <script src="{{ asset('js/theme.js') }}"></script>
    <script src="{{ asset('js/pwa.js') }}"></script>

    @stack('scripts')
</body>
</html>
@endif
