@php
    // Apresentação do status seguindo a paleta da seção de recarga:
    // vermelho da marca = ativo/ao vivo, âmbar = aguardando, cinza = inativo.
    // Definido antes do layout porque o <x-slot name="header"> é renderizado
    // antes do conteúdo principal.
    $statuses = [
        'iniciar' => [
            'label' => __('totens.status_charging'),
            'pill' => 'border-[#e53935]/30 bg-[#e53935]/5',
            'text' => 'text-[#e53935]',
            'dot' => 'bg-[#e53935]',
            'ping' => true,
        ],
        'espera' => [
            'label' => __('totens.status_waiting'),
            'pill' => 'border-amber-500/30 bg-amber-500/10',
            'text' => 'text-amber-500 dark:text-amber-400',
            'dot' => 'bg-amber-500',
            'ping' => false,
        ],
        'parar' => [
            'label' => __('totens.status_stopped'),
            'pill' => 'border-gray-200 bg-gray-100 dark:border-[#343843] dark:bg-[#343843]',
            'text' => 'text-gray-500 dark:text-[#a0a0a0]',
            'dot' => 'bg-gray-400 dark:bg-[#a0a0a0]',
            'ping' => false,
        ],
    ];

    $status = $statuses[$statusKey] ?? [
        'label' => __('totens.status_offline'),
        'pill' => 'border-gray-200 bg-gray-100 dark:border-[#343843] dark:bg-[#343843]',
        'text' => 'text-gray-500 dark:text-[#a0a0a0]',
        'dot' => 'bg-gray-400 dark:bg-[#a0a0a0]',
        'ping' => false,
    ];
@endphp

