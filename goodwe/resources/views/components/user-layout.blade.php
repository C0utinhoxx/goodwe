<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoodWe</title>
    <link rel="icon" href="{{ asset('imagens/logo.png') }}" type="image/x-icon">
    <script>
        (function() {
            var theme = localStorage.getItem('theme');
            if (
                theme === 'dark' ||
                (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)
            ) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#FDFDFC] text-[#1b1b18] dark:bg-[#0f1117] dark:text-[#EDEDEC]">
    <div class="flex min-h-screen">
        <aside class="fixed inset-y-0 hidden w-64 shrink-0 flex-col border-r border-[#e3e3e0] bg-white dark:border-[#343843] dark:bg-[#1a1a1a] lg:flex">
            <div class="px-6 pb-6 pt-8">
                <a href="{{ Auth::user()->isSuporte() ? route('support.dashboard') : route('user.home') }}" class="flex items-center gap-2.5">
                    <img src="{{ asset('/imagens/goodwe-logo.png') }}" alt="Website logo" class="h-10 w-auto object-contain">
                </a>
                <p class="mt-2 text-xs text-gray-500 dark:text-[#a0a0a0]">
                    {{ Auth::user()->isSuporte() ? __('support.sidebar_subtitle') : __('app.subtitle') }}
                </p>
            </div>
            @php
                $navActive = 'flex items-center gap-3 rounded-lg border border-[#e53935]/40 bg-[#e53935]/10 px-4 py-2.5 text-sm font-medium text-[#e53935]';
                $navInactive = 'flex items-center gap-3 rounded-lg px-4 py-2.5 text-sm font-medium text-[#706f6c] hover:bg-[#e3e3e0]/50 hover:text-[#1b1b18] dark:text-[#a0a0a0] dark:hover:bg-[#343843] dark:hover:text-white transition-colors';
                $isHome = request()->routeIs('user.home');
                $isChargers = request()->routeIs('chargers.*');
                $isProfile = request()->routeIs('user.profile');
                $isManager = request()->routeIs('manager.dashboard');
                $isSupport = Auth::user()->isSuporte();
                $supportStatus = request()->query('status');
            @endphp
            <nav class="space-y-1 px-4">
                @if($isSupport)
                    {{-- Menu exclusivo para contas de suporte: todos os links já funcionam na tela existente. --}}
                    @php
                        $supportLinks = [
                            [
                                'label' => __('support.nav_overview'),
                                'href' => route('support.dashboard'),
                                'active' => $supportStatus === null,
                                'icon' => 'M3 3h7v7H3V3zm11 0h7v7h-7V3zM3 14h7v7H3v-7zm11 0h7v7h-7v-7z',
                            ],
                            [
                                'label' => __('support.nav_all'),
                                'href' => route('support.dashboard', ['status' => 'todos']),
                                'active' => $supportStatus === 'todos',
                                'icon' => 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01',
                            ],
                            [
                                'label' => __('support.nav_waiting'),
                                'href' => route('support.dashboard', ['status' => 'aguardando']),
                                'active' => $supportStatus === 'aguardando',
                                'icon' => 'M12 6v6l4 2m5-2a9 9 0 11-18 0 9 9 0 0118 0z',
                            ],
                            [
                                'label' => __('support.nav_in_progress'),
                                'href' => route('support.dashboard', ['status' => 'em_atendimento']),
                                'active' => $supportStatus === 'em_atendimento',
                                'icon' => 'M8 10h8m-8 4h5m8-2c0 4.971-4.029 9-9 9a9.955 9.955 0 01-4.78-1.213L3 21l1.213-5.22A9.955 9.955 0 013 12a9 9 0 1118 0z',
                            ],
                            [
                                'label' => __('support.nav_resolved'),
                                'href' => route('support.dashboard', ['status' => 'resolvido']),
                                'active' => $supportStatus === 'resolvido',
                                'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
                            ],
                        ];
                    @endphp
                    @foreach($supportLinks as $supportLink)
                        <a href="{{ $supportLink['href'] }}"
                           class="{{ $supportLink['active'] ? $navActive : $navInactive }}"
                           @if($supportLink['active']) aria-current="page" @endif>
                            <svg class="h-5 w-5" fill="none" stroke="currentColor"
                                 viewBox="0 0 24 24" stroke-width="1.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="{{ $supportLink['icon'] }}" />
                            </svg>
                            <span>{{ $supportLink['label'] }}</span>
                        </a>
                    @endforeach
                @else
                {{-- INÍCIO --}}
                <a
                    href="{{ route('user.home') }}"
                    class="{{ $isHome ? $navActive : $navInactive }}"
                >
                    <svg
                        class="h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"
                        />
                    </svg>
                    <span>{{ __('nav.home') }}</span>
                </a>
                {{-- CARREGADORES --}}
                <a
                    href="{{ route('chargers.index') }}"
                    class="{{ $isChargers ? $navActive : $navInactive }}"
                >
                    <svg
                        class="h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"
                        />
                    </svg>
                    <span>{{ __('nav.chargers') }}</span>
                </a>
                {{-- CARTEIRA --}}
                <a
                    href="{{ route('wallet.index') }}"
                    class="{{ request()->routeIs('wallet.index') ? $navActive : $navInactive }}"
                >
                    <svg
                        class="h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M21 11.25v8.25a1.5 1.5 0 01-1.5 1.5H5.25a1.5 1.5 0 01-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 109.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1114.625 7.5H12m0 0V21m-8.625-9.75h18c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-18c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"
                        />
                    </svg>
                    <span>{{ __('nav.wallet') }}</span>
                </a>
                {{-- HISTÓRICO --}}
                <a
                    href="{{ route('wallet.history') }}"
                    class="{{ request()->routeIs('wallet.history') ? $navActive : $navInactive }}"
                >
                    <svg
                        class="h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                    <span>{{ __('nav.history') }}</span>
                </a>
                {{-- AJUDA --}}
                <a
                    href="#"
                    class="{{ $navInactive }}"
                >
                    <svg
                        class="h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z"
                        />
                    </svg>
                    <span>{{ __('nav.help') }}</span>
                </a>
                {{-- ÁREA DO GESTOR --}}
                @if(Auth::user()->isGestor())
                    {{-- PAINEL DO GESTOR --}}
                    <a
                        href="{{ route('manager.dashboard') }}"
                        class="{{ $isManager ? $navActive : $navInactive }}"
                    >
                        <svg
                            class="h-5 w-5"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"
                            />
                        </svg>
                        <span>{{ __('nav.gestor') }}</span>
                    </a>
                @endif
                @endif
            </nav>
            {{-- PARTE INFERIOR DA SIDEBAR --}}
            <div class="mt-auto space-y-1 border-t border-[#e3e3e0] px-4 pb-6 pt-4 dark:border-[#343843]">
                {{-- PERFIL --}}
                <a
                    href="{{ route('user.profile') }}"
                    class="{{ $isProfile ? $navActive : $navInactive }}"
                >
                    <svg
                        class="h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"
                        />
                    </svg>
                    <span>{{ __('nav.profile') }}</span>
                </a>
                {{-- SAIR --}}
                <form
                    method="POST"
                    action="{{ route('logout') }}"
                >
                    @csrf
                    <button
                        type="submit"
                        class="flex w-full items-center gap-3 rounded-lg px-4 py-2.5 text-sm font-medium text-[#706f6c] transition-colors hover:bg-[#e3e3e0]/50 hover:text-[#1b1b18] dark:text-[#a0a0a0] dark:hover:bg-[#343843] dark:hover:text-white"
                    >
                        <svg
                            class="h-5 w-5"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"
                            />
                        </svg>
                        <span>{{ __('app.logout') }}</span>
                    </button>
                </form>
            </div>
        </aside>
        {{-- CONTEÚDO PRINCIPAL --}}
        <main class="min-w-0 flex-1 lg:ml-64">
            <div class="mx-auto max-w-[1280px] px-5 py-8 sm:px-6 lg:px-8">
                <header class="{{ $isManager ? 'flex flex-col items-stretch gap-4 md:flex-row md:items-center md:justify-between' : 'flex items-center justify-between gap-4' }}">
                    <div>
                        {{ $header ?? '' }}
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        {{-- IDIOMA --}}
                        <x-lang-switcher variant="boxed" />
                        {{-- TEMA --}}
                        <x-theme-toggle variant="boxed" />
                        <div class="hidden h-8 border-l border-[#e3e3e0] dark:border-[#343843] sm:block"></div>
                        {{-- PERFIL --}}
                        <a
                            href="{{ route('user.profile') }}"
                            class="flex h-10 w-10 items-center justify-center rounded-full border border-[#e53935]/40 bg-[#e53935]/10 text-sm font-semibold text-[#e53935] transition-colors hover:bg-[#e53935]/20"
                            title="{{ __('nav.profile') }}"
                        >
                            {{ strtoupper(substr(Auth::user()->name ?? 'U', 0, 2)) }}
                        </a>
                        {{-- LOGOUT --}}
                        <form
                            method="POST"
                            action="{{ route('logout') }}"
                            class="inline"
                        >
                            @csrf
                            <button
                                type="submit"
                                class="flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg border border-gray-200 bg-gray-50 text-gray-500 transition-all hover:border-[#e53935]/40 hover:text-[#e53935] dark:border-[#343843] dark:bg-[#181b22] dark:text-[#a0a0a0] dark:hover:text-[#e53935]"
                                title="{{ __('app.logout') }}"
                            >
                                <svg
                                    class="h-4 w-4"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"
                                    />
                                </svg>
                            </button>
                        </form>
                    </div>
                </header>
                <x-flash-messages />
                {{ $slot }}
            </div>
        </main>
    </div>
    @unless($isSupport)
    @php
        $newConversationLabel = match (app()->getLocale()) {
            'en' => 'New conversation',
            'es' => 'Nueva conversación',
            default => 'Nova conversa',
        };
    @endphp
    {{-- BOTAO FLUTUANTE DO ASSISTENTE (canto inferior esquerdo) --}}
    <button
        id="chatbotLauncher"
        class="goodwe-widget-launcher"
        type="button"
        aria-label="{{ __('nav.chatbot') }}"
        aria-expanded="false"
        aria-controls="chatbotWidget"
        title="{{ __('nav.chatbot') }}"
    >
        <span class="goodwe-widget-launcher-core" aria-hidden="true">
            <svg class="goodwe-widget-launcher-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.7">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8V4H8" />
                <rect width="16" height="12" x="4" y="8" rx="2" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M2 14h2" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M20 14h2" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 13v2" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 13v2" />
            </svg>
        </span>
    </button>
    {{-- PAINEL DE CHAT FLUTUANTE --}}
    <div id="chatbotWidgetOverlay" class="goodwe-widget-overlay" style="display: none;"></div>
    <div id="chatbotWidget" class="goodwe-widget-panel" role="dialog" aria-modal="true" aria-labelledby="chatbotWidgetTitle" style="display: none;">
        <div class="goodwe-widget-header">
            <div class="goodwe-widget-header-info">
                <div class="goodwe-widget-header-avatar">W</div>
                <div class="goodwe-widget-header-text">
                    <h2 id="chatbotWidgetTitle">{{ __('chatbot.title') }}</h2>
                    <p class="goodwe-widget-header-status">
                        <span class="goodwe-widget-status-dot"></span>
                        Online
                    </p>
                </div>
            </div>
            <div class="goodwe-widget-header-actions">
                <button id="chatbotWidgetNew" class="goodwe-widget-header-btn" type="button" aria-label="{{ $newConversationLabel }}" title="{{ $newConversationLabel }}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                </button>
                <button id="chatbotWidgetClose" class="goodwe-widget-header-btn" type="button" aria-label="Fechar">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
        {{-- Abas do assistente flutuante: disponíveis para usuário e gestor. --}}
        <div class="goodwe-widget-tabs" role="tablist" aria-label="Atendimento GoodWe">
            <button type="button" id="goodweWidgetTabIA" class="goodwe-widget-tab active"
                    role="tab" aria-selected="true" aria-controls="goodweWidgetIAView">Chat com IA</button>
            <button type="button" id="goodweWidgetTabTickets" class="goodwe-widget-tab"
                    role="tab" aria-selected="false" aria-controls="goodweWidgetTicketsView">Meus Tickets</button>
        </div>
        <section id="goodweWidgetIAView" class="goodwe-widget-mainview"
                 role="tabpanel" aria-labelledby="goodweWidgetTabIA">
        <div id="chatbotWidgetMessages" class="goodwe-widget-messages">
            <div id="chatbotWidgetWelcome" class="goodwe-widget-welcome">
                <div class="goodwe-widget-welcome-logo">W</div>
                <h3 class="goodwe-widget-welcome-title">{{ __('chatbot.welcome') }}</h3>
                <p class="goodwe-widget-welcome-text">{{ __('chatbot.description') }}</p>
                <div class="goodwe-widget-suggestions">
                    @if(Auth::user()->isGestor())
                        <button type="button" class="goodwe-widget-suggestion" data-question="{{ __('chatbot.suggestion_business_question') }}">
                            📊 <span>{{ __('chatbot.suggestion_business') }}</span>
                        </button>
                        <button type="button" class="goodwe-widget-suggestion" data-question="{{ __('chatbot.suggestion_sessions_question') }}">
                            ⚡ <span>{{ __('chatbot.suggestion_sessions') }}</span>
                        </button>
                        <button type="button" class="goodwe-widget-suggestion" data-question="{{ __('chatbot.suggestion_revenue_question') }}">
                            💰 <span>{{ __('chatbot.suggestion_revenue') }}</span>
                        </button>
                        <button type="button" class="goodwe-widget-suggestion" data-question="{{ __('chatbot.suggestion_energy_question') }}">
                            🔋 <span>{{ __('chatbot.suggestion_energy') }}</span>
                        </button>
                    @else
                        @php
                            $widgetSuggestions = match (app()->getLocale()) {
                                'en' => [
                                    ['icon' => '💰', 'title' => 'My balance', 'question' => 'What is my current balance?'],
                                    ['icon' => '⭐', 'title' => 'My points', 'question' => 'How many points do I have?'],
                                    ['icon' => '⚡', 'title' => 'Available chargers', 'question' => 'Which chargers are available right now?'],
                                    ['icon' => '🕘', 'title' => 'Recent charges', 'question' => 'What were my latest charging sessions?'],
                                ],
                                'es' => [
                                    ['icon' => '💰', 'title' => 'Mi saldo', 'question' => '¿Cuál es mi saldo actual?'],
                                    ['icon' => '⭐', 'title' => 'Mis puntos', 'question' => '¿Cuántos puntos tengo?'],
                                    ['icon' => '⚡', 'title' => 'Cargadores disponibles', 'question' => '¿Qué cargadores están disponibles ahora?'],
                                    ['icon' => '🕘', 'title' => 'Recargas recientes', 'question' => '¿Cuáles fueron mis últimas recargas?'],
                                ],
                                default => [
                                    ['icon' => '💰', 'title' => 'Meu saldo', 'question' => 'Qual é o meu saldo atual?'],
                                    ['icon' => '⭐', 'title' => 'Meus pontos', 'question' => 'Quantos pontos eu tenho?'],
                                    ['icon' => '⚡', 'title' => 'Carregadores disponíveis', 'question' => 'Quais carregadores estão disponíveis agora?'],
                                    ['icon' => '🕘', 'title' => 'Recargas recentes', 'question' => 'Quais foram minhas últimas recargas?'],
                                ],
                            };
                        @endphp
                        @foreach($widgetSuggestions as $widgetSuggestion)
                            <button type="button" class="goodwe-widget-suggestion" data-question="{{ $widgetSuggestion['question'] }}">
                                {{ $widgetSuggestion['icon'] }} <span>{{ $widgetSuggestion['title'] }}</span>
                            </button>
                        @endforeach
                    @endif
                </div>
            </div>
            <div id="chatbotWidgetConversation" class="goodwe-widget-conversation" style="display: none;"></div>
        </div>
        <div class="goodwe-widget-composer-area">
            <form id="chatbotWidgetForm">
                @csrf
                <div class="goodwe-widget-composer">
                    <textarea
                        id="chatbotWidgetInput"
                        class="goodwe-widget-input"
                        rows="1"
                        maxlength="1000"
                        autocomplete="off"
                        placeholder="{{ __('chatbot.placeholder') }}"
                    ></textarea>
                    <button
                        id="chatbotWidgetSend"
                        class="goodwe-widget-send"
                        type="submit"
                        disabled
                        aria-label="{{ __('chatbot.send') }}"
                        title="{{ __('chatbot.send') }}"
                    >
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5m0 0-5 5m5-5 5 5" />
                        </svg>
                    </button>
                </div>
                <p class="goodwe-widget-disclaimer">{{ __('chatbot.warning') }}</p>
            </form>
        </div>
        </section>

        {{-- Tickets ficam separados das conversas com a IA. --}}
        <section id="goodweWidgetTicketsView" class="goodwe-widget-ticket-view"
                 role="tabpanel" aria-labelledby="goodweWidgetTabTickets" hidden>
            <div id="goodweWidgetTicketList" class="goodwe-widget-ticket-list-view">
                <div class="goodwe-widget-ticket-toolbar">
                    <div>
                        <strong>Meus Tickets</strong>
                        <p>Chamados enviados ao Suporte GoodWe</p>
                    </div>
                    <button id="goodweWidgetTicketRefresh" type="button" class="goodwe-widget-ticket-btn">Atualizar</button>
                </div>
                <div id="goodweWidgetTicketListError" class="goodwe-widget-ticket-error" role="alert" hidden></div>
                <div id="goodweWidgetTicketItems" class="goodwe-widget-ticket-items" aria-live="polite">
                    <p class="goodwe-widget-ticket-empty">Abra a aba para consultar seus tickets.</p>
                </div>
                <div class="goodwe-widget-ticket-pages">
                    <button id="goodweWidgetTicketPrev" class="goodwe-widget-ticket-btn" type="button" hidden>Anterior</button>
                    <button id="goodweWidgetTicketNext" class="goodwe-widget-ticket-btn" type="button" hidden>Próxima</button>
                </div>
            </div>
            <div id="goodweWidgetTicketDetail" class="goodwe-widget-ticket-detail-view" hidden>
                <div class="goodwe-widget-ticket-toolbar">
                    <div>
                        <strong id="goodweWidgetTicketTitle">Conversa do Ticket</strong>
                        <p id="goodweWidgetTicketStatus">Aguardando...</p>
                    </div>
                    <div class="goodwe-widget-ticket-toolbar-actions">
                        <button id="goodweWidgetTicketReload" type="button" class="goodwe-widget-ticket-btn">Atualizar</button>
                        <button id="goodweWidgetTicketBack" type="button" class="goodwe-widget-ticket-btn">Voltar</button>
                    </div>
                </div>
                <div id="goodweWidgetTicketDetailError" class="goodwe-widget-ticket-error" role="alert" hidden></div>
                <div id="goodweWidgetTicketMessages" class="goodwe-widget-ticket-messages" aria-live="polite"></div>
                <form id="goodweWidgetTicketReplyForm" class="goodwe-widget-ticket-reply" hidden>
                    @csrf
                    <label for="goodweWidgetTicketReplyInput">Responder ao suporte</label>
                    <textarea id="goodweWidgetTicketReplyInput" rows="2" maxlength="10000"
                              placeholder="Digite sua mensagem..."></textarea>
                    <button type="submit" id="goodweWidgetTicketReplySend" class="goodwe-widget-ticket-primary">Enviar resposta</button>
                </form>
                <p id="goodweWidgetTicketResolved" class="goodwe-widget-ticket-resolved" hidden>
                    Este ticket está resolvido. A conversa continua disponível para consulta.
                </p>
            </div>
        </section>
    </div>
    <style>
        .goodwe-widget-launcher {
            position: fixed;
            bottom: 26px;
            right: 26px;
            width: 60px;
            height: 60px;
            padding: 0;
            border: 0;
            border-radius: 50%;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 1050;
            background:
                radial-gradient(circle at 32% 26%, rgba(255, 255, 255, .45), transparent 46%),
                linear-gradient(160deg, #ff5252 0%, #e53935 45%, #9f1f1f 100%);
            box-shadow:
                inset 0 2px 3px rgba(255, 255, 255, .5),
                inset 0 -5px 9px rgba(0, 0, 0, .38),
                0 10px 26px rgba(229, 57, 53, .45),
                0 4px 10px rgba(0, 0, 0, .3);
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .goodwe-widget-launcher::before,
        .goodwe-widget-launcher::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 50%;
            border: 2px solid rgba(229, 57, 53, .55);
            animation: goodwe-widget-pulse 2.6s ease-out infinite;
            pointer-events: none;
        }
        .goodwe-widget-launcher::after {
            animation-delay: 1.3s;
        }
        @keyframes goodwe-widget-pulse {
            0% { transform: scale(1); opacity: .85; }
            100% { transform: scale(1.95); opacity: 0; }
        }
        .goodwe-widget-launcher:hover {
            transform: translateY(-4px) scale(1.06);
            box-shadow:
                inset 0 2px 3px rgba(255, 255, 255, .55),
                inset 0 -5px 9px rgba(0, 0, 0, .38),
                0 18px 38px rgba(229, 57, 53, .55),
                0 0 28px rgba(229, 57, 53, .45);
        }
        .goodwe-widget-launcher:active {
            transform: translateY(1px) scale(.96);
            box-shadow:
                inset 0 4px 8px rgba(0, 0, 0, .45),
                inset 0 -1px 2px rgba(255, 255, 255, .2),
                0 4px 12px rgba(229, 57, 53, .4);
        }
        .goodwe-widget-launcher.is-open::before,
        .goodwe-widget-launcher.is-open::after {
            animation: none;
            opacity: 0;
        }
        .goodwe-widget-launcher.is-open {
            transform: scale(.94);
            box-shadow:
                inset 0 3px 6px rgba(0, 0, 0, .35),
                0 0 0 3px rgba(229, 57, 53, .18),
                0 8px 20px rgba(229, 57, 53, .35);
        }
        .goodwe-widget-launcher-core {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
        }
        .goodwe-widget-launcher-icon {
            display: block;
            width: 28px;
            height: 28px;
            filter: drop-shadow(0 2px 3px rgba(0, 0, 0, .35));
        }
        .goodwe-widget-overlay {
            position: fixed;
            inset: 0;
            background: linear-gradient(to top left, rgba(229, 57, 53, .1), rgba(0, 0, 0, .45));
            backdrop-filter: blur(2px);
            -webkit-backdrop-filter: blur(2px);
            z-index: 1040;
            animation: goodwe-widget-fade .2s ease;
        }
        .goodwe-widget-panel {
            position: fixed;
            bottom: 104px;
            right: 26px;
            width: min(400px, calc(100vw - 48px));
            height: min(570px, calc(100vh - 145px));
            display: flex;
            flex-direction: column;
            background: rgba(253, 253, 252, .92);
            backdrop-filter: blur(20px) saturate(1.4);
            -webkit-backdrop-filter: blur(20px) saturate(1.4);
            border: 1px solid rgba(255, 255, 255, .65);
            border-radius: 22px;
            overflow: hidden;
            z-index: 1050;
            transform-origin: bottom right;
            box-shadow:
                0 0 0 1px rgba(229, 57, 53, .2),
                0 24px 60px rgba(0, 0, 0, .25),
                0 0 50px rgba(229, 57, 53, .14),
                inset 0 1px 0 rgba(255, 255, 255, .8);
            animation: goodwe-widget-pop .3s cubic-bezier(.16, 1, .3, 1);
        }
        @keyframes goodwe-widget-fade {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes goodwe-widget-pop {
            from { opacity: 0; transform: translateY(18px) scale(.92); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .goodwe-widget-header {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding: 13px 14px;
            background: linear-gradient(135deg, rgba(255, 255, 255, .95), rgba(250, 250, 249, .85));
            border-bottom: 1px solid rgba(228, 228, 231, .8);
            flex-shrink: 0;
        }
        .goodwe-widget-header::after {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            bottom: -1px;
            height: 2px;
            background: linear-gradient(90deg, #e53935, #ff4081, #4fc3f7, #e53935);
            background-size: 300% 100%;
            animation: goodwe-widget-neonline 5s linear infinite;
        }
        @keyframes goodwe-widget-neonline {
            from { background-position: 0% 0; }
            to { background-position: 300% 0; }
        }
        .goodwe-widget-header-info {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }
        .goodwe-widget-header-avatar {
            width: 34px;
            height: 34px;
            flex-shrink: 0;
            border-radius: 10px;
            background:
                radial-gradient(circle at 30% 25%, rgba(255, 255, 255, .4), transparent 50%),
                linear-gradient(160deg, #ff5252, #c62828);
            color: #fff;
            font-size: 14px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow:
                inset 0 1px 2px rgba(255, 255, 255, .45),
                0 4px 12px rgba(229, 57, 53, .4);
        }
        .goodwe-widget-header-text {
            min-width: 0;
        }
        .goodwe-widget-header-text h2 {
            margin: 0;
            font-size: 14px;
            font-weight: 700;
            color: #18181b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .goodwe-widget-header-status {
            display: flex;
            align-items: center;
            gap: 6px;
            margin: 2px 0 0;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .4px;
            color: #10b981;
        }
        .goodwe-widget-status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #10b981;
            box-shadow: 0 0 8px rgba(16, 185, 129, .9);
            animation: goodwe-widget-blink 1.8s ease-in-out infinite;
        }
        @keyframes goodwe-widget-blink {
            0%, 100% { opacity: 1; }
            50% { opacity: .35; }
        }
        .goodwe-widget-header-actions {
            display: flex;
            align-items: center;
            gap: 4px;
            flex-shrink: 0;
        }
        .goodwe-widget-header-btn {
            width: 30px;
            height: 30px;
            padding: 0;
            border: 0;
            border-radius: 8px;
            background: transparent;
            color: #71717a;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: .15s ease;
        }
        .goodwe-widget-header-btn:hover {
            background: #f4f4f5;
            color: #e53935;
        }
        .goodwe-widget-header-btn svg {
            width: 16px;
            height: 16px;
        }
        .goodwe-widget-messages {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            scrollbar-width: thin;
            background: transparent;
        }
        .goodwe-widget-welcome {
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px 18px;
            text-align: center;
        }
        .goodwe-widget-welcome-logo {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            background:
                radial-gradient(circle at 30% 25%, rgba(255, 255, 255, .45), transparent 50%),
                linear-gradient(160deg, #ff5252, #c62828);
            color: #fff;
            font-size: 18px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow:
                inset 0 1px 2px rgba(255, 255, 255, .5),
                0 8px 24px rgba(229, 57, 53, .4),
                0 0 30px rgba(229, 57, 53, .25);
            animation: goodwe-widget-float 3.5s ease-in-out infinite;
        }
        @keyframes goodwe-widget-float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-6px); }
        }
        .goodwe-widget-welcome-title {
            margin: 12px 0 0;
            font-size: 17px;
            font-weight: 650;
            color: #18181b;
        }
        .goodwe-widget-welcome-text {
            margin: 6px 0 0;
            max-width: 280px;
            font-size: 12px;
            line-height: 1.5;
            color: #71717a;
        }
        .goodwe-widget-suggestions {
            margin-top: 16px;
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .goodwe-widget-suggestion {
            padding: 10px 10px;
            border: 1px solid rgba(229, 57, 53, .22);
            border-radius: 12px;
            background: linear-gradient(160deg, rgba(255, 255, 255, .9), rgba(250, 250, 249, .7));
            color: #18181b;
            font-size: 12px;
            font-weight: 600;
            text-align: left;
            cursor: pointer;
            transition: .18s ease;
        }
        .goodwe-widget-suggestion:hover {
            border-color: rgba(229, 57, 53, .65);
            background: #fff;
            box-shadow: 0 0 16px rgba(229, 57, 53, .22), 0 4px 12px rgba(0, 0, 0, .06);
            transform: translateY(-2px);
        }
        .goodwe-widget-conversation {
            padding: 16px 14px 4px;
        }
        .goodwe-widget-row {
            display: flex;
            width: 100%;
            margin-bottom: 14px;
        }
        .goodwe-widget-row.user {
            justify-content: flex-end;
        }
        .goodwe-widget-row.assistant {
            justify-content: flex-start;
            align-items: flex-start;
            gap: 8px;
        }
        .goodwe-widget-bubble {
            max-width: 78%;
            padding: 8px 12px;
            border-radius: 16px;
            background: #eeeeef;
            color: #18181b;
            font-size: 13px;
            line-height: 1.5;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .goodwe-widget-avatar {
            width: 24px;
            height: 24px;
            flex-shrink: 0;
            border-radius: 8px;
            background: #e53935;
            color: #fff;
            font-size: 10px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .goodwe-widget-reply {
            flex: 1;
            min-width: 0;
            padding-top: 3px;
            color: #27272a;
            font-size: 13px;
            line-height: 1.6;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .goodwe-widget-loading {
            display: flex;
            gap: 3px;
            padding-top: 7px;
        }
        .goodwe-widget-loading span {
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: #a1a1aa;
            animation: goodwe-widget-bounce 1s infinite alternate;
        }
        .goodwe-widget-loading span:nth-child(2) { animation-delay: .15s; }
        .goodwe-widget-loading span:nth-child(3) { animation-delay: .3s; }
        @keyframes goodwe-widget-bounce {
            from { transform: translateY(0); opacity: .45; }
            to { transform: translateY(-3px); opacity: 1; }
        }
        .goodwe-widget-composer-area {
            flex-shrink: 0;
            padding: 10px 12px 12px;
            border-top: 1px solid rgba(228, 228, 231, .8);
            background: linear-gradient(135deg, rgba(255, 255, 255, .95), rgba(250, 250, 249, .85));
        }
        .goodwe-widget-composer {
            position: relative;
            display: flex;
            align-items: flex-end;
        }
        .goodwe-widget-input {
            flex: 1;
            min-height: 40px;
            max-height: 110px;
            padding: 10px 46px 10px 12px;
            border: 1px solid #d4d4d8;
            border-radius: 20px;
            background: #fff;
            color: #18181b;
            font-family: inherit;
            font-size: 13px;
            line-height: 20px;
            resize: none;
            overflow-y: auto;
            outline: none;
            transition: border-color .15s ease;
        }
        .goodwe-widget-input:focus {
            border-color: rgba(229, 57, 53, .7);
            box-shadow: 0 0 0 3px rgba(229, 57, 53, .15), 0 0 14px rgba(229, 57, 53, .12);
        }
        .goodwe-widget-input::placeholder {
            color: #a1a1aa;
        }
        .goodwe-widget-send {
            position: absolute;
            right: 5px;
            bottom: 4px;
            width: 32px;
            height: 32px;
            padding: 0;
            border: 0;
            border-radius: 50%;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            background:
                radial-gradient(circle at 32% 26%, rgba(255, 255, 255, .45), transparent 48%),
                linear-gradient(160deg, #ff5252, #c62828);
            box-shadow:
                inset 0 1px 2px rgba(255, 255, 255, .45),
                inset 0 -2px 4px rgba(0, 0, 0, .25),
                0 4px 12px rgba(229, 57, 53, .4);
            transition: .15s ease;
        }
        .goodwe-widget-send:hover:not(:disabled) {
            transform: translateY(-1px) scale(1.05);
            box-shadow:
                inset 0 1px 2px rgba(255, 255, 255, .5),
                inset 0 -2px 4px rgba(0, 0, 0, .25),
                0 6px 18px rgba(229, 57, 53, .55),
                0 0 18px rgba(229, 57, 53, .4);
        }
        .goodwe-widget-send:disabled {
            background: linear-gradient(160deg, #d4d4d8, #b8b8bd);
            color: #71717a;
            box-shadow: inset 0 1px 2px rgba(255, 255, 255, .5), inset 0 -2px 4px rgba(0, 0, 0, .12);
            cursor: default;
        }
        .goodwe-widget-send svg {
            width: 16px;
            height: 16px;
        }
        .goodwe-widget-disclaimer {
            margin: 6px 0 0;
            text-align: center;
            font-size: 10px;
            color: #a1a1aa;
        }
        html.dark .goodwe-widget-panel {
            background: rgba(15, 17, 23, .92);
            border-color: rgba(255, 255, 255, .08);
            box-shadow:
                0 0 0 1px rgba(229, 57, 53, .3),
                0 24px 60px rgba(0, 0, 0, .65),
                0 0 55px rgba(229, 57, 53, .18),
                inset 0 1px 0 rgba(255, 255, 255, .06);
        }
        html.dark .goodwe-widget-header {
            background: linear-gradient(135deg, rgba(26, 28, 32, .95), rgba(20, 22, 27, .85));
            border-color: rgba(52, 54, 61, .8);
        }
        html.dark .goodwe-widget-header-text h2 {
            color: #f7f7f8;
        }
        html.dark .goodwe-widget-header-btn {
            color: #8b8d94;
        }
        html.dark .goodwe-widget-header-btn:hover {
            background: #24262c;
            color: #ef5350;
        }
        html.dark .goodwe-widget-messages {
            background: transparent;
        }
        html.dark .goodwe-widget-welcome-title {
            color: #f7f7f8;
        }
        html.dark .goodwe-widget-welcome-text {
            color: #a1a1aa;
        }
        html.dark .goodwe-widget-suggestion {
            border-color: rgba(229, 57, 53, .3);
            background: linear-gradient(160deg, rgba(36, 38, 44, .9), rgba(24, 26, 32, .7));
            color: #f7f7f8;
        }
        html.dark .goodwe-widget-suggestion:hover {
            border-color: rgba(229, 57, 53, .7);
            background: #24262c;
            box-shadow: 0 0 16px rgba(229, 57, 53, .3), 0 4px 12px rgba(0, 0, 0, .35);
        }
        html.dark .goodwe-widget-bubble {
            background: #2a2c32;
            color: #f7f7f8;
        }
        html.dark .goodwe-widget-reply {
            color: #ececef;
        }
        html.dark .goodwe-widget-composer-area {
            background: linear-gradient(135deg, rgba(24, 26, 32, .95), rgba(20, 22, 27, .85));
            border-color: rgba(52, 54, 61, .8);
        }
        html.dark .goodwe-widget-input {
            border-color: #3a3c43;
            background: #24262c;
            color: #f7f7f8;
        }
        html.dark .goodwe-widget-input::placeholder {
            color: #8b8d94;
        }
        html.dark .goodwe-widget-send:disabled {
            background: linear-gradient(160deg, #41434a, #33353b);
            color: #85878e;
            box-shadow: inset 0 1px 2px rgba(255, 255, 255, .07), inset 0 -2px 4px rgba(0, 0, 0, .35);
        }
        html.dark .goodwe-widget-disclaimer {
            color: #777981;
        }

        /* Abas e tickets no assistente flutuante, seguindo o tema GoodWe. */
        .goodwe-widget-tabs {
            display: grid; grid-template-columns: 1fr 1fr; gap: 6px;
            padding: 9px 12px; border-bottom: 1px solid #e4e4e7;
            background: rgba(250,250,250,.92); flex-shrink: 0;
        }
        .goodwe-widget-tab {
            border: 1px solid transparent; border-radius: 9px; padding: 9px 6px;
            background: transparent; color: #71717a; font-size: 12px;
            font-weight: 700; cursor: pointer; transition: .15s;
        }
        .goodwe-widget-tab:hover { color: #e53935; }
        .goodwe-widget-tab.active {
            color: #e53935; border-color: rgba(229,57,53,.28);
            background: rgba(229,57,53,.08);
        }
        .goodwe-widget-mainview, .goodwe-widget-ticket-view {
            flex: 1; min-height: 0; display: flex; flex-direction: column;
        }
        .goodwe-widget-mainview[hidden],
        .goodwe-widget-ticket-view[hidden],
        .goodwe-widget-ticket-list-view[hidden],
        .goodwe-widget-ticket-detail-view[hidden],
        .goodwe-widget-ticket-reply[hidden],
        .goodwe-widget-ticket-resolved[hidden],
        .goodwe-widget-ticket-btn[hidden],
        .goodwe-widget-header-btn[hidden],
        .goodwe-widget-ticket-error[hidden] { display: none !important; }
        .goodwe-widget-ticket-view { padding: 12px; gap: 8px; }
        .goodwe-widget-ticket-list-view,
        .goodwe-widget-ticket-detail-view {
            display: flex; flex: 1; min-height: 0; flex-direction: column; gap: 10px;
        }
        .goodwe-widget-ticket-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .goodwe-widget-ticket-toolbar-actions { display: flex; gap: 5px; flex-shrink: 0; }
        .goodwe-widget-ticket-toolbar strong {
            color: #18181b; font-weight: 700; font-size: 13px;
            display: block; line-height: 1.5; overflow-wrap: anywhere;
        }
        .goodwe-widget-ticket-toolbar p { margin: 3px 0 0; color: #71717a; font-size: 10px; }
        .goodwe-widget-ticket-btn {
            flex-shrink: 0; border-radius: 9px; padding: 7px 10px;
            background: #fff; border: 1px solid #d4d4d8;
            color: #52525b; font-size: 11px; font-weight: 650; cursor: pointer;
        }
        .goodwe-widget-ticket-btn:hover { color: #e53935; border-color: #e53935; }
        .goodwe-widget-ticket-btn:disabled { opacity: .5; cursor: wait; }
        .goodwe-widget-ticket-items, .goodwe-widget-ticket-messages {
            flex: 1; min-height: 0; overflow-y: auto; scrollbar-width: thin;
            display: flex; flex-direction: column; gap: 9px;
        }
        .goodwe-widget-ticket-card {
            border-radius: 12px; border: 1px solid #e4e4e7;
            padding: 12px; background: rgba(255,255,255,.76);
        }
        .goodwe-widget-ticket-card-head,
        .goodwe-widget-ticket-card-foot,
        .goodwe-widget-ticket-pages { display: flex; gap: 8px; justify-content: space-between; align-items: center; }
        .goodwe-widget-ticket-number { color: #e53935; font-weight: 750; font-size: 11px; }
        .goodwe-widget-ticket-card-title { margin: 8px 0 4px; font-weight: 650; font-size: 12px; color: #18181b; }
        .goodwe-widget-ticket-card-preview {
            margin: 0 0 10px; color: #71717a; font-size: 11px; line-height: 1.5;
            overflow-wrap: anywhere; display: -webkit-box;
            -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
        }
        .goodwe-widget-ticket-card-foot { color: #71717a; font-size: 10px; }
        .goodwe-widget-ticket-tag { font-size: 10px; border-radius: 7px; padding: 4px 7px; }
        .goodwe-widget-ticket-tag.aguardando { color: #b45309; background: rgba(245,158,11,.12); }
        .goodwe-widget-ticket-tag.em_atendimento { color: #2563eb; background: rgba(59,130,246,.13); }
        .goodwe-widget-ticket-tag.resolvido { color: #059669; background: rgba(16,185,129,.12); }
        .goodwe-widget-ticket-tag.outro { color: #71717a; background: rgba(113,113,122,.12); }
        .goodwe-widget-ticket-empty,
        .goodwe-widget-ticket-resolved {
            color: #71717a; font-size: 12px; line-height: 1.6;
            text-align: center; padding: 24px 10px;
        }
        .goodwe-widget-ticket-pages { justify-content: center; flex-shrink: 0; }
        .goodwe-widget-ticket-error {
            color: #b91c1c; background: rgba(229,57,53,.1);
            border-radius: 8px; font-size: 11px; padding: 8px 10px;
            overflow-wrap: anywhere;
        }
        .goodwe-widget-ticket-messages { gap: 12px; padding: 6px 2px; }
        .goodwe-widget-ticket-message { max-width: 88%; align-self: flex-start; }
        .goodwe-widget-ticket-message.mine { align-self: flex-end; }
        .goodwe-widget-ticket-who { font-size: 10px; color: #71717a; margin-bottom: 4px; }
        .goodwe-widget-ticket-message.mine .goodwe-widget-ticket-who { text-align: right; }
        .goodwe-widget-ticket-bubble {
            border-radius: 13px; padding: 9px 11px; background: #eeeeef;
            color: #18181b; font-size: 12px; line-height: 1.5;
            white-space: pre-wrap; word-break: break-word;
        }
        .goodwe-widget-ticket-message:not(.mine) .goodwe-widget-ticket-bubble {
            border: 1px solid rgba(229,57,53,.2); background: rgba(229,57,53,.06);
        }
        .goodwe-widget-ticket-reply { border-top: 1px solid #e4e4e7; padding-top: 9px; flex-shrink: 0; }
        .goodwe-widget-ticket-reply label { color: #71717a; font-size: 11px; display: block; margin-bottom: 7px; }
        .goodwe-widget-ticket-reply textarea {
            width: 100%; display: block; resize: vertical; min-height: 52px; max-height: 94px;
            border-radius: 11px; border: 1px solid #d4d4d8; background: #fff;
            color: #18181b; padding: 9px; font: inherit; font-size: 12px;
        }
        .goodwe-widget-ticket-primary {
            background: #e53935; border: 0; color: #fff;
            padding: 9px 12px; border-radius: 9px; cursor: pointer;
            font-size: 11px; font-weight: 700; margin-top: 7px; width: 100%;
        }
        .goodwe-widget-ticket-primary:hover { background: #c62828; }
        .goodwe-widget-ticket-primary:disabled { opacity: .55; cursor: wait; }
        html.dark .goodwe-widget-tabs { background: rgba(20,22,27,.9); border-color: #343843; }
        html.dark .goodwe-widget-tab { color: #a1a1aa; }
        html.dark .goodwe-widget-tab.active { color: #ef5350; background: rgba(229,57,53,.12); border-color: rgba(229,57,53,.35); }
        html.dark .goodwe-widget-ticket-toolbar strong,
        html.dark .goodwe-widget-ticket-card-title { color: #f7f7f8; }
        html.dark .goodwe-widget-ticket-toolbar p,
        html.dark .goodwe-widget-ticket-card-preview,
        html.dark .goodwe-widget-ticket-card-foot,
        html.dark .goodwe-widget-ticket-who,
        html.dark .goodwe-widget-ticket-empty,
        html.dark .goodwe-widget-ticket-resolved,
        html.dark .goodwe-widget-ticket-reply label { color: #a1a1aa; }
        html.dark .goodwe-widget-ticket-card { background: #1a1a1a; border-color: #343843; }
        html.dark .goodwe-widget-ticket-btn { background: #24262c; border-color: #3a3c43; color: #d4d4d8; }
        html.dark .goodwe-widget-ticket-btn:hover { color: #ef5350; border-color: #e53935; }
        html.dark .goodwe-widget-ticket-tag.aguardando { color: #fbbf24; }
        html.dark .goodwe-widget-ticket-tag.em_atendimento { color: #60a5fa; }
        html.dark .goodwe-widget-ticket-tag.resolvido { color: #34d399; }
        html.dark .goodwe-widget-ticket-bubble { background: #2a2c32; color: #f7f7f8; }
        html.dark .goodwe-widget-ticket-message:not(.mine) .goodwe-widget-ticket-bubble {
            background: rgba(229,57,53,.1); border-color: rgba(229,57,53,.25);
        }
        html.dark .goodwe-widget-ticket-reply { border-color: #343843; }
        html.dark .goodwe-widget-ticket-reply textarea { background: #24262c; color: #f7f7f8; border-color: #3a3c43; }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const launcher = document.getElementById('chatbotLauncher');
            const overlay = document.getElementById('chatbotWidgetOverlay');
            const panel = document.getElementById('chatbotWidget');
            const closeBtn = document.getElementById('chatbotWidgetClose');
            const newBtn = document.getElementById('chatbotWidgetNew');
            const messages = document.getElementById('chatbotWidgetMessages');
            const welcome = document.getElementById('chatbotWidgetWelcome');
            const conversation = document.getElementById('chatbotWidgetConversation');
            const form = document.getElementById('chatbotWidgetForm');
            const input = document.getElementById('chatbotWidgetInput');
            const sendButton = document.getElementById('chatbotWidgetSend');
            const suggestions = document.querySelectorAll('.goodwe-widget-suggestion');
            const postRoute = @json(Auth::user()->isGestor() ? route('manager.chatbot') : route('user.chatbot'));
            const clearRoute = @json(Auth::user()->isGestor() ? route('manager.chatbot.clear') : route('user.chatbot.clear'));
            const csrfToken = form.querySelector('input[name="_token"]').value;
            const ticketTabIA = document.getElementById('goodweWidgetTabIA');
            const ticketTabTickets = document.getElementById('goodweWidgetTabTickets');
            const iaView = document.getElementById('goodweWidgetIAView');
            const ticketsView = document.getElementById('goodweWidgetTicketsView');
            const ticketsList = document.getElementById('goodweWidgetTicketList');
            const ticketsDetail = document.getElementById('goodweWidgetTicketDetail');
            const ticketsItems = document.getElementById('goodweWidgetTicketItems');
            const ticketsError = document.getElementById('goodweWidgetTicketListError');
            const ticketDetailError = document.getElementById('goodweWidgetTicketDetailError');
            const ticketMessages = document.getElementById('goodweWidgetTicketMessages');
            const ticketReplyForm = document.getElementById('goodweWidgetTicketReplyForm');
            const ticketReplyInput = document.getElementById('goodweWidgetTicketReplyInput');
            const ticketReplySend = document.getElementById('goodweWidgetTicketReplySend');
            const ticketIndexRoute = @json(route('tickets.index'));
            const ticketCurrentUser = @json(auth()->id());
            let activeWidgetTab = 'ia';
            let selectedTicketId = null;
            let nextTicketsUrl = null;
            let previousTicketsUrl = null;
            let ticketRequestId = 0;
            let started = false;
            function openWidget() {
                panel.style.display = 'flex';
                overlay.style.display = 'block';
                launcher.classList.add('is-open');
                launcher.setAttribute('aria-expanded', 'true');
                if (activeWidgetTab === 'ia') {
                    input.focus();
                } else if (selectedTicketId !== null) {
                    loadTicket(selectedTicketId);
                } else {
                    loadTickets();
                }
            }
            function closeWidget() {
                panel.style.display = 'none';
                overlay.style.display = 'none';
                launcher.classList.remove('is-open');
                launcher.setAttribute('aria-expanded', 'false');
            }
            launcher.addEventListener('click', function () {
                if (panel.style.display === 'none') {
                    openWidget();
                } else {
                    closeWidget();
                }
            });
            closeBtn.addEventListener('click', closeWidget);
            overlay.addEventListener('click', closeWidget);
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && panel.style.display !== 'none') {
                    closeWidget();
                }
            });
            function startConversation() {
                if (started) {
                    return;
                }
                started = true;
                welcome.style.display = 'none';
                conversation.style.display = 'block';
            }
            function updateSendButton() {
                sendButton.disabled = input.value.trim() === '';
            }
            function resizeInput() {
                input.style.height = 'auto';
                input.style.height = Math.min(input.scrollHeight, 110) + 'px';
            }
            function scrollBottom(animate = true) {
                requestAnimationFrame(function () {
                    messages.scrollTo({
                        top: messages.scrollHeight,
                        behavior: animate ? 'smooth' : 'auto'
                    });
                });
            }
            function addUserMessage(text) {
                const row = document.createElement('div');
                row.className = 'goodwe-widget-row user';
                const bubble = document.createElement('div');
                bubble.className = 'goodwe-widget-bubble';
                bubble.textContent = text;
                row.appendChild(bubble);
                conversation.appendChild(row);
            }
            function addAssistantMessage(text) {
                const row = document.createElement('div');
                row.className = 'goodwe-widget-row assistant';
                const avatar = document.createElement('div');
                avatar.className = 'goodwe-widget-avatar';
                avatar.textContent = 'W';
                const content = document.createElement('div');
                content.className = 'goodwe-widget-reply';
                content.textContent = text;
                row.appendChild(avatar);
                row.appendChild(content);
                conversation.appendChild(row);
            }
            function addLoading() {
                const row = document.createElement('div');
                row.id = 'goodweWidgetLoading';
                row.className = 'goodwe-widget-row assistant';
                const avatar = document.createElement('div');
                avatar.className = 'goodwe-widget-avatar';
                avatar.textContent = 'W';
                const loading = document.createElement('div');
                loading.className = 'goodwe-widget-loading';
                loading.innerHTML = '<span></span><span></span><span></span>';
                row.appendChild(avatar);
                row.appendChild(loading);
                conversation.appendChild(row);
            }
            async function sendMessage(message) {
                const text = message.trim();
                if (!text || input.disabled) {
                    return;
                }
                startConversation();
                addUserMessage(text);
                input.value = '';
                resizeInput();
                updateSendButton();
                input.disabled = true;
                sendButton.disabled = true;
                addLoading();
                scrollBottom();
                try {
                    const response = await fetch(postRoute, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({ message: text })
                    });
                    const data = await response.json();
                    document.getElementById('goodweWidgetLoading')?.remove();
                    addAssistantMessage(
                        data.answer ||
                        @json(__('chatbot.no_answer'))
                    );
                } catch (error) {
                    document.getElementById('goodweWidgetLoading')?.remove();
                    addAssistantMessage(
                        @json(__('chatbot.connection_error'))
                    );
                }
                input.disabled = false;
                input.focus();
                updateSendButton();
                scrollBottom();
            }
            async function startNewConversation() {
                if (input.disabled) {
                    return;
                }
                newBtn.disabled = true;
                try {
                    const response = await fetch(clearRoute, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        }
                    });
                    if (!response.ok) {
                        throw new Error('Unable to clear conversation');
                    }
                    conversation.innerHTML = '';
                    conversation.style.display = 'none';
                    welcome.style.display = 'flex';
                    started = false;
                    input.value = '';
                    input.disabled = false;
                    resizeInput();
                    updateSendButton();
                    messages.scrollTo({ top: 0, behavior: 'auto' });
                    input.focus();
                } catch (error) {
                    alert(@json(
                        app()->getLocale() === 'en'
                            ? 'Could not start a new conversation.'
                            : (app()->getLocale() === 'es'
                                ? 'No fue posible iniciar una nueva conversación.'
                                : 'Não foi possível iniciar uma nova conversa.')
                    ));
                }
                newBtn.disabled = false;
            }
            newBtn.addEventListener('click', startNewConversation);
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                sendMessage(input.value);
            });
            input.addEventListener('input', function () {
                resizeInput();
                updateSendButton();
            });
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    sendMessage(input.value);
                }
            });
            suggestions.forEach(function (button) {
                button.addEventListener('click', function () {
                    sendMessage(button.dataset.question);
                });
            });
            // Tickets do usuário/gestor na mesma janela flutuante.
            function ticketElement(tag, className, value) {
                const el = document.createElement(tag);
                el.className = className;
                el.textContent = value ?? '';
                return el;
            }

            function ticketError(target, message = '') {
                target.textContent = message;
                target.hidden = !message;
            }

            function statusName(status) {
                return ({
                    aguardando: 'Aguardando',
                    em_atendimento: 'Em atendimento',
                    resolvido: 'Resolvido'
                })[status] || status || 'Sem status';
            }

            function statusTag(status) {
                const badge = ticketElement('span', 'goodwe-widget-ticket-tag ' +
                    (['aguardando', 'em_atendimento', 'resolvido'].includes(status) ? status : 'outro'),
                    statusName(status));
                return badge;
            }

            function formattedDate(value) {
                if (!value) return '';
                const date = new Date(value);
                return isNaN(date.getTime()) ? String(value) :
                    date.toLocaleString(document.documentElement.lang || 'pt-BR');
            }

            async function ticketJson(url, options = {}) {
                const response = await fetch(url, {
                    credentials: 'same-origin',
                    ...options,
                    headers: { Accept: 'application/json', ...(options.headers || {}) }
                });
                const result = await response.json().catch(() => ({}));
                if (!response.ok) {
                    throw new Error(result.mensagem || result.message ||
                        'Não foi possível consultar os tickets.');
                }
                return result;
            }

            function switchWidgetTab(name) {
                const isIA = name === 'ia';
                activeWidgetTab = name;
                iaView.hidden = !isIA;
                ticketsView.hidden = isIA;
                ticketTabIA.classList.toggle('active', isIA);
                ticketTabTickets.classList.toggle('active', !isIA);
                ticketTabIA.setAttribute('aria-selected', String(isIA));
                ticketTabTickets.setAttribute('aria-selected', String(!isIA));
                newBtn.hidden = !isIA;
                if (isIA) {
                    input.focus();
                } else if (selectedTicketId !== null && !ticketsDetail.hidden) {
                    loadTicket(selectedTicketId);
                } else {
                    loadTickets();
                }
            }

            function displayTicketSection(view) {
                ticketsList.hidden = view !== 'list';
                ticketsDetail.hidden = view !== 'detail';
            }

            function renderTickets(records) {
                ticketsItems.replaceChildren();
                if (!records.length) {
                    ticketsItems.appendChild(ticketElement('p', 'goodwe-widget-ticket-empty',
                        'Você ainda não possui tickets. Peça atendimento humano no Chat com IA quando precisar.'));
                    return;
                }
                records.forEach(ticket => {
                    const card = ticketElement('article', 'goodwe-widget-ticket-card', '');
                    const head = ticketElement('div', 'goodwe-widget-ticket-card-head', '');
                    head.appendChild(ticketElement('span', 'goodwe-widget-ticket-number', '#' + ticket.id));
                    head.appendChild(statusTag(ticket.status));
                    card.appendChild(head);
                    card.appendChild(ticketElement('h3', 'goodwe-widget-ticket-card-title',
                        ticket.motivo || 'Atendimento humano'));
                    card.appendChild(ticketElement('p', 'goodwe-widget-ticket-card-preview', ticket.mensagem || ''));
                    const foot = ticketElement('div', 'goodwe-widget-ticket-card-foot', '');
                    foot.appendChild(ticketElement('span', '', formattedDate(ticket.created_at)));
                    const action = ticketElement('button', 'goodwe-widget-ticket-btn', 'Ver conversa');
                    action.type = 'button';
                    action.addEventListener('click', () => loadTicket(ticket.id));
                    foot.appendChild(action);
                    card.appendChild(foot);
                    ticketsItems.appendChild(card);
                });
            }

            async function loadTickets(url = ticketIndexRoute) {
                const requestId = ++ticketRequestId;
                ticketError(ticketsError);
                displayTicketSection('list');
                ticketsItems.replaceChildren(ticketElement('p', 'goodwe-widget-ticket-empty', 'Carregando seus tickets...'));
                nextTicketsUrl = null;
                previousTicketsUrl = null;
                document.getElementById('goodweWidgetTicketPrev').hidden = true;
                document.getElementById('goodweWidgetTicketNext').hidden = true;
                try {
                    const data = await ticketJson(url);
                    if (requestId !== ticketRequestId || activeWidgetTab !== 'tickets' || ticketsList.hidden) return;
                    const paginator = data.tickets || {};
                    renderTickets(Array.isArray(paginator.data) ? paginator.data : []);
                    nextTicketsUrl = paginator.next_page_url || null;
                    previousTicketsUrl = paginator.prev_page_url || null;
                    document.getElementById('goodweWidgetTicketPrev').hidden = !previousTicketsUrl;
                    document.getElementById('goodweWidgetTicketNext').hidden = !nextTicketsUrl;
                } catch (error) {
                    if (requestId !== ticketRequestId || activeWidgetTab !== 'tickets' || ticketsList.hidden) return;
                    ticketsItems.replaceChildren();
                    ticketError(ticketsError, error.message);
                }
            }

            function addTicketMessage(who, message, date, mine) {
                const row = ticketElement('div', 'goodwe-widget-ticket-message' + (mine ? ' mine' : ''), '');
                row.appendChild(ticketElement('div', 'goodwe-widget-ticket-who',
                    who + (date ? ' · ' + formattedDate(date) : '')));
                row.appendChild(ticketElement('div', 'goodwe-widget-ticket-bubble', message));
                ticketMessages.appendChild(row);
            }

            async function loadTicket(id) {
                selectedTicketId = id;
                ++ticketRequestId;
                const requestId = ticketRequestId;
                ticketError(ticketDetailError);
                displayTicketSection('detail');
                document.getElementById('goodweWidgetTicketTitle').textContent = 'Ticket #' + id;
                document.getElementById('goodweWidgetTicketStatus').textContent = 'Carregando...';
                ticketMessages.replaceChildren(ticketElement('p', 'goodwe-widget-ticket-empty', 'Carregando conversa...'));
                ticketReplyForm.hidden = true;
                document.getElementById('goodweWidgetTicketResolved').hidden = true;
                try {
                    const data = await ticketJson(ticketIndexRoute.replace(/\/$/, '') + '/' + encodeURIComponent(id));
                    if (requestId !== ticketRequestId || selectedTicketId !== id || ticketsDetail.hidden) return;
                    const ticket = data.ticket;
                    document.getElementById('goodweWidgetTicketTitle').textContent =
                        'Ticket #' + ticket.id + ' — ' + (ticket.motivo || 'Atendimento humano');
                    document.getElementById('goodweWidgetTicketStatus').textContent = 'Status: ' + statusName(ticket.status);
                    ticketMessages.replaceChildren();
                    addTicketMessage('Você (mensagem inicial)',
                        data.mensagem_inicial || ticket.mensagem || '', ticket.created_at, true);
                    (data.mensagens || []).forEach(m => {
                        const mine = Number(m.remetente_id) === Number(ticketCurrentUser);
                        addTicketMessage(mine ? 'Você' : (m.remetente_nome || 'Suporte GoodWe'),
                            m.mensagem || '', m.created_at, mine);
                    });
                    ticketReplyForm.hidden = ticket.status === 'resolvido';
                    document.getElementById('goodweWidgetTicketResolved').hidden = ticket.status !== 'resolvido';
                    ticketMessages.scrollTop = ticketMessages.scrollHeight;
                } catch (error) {
                    if (requestId !== ticketRequestId || ticketsDetail.hidden) return;
                    ticketMessages.replaceChildren();
                    ticketError(ticketDetailError, error.message);
                }
            }

            ticketTabIA.addEventListener('click', () => switchWidgetTab('ia'));
            ticketTabTickets.addEventListener('click', () => switchWidgetTab('tickets'));
            document.getElementById('goodweWidgetTicketRefresh').addEventListener('click', () => loadTickets());
            document.getElementById('goodweWidgetTicketPrev').addEventListener('click', () => {
                if (previousTicketsUrl) loadTickets(previousTicketsUrl);
            });
            document.getElementById('goodweWidgetTicketNext').addEventListener('click', () => {
                if (nextTicketsUrl) loadTickets(nextTicketsUrl);
            });
            document.getElementById('goodweWidgetTicketBack').addEventListener('click', () => {
                selectedTicketId = null;
                loadTickets();
            });
            document.getElementById('goodweWidgetTicketReload').addEventListener('click', () => {
                if (selectedTicketId !== null) loadTicket(selectedTicketId);
            });
            ticketReplyForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (selectedTicketId === null || !ticketReplyInput.value.trim() || ticketReplySend.disabled) return;
                const currentId = selectedTicketId;
                const content = ticketReplyInput.value.trim();
                ticketReplySend.disabled = true;
                ticketReplyInput.disabled = true;
                ticketError(ticketDetailError);
                try {
                    await ticketJson(ticketIndexRoute.replace(/\/$/, '') + '/' +
                        encodeURIComponent(currentId) + '/responder', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body: JSON.stringify({ mensagem: content })
                    });
                    ticketReplyInput.value = '';
                    if (selectedTicketId === currentId) await loadTicket(currentId);
                } catch (error) {
                    ticketError(ticketDetailError, error.message);
                } finally {
                    ticketReplySend.disabled = false;
                    ticketReplyInput.disabled = false;
                }
            });
            resizeInput();
            updateSendButton();
        });
    </script>
    @endunless
    <script src="{{ asset('js/theme.js') }}"></script>
</body>
</html>
