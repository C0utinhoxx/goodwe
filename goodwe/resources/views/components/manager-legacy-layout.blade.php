<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- PWA -->
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#0f1117">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="GoodWe">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icons/icon-192.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
    @php
        $setup = request()->routeIs('manager.establishment.setup');
        $establishmentRoute = $setup ? 'manager.establishment.setup' : 'manager.establishment.edit';
        $links = $setup ? [] : [
            ['name'=>'Visão geral','route'=>'manager.dashboard','active'=>'manager.dashboard','icon'=>'layout-dashboard','description'=>'Indicadores da sua operação'],
            ['name'=>'Carregadores','route'=>'manager.chargers.index','active'=>'manager.chargers.*','icon'=>'plug-zap','description'=>'Equipamentos da sua loja','children'=>[
                ['name'=>'Adicionar carregador','route'=>'manager.chargers.create'],
            ]],
            ['name'=>'Demanda e tarifas','route'=>'manager.demand.index','active'=>'manager.demand.*|manager.demand-control.*','icon'=>'chart-no-axes-column','description'=>'Preços e decisões','children'=>[
                ['name'=>'Resumo de tarifas','route'=>'manager.demand.index','hash'=>'resumo-tarifas'],
                ['name'=>'Histórico de alterações','route'=>'manager.demand.index','hash'=>'historico-tarifas'],
                ['name'=>'Controle de consumo','route'=>'manager.demand-control.index'],
                ['name'=>'Distribuição inteligente','route'=>'manager.demand-control.index','hash'=>'distribuicao-inteligente'],
            ]],
        ];
        $links[] = ['name'=>'Meu Estabelecimento','route'=>$establishmentRoute,'active'=>'manager.establishment.*','icon'=>'store','description'=>'Dados e configurações','children'=>[
            ['name'=>'Dados do estabelecimento','route'=>$establishmentRoute,'hash'=>'dados-estabelecimento'],
            ['name'=>'Configurar tarifas','route'=>$establishmentRoute,'hash'=>'configurar-tarifas'],
            ['name'=>'Regras de reserva','route'=>$establishmentRoute,'hash'=>'regras-reserva'],
        ]];
        $currentTitle = collect($links)->first(fn ($link) => request()->routeIs($link['active']))['name']
            ?? match (true) {
                request()->routeIs('manager.profile') => 'Meu perfil',
                request()->routeIs('manager.chatbot.*') => 'Assistente IA',
                default => 'Painel do gestor',
            };
    @endphp
    <title>{{ $currentTitle }} · PowerTag Gestor</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
    <script>
        (() => {
            const theme = localStorage.getItem('theme');
            document.documentElement.classList.toggle('dark', theme === 'dark' || (!theme && matchMedia('(prefers-color-scheme: dark)').matches));
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="pt-body portal-ui manager-ui min-h-screen antialiased">
    <a href="#main-content" class="gw-skip-link">Pular para o conteúdo</a>
    <div class="pt-shell" data-portal-shell data-portal-role="manager">
        <div class="pt-mobile-overlay" data-mobile-close hidden></div>
        <x-portal-sidebar :links="$links" :home="$setup ? 'manager.establishment.setup' : 'manager.dashboard'" manager />
        <div class="pt-main-shell">
            <x-portal-topbar :title="$currentTitle" manager />
            <main class="pt-content manager-content" id="main-content" tabindex="-1">
                @if(isset($header))<div class="pt-page-head gw-page-head">{{ $header }}</div>@endif
                <x-flash-messages />
                @if($errors->any())<div class="pt-alert pt-alert-error" role="alert"><strong>Confira os campos:</strong> {{ $errors->first() }}</div>@endif
                {{ $slot }}
                <footer class="pt-footer"><span>© {{ date('Y') }} PowerTag · Challenge GoodWe</span><span>Gestão inteligente da sua operação.</span></footer>
            </main>
        </div>
    </div>
    <x-portal-commands />
    @unless($setup)
        <x-chat-widget manager :history="session('manager_chat_history.scoped_v1.'.optional(Auth::user()?->establishment)->id, [])" />
    @endunless
    <dialog data-manager-confirmation class="pt-dialog gw-confirmation" aria-labelledby="manager-confirm-title" aria-describedby="manager-confirm-message">
        <div class="pt-dialog-body">
            <div class="pt-dialog-top"><div><p class="pt-eyebrow">REVISAR E CONFIRMAR</p><h2 id="manager-confirm-title">Confirmar alteração</h2></div><button type="button" class="pt-dialog-close" data-manager-confirmation-cancel aria-label="Fechar confirmação"><i data-lucide="x" aria-hidden="true"></i></button></div>
            <p id="manager-confirm-message" data-manager-confirmation-message class="pt-muted"></p>
            <div class="pt-form-actions"><button type="button" data-manager-confirmation-cancel class="gw-button gw-button-secondary" autofocus>Continuar editando</button><button type="button" data-manager-confirmation-accept class="gw-button gw-button-primary">Confirmar</button></div>
        </div>
    </dialog>
    <script src="{{ asset('js/theme.js') }}"></script>
<script src="{{ asset('js/pwa.js') }}"></script>
</body>
</html>