<x-user-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    {{-- Volta para a seção de recarga (Carregadores) --}}
                    <a
                        href="{{ route('chargers.index') }}"
                        title="{{ __('totens.back_to_chargers') }}"
                        class="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-gray-50 text-gray-500 transition-all hover:border-[#e53935]/40 hover:text-[#e53935] dark:border-[#343843] dark:bg-[#181b22] dark:text-[#a0a0a0] dark:hover:text-[#e53935]"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                        </svg>
                    </a>

                    <h1 class="text-3xl font-semibold text-gray-900 dark:text-white">
                        {{ __('totens.title') }}
                    </h1>
                </div>

                <p class="mt-2 text-sm text-gray-500 dark:text-[#a0a0a0]">
                    {{ __('totens.subtitle') }} &bull;
                    <span class="font-mono text-gray-400 dark:text-[#8a8f98]">{{ $totemId }}</span>
                </p>
            </div>

            {{-- Status em tempo real (campo comando_web no Firestore) --}}
            <div class="flex items-center gap-2 rounded-full border px-3 py-1.5 {{ $status['pill'] }}">
                <span class="relative flex h-2.5 w-2.5 {{ $status['dot'] }}">
                    @if ($status['ping'])
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75 {{ $status['dot'] }}"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 {{ $status['dot'] }}"></span>
                    @endif
                </span>
                <span class="text-xs font-medium {{ $status['text'] }}">{{ $status['label'] }}</span>
            </div>
        </div>
    </x-slot>

    <section class="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-2">
        {{-- Sessão de recarga: usuário, saldo e tarifa --}}
        <div class="relative overflow-hidden rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-[#343843] dark:bg-[#1a1a1a]">
            <div class="absolute inset-0 rounded-lg bg-gradient-to-br from-[#e53935]/5 to-transparent opacity-0 transition-opacity duration-300 pointer-events-none"></div>

            <div class="relative flex items-start justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-[#e53935]/10 text-sm font-semibold text-[#e53935]">
                        {{ strtoupper(mb_substr($userName, 0, 1)) }}
                    </div>

                    <div>
                        <h2 class="font-medium text-gray-900 dark:text-white">{{ $userName }}</h2>
                        <p class="text-xs text-gray-500 dark:text-[#a0a0a0]">
                            {{ __('totens.user_label') }} &bull; {{ __('totens.user_id') }}: {{ $userId }}
                        </p>
                        <p class="mt-0.5 font-mono text-[11px] text-gray-400 dark:text-[#8a8f98]">
                            {{ __('totens.tag') }}: {{ $tagUid }}
                        </p>
                    </div>
                </div>

                <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium {{ $status['pill'] }} {{ $status['text'] }}">
                    {{ __('chargers.live_status') }}
                </span>
            </div>

            <div class="relative mt-4 flex items-center justify-between rounded-md bg-gray-50 px-3 py-2 dark:bg-[#181b22]">
                <span class="text-xs text-gray-500 dark:text-[#a0a0a0]">{{ __('totens.balance') }}</span>
                <span class="text-sm font-semibold text-gray-900 dark:text-white {{ $saldo > 0 ? '' : 'text-[#e53935]' }}">
                    R$ {{ number_format($saldo, 2, ',', '.') }}
                </span>
            </div>

            <div class="relative mt-2 flex items-center justify-between rounded-md bg-gray-50 px-3 py-2 dark:bg-[#181b22]">
                <span class="text-xs text-gray-500 dark:text-[#a0a0a0]">{{ __('totens.tariff') }}</span>
                <span class="text-sm font-semibold text-gray-900 dark:text-white">
                    R$ {{ number_format($tarifaKwh, 2, ',', '.') }}
                    <span class="text-xs font-normal text-gray-400">{{ __('totens.per_kwh') }}</span>
                </span>
            </div>
        </div>

        {{-- Controle remoto do totem --}}
        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-[#343843] dark:bg-[#1a1a1a]">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                {{ __('totens.controls_title') }}
            </h2>

            <div class="mt-4 space-y-3">
                {{-- Inicia a recarga (comando_web = iniciar) --}}
                <form method="POST" action="{{ route('totens.iniciar', $totemId) }}">
                    @csrf
                    <button
                        type="submit"
                        @disabled($saldo <= 0)
                        class="w-full rounded-lg bg-[#e53935] px-5 py-3 text-sm font-semibold text-white transition-all hover:bg-[#c62828] hover:shadow-[0_0_15px_rgba(229,57,53,0.3)] active:scale-[0.98] disabled:cursor-not-allowed disabled:bg-gray-300 disabled:shadow-none dark:disabled:bg-[#343843]"
                    >
                        <span class="inline-flex items-center justify-center gap-2">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                            </svg>
                            {{ __('totens.start_button') }}
                        </span>
                    </button>
                </form>

                @if ($saldo <= 0)
                    <p class="text-xs font-medium text-[#e53935]">
                        {{ __('totens.no_balance') }}
                    </p>
                @endif

                {{-- Interrompe a recarga (comando_web = parar) --}}
                <form method="POST" action="{{ route('totens.parar', $totemId) }}">
                    @csrf
                    <button
                        type="submit"
                        class="w-full rounded-lg border border-[#e53935] px-5 py-3 text-sm font-semibold text-[#e53935] transition-all hover:bg-[#e53935]/10 active:scale-[0.98]"
                    >
                        <span class="inline-flex items-center justify-center gap-2">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653v12.694m0 0a3.75 3.75 0 107.5 0m-7.5 0a3.75 3.75 0 007.5 0m0 0V5.653m0 0L13.5 3.75m3 1.903L18.75 3.75" />
                            </svg>
                            {{ __('totens.stop_button') }}
                        </span>
                    </button>
                </form>
            </div>

            <div class="mt-5 space-y-1 rounded-md bg-gray-50 px-3 py-3 text-[11px] text-gray-500 dark:bg-[#181b22] dark:text-[#a0a0a0]">
                <p class="font-mono">{{ __('totens.channel_hint', ['tag' => $tagUid]) }}</p>
                <p class="font-mono">{{ __('totens.mirror_hint', ['id' => $totemId]) }}</p>
            </div>
        </div>
    </section>

    <div class="mt-8 text-center">
        <p class="mb-1 text-sm text-gray-400 dark:text-[#b0b4bc]">{{ __('app.footer_tagline') }}</p>
        <span class="text-xs text-gray-400 dark:text-[#8a8f98]">{{ __('app.footer_copyright') }}</span>
    </div>

    {{-- ============================================================
        MODAL DE AUTENTICAÇÃO PIN
        ============================================================
        Exibida automaticamente quando o ESP32 altera o comando_web
        para "aguardando_pin". O utilizador digita os 4 dígitos do PIN
        gerado pelo totem e envia via AJAX para validação.
        ============================================================ --}}
    <div
        id="pin-modal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm"
        aria-labelledby="pin-modal-title"
        role="dialog"
        aria-modal="true"
    >
        <div class="relative mx-4 w-full max-w-sm rounded-2xl border border-gray-200 bg-white p-6 shadow-2xl dark:border-[#343843] dark:bg-[#1a1a1a]">
            <!-- Ícone de segurança -->
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-[#e53935]/10">
                <svg class="h-7 w-7 text-[#e53935]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                </svg>
            </div>

            <!-- Título e descrição -->
            <h3 id="pin-modal-title" class="text-center text-lg font-semibold text-gray-900 dark:text-white">
                {{ __('totens.pin_modal_title') }}
            </h3>
            <p class="mt-2 text-center text-sm text-gray-500 dark:text-[#a0a0a0]">
                {{ __('totens.pin_modal_description') }}
            </p>

            <!-- Formulário de PIN -->
            <form id="pin-form" class="mt-5" method="POST">
                @csrf
                <input type="hidden" name="totem_id" value="{{ $totemId }}">

                <!-- Campo de PIN (4 dígitos) -->
                <div class="relative">
                    <input
                        type="text"
                        id="pin-input"
                        name="pin"
                        inputmode="numeric"
                        pattern="\d{4}"
                        maxlength="4"
                        autocomplete="off"
                        placeholder="____"
                        class="w-full rounded-lg border border-gray-300 bg-gray-50 px-4 py-3 text-center text-2xl font-mono tracking-[0.5em] text-gray-900 placeholder-gray-300 transition-colors focus:border-[#e53935] focus:outline-none focus:ring-2 focus:ring-[#e53935]/20 dark:border-[#343843] dark:bg-[#181b22] dark:text-white dark:placeholder-gray-600"
                        required
                    >
                </div>

                <!-- Mensagem de erro (oculta por padrão) -->
                <p id="pin-error" class="mt-2 hidden text-center text-xs font-medium text-[#e53935]"></p>

                <!-- Botões de ação -->
                <div class="mt-5 flex gap-3">
                    <button
                        type="button"
                        id="pin-cancel"
                        class="flex-1 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-[#343843] dark:text-gray-300 dark:hover:bg-[#181b22]"
                    >
                        {{ __('totens.pin_cancel') }}
                    </button>
                    <button
                        type="submit"
                        id="pin-submit"
                        class="flex-1 rounded-lg bg-[#e53935] px-4 py-2.5 text-sm font-semibold text-white transition-all hover:bg-[#c62828] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {{ __('totens.pin_confirm') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ============================================================
        SCRIPT DE MONITORIZAÇÃO E CONTROLO DA MODAL
        ============================================================
        Faz polling a cada 2 segundos ao endpoint de status do totem.
        Quando o ESP32 altera comando_web para "aguardando_pin", abre
        automaticamente a modal para o utilizador digitar o PIN.
        ============================================================ --}}
    <script>
        (function() {
            // Configuração
            const TOTEM_ID = @json($totemId);
            const POLLING_INTERVAL = 2000; // 2 segundos
            const STATUS_URL = @json(route('totens.status', $totemId));
            const PIN_URL = @json(route('totens.pin', $totemId));

            // Elementos do DOM
            const modal = document.getElementById('pin-modal');
            const form = document.getElementById('pin-form');
            const pinInput = document.getElementById('pin-input');
            const pinError = document.getElementById('pin-error');
            const pinSubmit = document.getElementById('pin-submit');
            const pinCancel = document.getElementById('pin-cancel');

            // Estado interno
            let pollingInterval = null;
            let isModalOpen = false;
            let lastComando = '';

            /**
             * Abre a modal de PIN e foca no campo de input.
             */
            function openModal() {
                if (isModalOpen) return;

                isModalOpen = true;
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                pinInput.value = '';
                pinError.classList.add('hidden');
                pinSubmit.disabled = false;

                // Foca no campo após a animação de abertura
                setTimeout(() => pinInput.focus(), 100);
            }

            /**
             * Fecha a modal de PIN.
             */
            function closeModal() {
                isModalOpen = false;
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }

            /**
             * Consulta o estado atual do totem via AJAX.
             * Se comando_web === 'aguardando_pin', abre a modal.
             * Se comando_web volta para 'espera' ou 'carregando', fecha a modal.
             */
            async function checkStatus() {
                try {
                    const response = await fetch(STATUS_URL, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) return;

                    const data = await response.json();
                    if (!data.success) return;

                    const comando = data.comando_web;

                    // Deteta transição para "aguardando_pin"
                    if (comando === 'aguardando_pin' && lastComando !== 'aguardando_pin') {
                        openModal();
                    }

                    // Deteta fim da sessão PIN (carregamento iniciado ou cancelado)
                    if ((comando === 'carregando' || comando === 'espera' || comando === 'iniciar') && lastComando === 'aguardando_pin') {
                        closeModal();
                    }

                    lastComando = comando;
                } catch (e) {
                    // Silencioso: falhas de rede não devem interromper o polling
                    console.warn('Falha ao consultar status do totem:', e);
                }
            }

            /**
             * Envia o PIN digitado para o Laravel via AJAX.
             */
            async function submitPin(pin) {
                pinSubmit.disabled = true;
                pinError.classList.add('hidden');

                try {
                    const response = await fetch(PIN_URL, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': @json(csrf_token()),
                        },
                        body: JSON.stringify({ pin }),
                    });

                    const data = await response.json();

                    if (response.ok && data.success) {
                        // PIN enviado com sucesso - aguarda o ESP32 validar
                        pinInput.value = '';
                        pinError.classList.add('hidden');
                    } else {
                        // Mostra erro de validação
                        pinError.textContent = data.message || 'PIN inválido. Tente novamente.';
                        pinError.classList.remove('hidden');
                        pinInput.select();
                    }
                } catch (e) {
                    pinError.textContent = 'Erro de conexão. Tente novamente.';
                    pinError.classList.remove('hidden');
                } finally {
                    pinSubmit.disabled = false;
                }
            }

            // ============================================================
            // Event Listeners
            // ============================================================

            // Submissão do formulário de PIN
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const pin = pinInput.value.trim();

                if (!/^\d{4}$/.test(pin)) {
                    pinError.textContent = 'O PIN deve ter exatamente 4 dígitos.';
                    pinError.classList.remove('hidden');
                    pinInput.focus();
                    return;
                }

                submitPin(pin);
            });

            // Botão cancelar
            pinCancel.addEventListener('click', closeModal);

            // Fechar modal ao clicar fora dela
            modal.addEventListener('click', function(e) {
                if (e.target === modal) closeModal();
            });

            // Fechar modal com tecla Escape
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && isModalOpen) closeModal();
            });

            // Permitir apenas dígitos no campo de PIN
            pinInput.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '').slice(0, 4);
            });

            // ============================================================
            // Início do polling
            // ============================================================
            pollingInterval = setInterval(checkStatus, POLLING_INTERVAL);

            // Primeira consulta imediata
            checkStatus();
        })();
    </script>
</x-user-layout>
