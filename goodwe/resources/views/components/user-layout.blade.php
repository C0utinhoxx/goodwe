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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
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
            document.documentElement.classList.toggle('dark', theme === 'dark' || (!theme && matchMedia('(prefers-color-scheme: dark)').matches));
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="pt-body portal-ui min-h-screen antialiased">
    <a href="#main-content" class="gw-skip-link">Pular para o conteúdo</a>
    @php
        $links = [
            ['name'=>__('portal.home'),'route'=>'user.home','active'=>'user.home','icon'=>'house','description'=>'Resumo da sua conta'],
            ['name'=>__('nav.chargers'),'route'=>'chargers.index','active'=>['chargers.*','reservations.*','charging-sessions.*'],'icon'=>'zap','description'=>'Estações e reservas','children'=>[
                ['name'=>'Minhas reservas','route'=>'chargers.index','hash'=>'minhas-reservas'],
            ]],
            ['name'=>__('nav.recharge'),'route'=>'totens.index','active'=>['totens.*'],'icon'=>'battery-charging','description'=>__('nav.recharge_hint')],
            ['name'=>__('nav.wallet'),'route'=>'wallet.index','active'=>'wallet.*','icon'=>'wallet','description'=>'Saldo e movimentações','children'=>[
                ['name'=>'Adicionar saldo','route'=>'wallet.deposit.form'],
                ['name'=>'Histórico financeiro','route'=>'wallet.history'],
            ]],
            ['name'=>__('portal.points'),'route'=>'points.index','active'=>'points.*','icon'=>'gift','description'=>'Recompensas e cupons','children'=>[
                ['name'=>'Recompensas','route'=>'points.index','hash'=>'recompensas'],
                ['name'=>'Meus cupons','route'=>'points.index','hash'=>'meus-cupons'],
                ['name'=>'Histórico de pontos','route'=>'points.index','hash'=>'historico-pontos'],
            ]],
            ['name'=>__('nav.profile'),'route'=>'user.profile','active'=>'user.profile','icon'=>'user','description'=>'Dados, veículos e preferências','children'=>[
                ['name'=>'Dados pessoais','route'=>'user.profile','hash'=>'dados-pessoais'],
                ['name'=>'Meus veículos','route'=>'user.profile','hash'=>'meus-veiculos'],
                ['name'=>'Preferências','route'=>'user.profile','hash'=>'preferencias'],
            ]],
        ];

        if (auth()->user()?->isSuporte()) {
            $links = [
                ['name'=>__('support.overview_title'),'route'=>'support.dashboard','active'=>'support.*','icon'=>'life-buoy','description'=>__('support.support_panel')],
            ] + $links;
        }
    @endphp
    <div class="pt-shell" data-portal-shell data-portal-role="user">
        <div class="pt-mobile-overlay" data-mobile-close hidden></div>
        <x-portal-sidebar :links="$links" home="user.home" />
        <div class="pt-main-shell">
            <x-portal-topbar :title="$pageTitle ?? 'Início'" />
            <main class="pt-content" id="main-content" tabindex="-1">
                @if(isset($header))<div class="pt-page-head">{{ $header }}</div>@endif
                @if(session('success'))<div class="pt-alert pt-alert-success" role="status">{{ session('success') }}</div>@endif
                @if(session('error'))<div class="pt-alert pt-alert-error" role="alert">{{ session('error') }}</div>@endif
                @if($errors->any())<div class="pt-alert pt-alert-error" role="alert"><strong>Confira os campos:</strong> {{ $errors->first() }}</div>@endif
                {{ $slot }}
                <footer class="pt-footer"><span>© {{ date('Y') }} PowerTag · Challenge GoodWe</span><span>Energia inteligente, conectando pessoas.</span></footer>
            </main>
        </div>
    </div>
    <x-portal-commands />
    <x-user-chat-widget />
    <script src="{{ asset('js/theme.js') }}"></script>
<script src="{{ asset('js/pwa.js') }}"></script>
</body>
</html>

@endif
