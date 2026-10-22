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
            ['name'=>__('managerlayout.overview'),'route'=>'manager.dashboard','active'=>'manager.dashboard','icon'=>'layout-dashboard','description'=>__('managerlayout.operation_indicators')],
            ['name'=>__('managerlayout.chargers'),'route'=>'manager.chargers.index','active'=>'manager.chargers.*','icon'=>'plug-zap','description'=>__('managerlayout.store_equipment'),'children'=>[
                ['name'=>__('managerlayout.add_charger'),'route'=>'manager.chargers.create'],
            ]],
            ['name'=>__('managerlayout.demand_rates'),'route'=>'manager.demand.index','active'=>'manager.demand.*|manager.demand-control.*','icon'=>'chart-no-axes-column','description'=>__('managerlayout.prices_decisions'),'children'=>[
                ['name'=>__('managerlayout.rates_summary'),'route'=>'manager.demand.index','hash'=>'resumo-tarifas'],
                ['name'=>__('managerlayout.changes_history'),'route'=>'manager.demand.index','hash'=>'historico-tarifas'],
                ['name'=>__('managerlayout.consumption_control'),'route'=>'manager.demand-control.index'],
            ]],
        ];
        $links[] = ['name'=>__('managerlayout.my_establishment'),'route'=>$establishmentRoute,'active'=>'manager.establishment.*','icon'=>'store','description'=>__('managerlayout.data_settings'),'children'=>[
            ['name'=>__('managerlayout.establishment_data'),'route'=>$establishmentRoute,'hash'=>'dados-estabelecimento'],
            ['name'=>__('managerlayout.configure_rates'),'route'=>$establishmentRoute,'hash'=>'configurar-tarifas'],
            ['name'=>__('managerlayout.reservation_rules'),'route'=>$establishmentRoute,'hash'=>'regras-reserva'],
        ]];
        $currentTitle = collect($links)->first(fn ($link) => request()->routeIs($link['active']))['name']
            ?? match (true) {
                request()->routeIs('manager.profile') => __('managerlayout.my_profile'),
                request()->routeIs('manager.chatbot.*') => __('managerlayout.ai_assistant'),
                default => __('managerlayout.manager_dashboard'),
            };
    @endphp
    <title>{{ $currentTitle }} · PowerTag {{ __('managerlayout.manager') }}</title>
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
    <a href="#main-content" class="gw-skip-link">{{ __('managerlayout.skip_content') }}</a>
    <div class="pt-shell" data-portal-shell data-portal-role="manager">
        <div class="pt-mobile-overlay" data-mobile-close hidden></div>
        <x-portal-sidebar :links="$links" :home="$setup ? 'manager.establishment.setup' : 'manager.dashboard'" manager />
        <div class="pt-main-shell">
            <x-portal-topbar :title="$currentTitle" manager />
            <main class="pt-content manager-content" id="main-content" tabindex="-1">
                @if(isset($header))<div class="pt-page-head gw-page-head">{{ $header }}</div>@endif
                <x-flash-messages />
                @if($errors->any())<div class="pt-alert pt-alert-error" role="alert"><strong>{{ __('managerlayout.check_fields') }}</strong> {{ $errors->first() }}</div>@endif
                {{ $slot }}
                <footer class="pt-footer"><span>© {{ date('Y') }} PowerTag · Challenge GoodWe</span><span>{{ __('footer.manager_tagline') }}</span></footer>
            </main>
        </div>
    </div>
    <x-portal-commands />
    @unless($setup)
        <x-chat-widget manager :history="session('manager_chat_history.scoped_v1.'.optional(Auth::user()?->establishment)->id, [])" />
    @endunless
    <dialog data-manager-confirmation class="pt-dialog gw-confirmation" aria-labelledby="manager-confirm-title" aria-describedby="manager-confirm-message">
        <div class="pt-dialog-body">
            <div class="pt-dialog-top"><div><p class="pt-eyebrow">{{ __('managerlayout.review_confirm') }}</p><h2 id="manager-confirm-title">{{ __('managerlayout.confirm_change') }}</h2></div><button type="button" class="pt-dialog-close" data-manager-confirmation-cancel aria-label="{{ __('managerlayout.close_confirmation') }}"><i data-lucide="x" aria-hidden="true"></i></button></div>            <p id="manager-confirm-message" data-manager-confirmation-message class="pt-muted"></p>
            <div class="pt-form-actions"><button type="button" data-manager-confirmation-cancel class="gw-button gw-button-secondary" autofocus>{{ __('managerlayout.continue_editing') }}</button><button type="button" data-manager-confirmation-accept class="gw-button gw-button-primary">{{ __('managerlayout.confirm') }}</button></div>
        </div>
    </dialog>
    <script src="{{ asset('js/theme.js') }}"></script>
<script src="{{ asset('js/pwa.js') }}"></script>
</body>
</html>
