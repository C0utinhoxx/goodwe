<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carregar - Totem {{ $totemId }}</title>
    @vite(['resources/css/app.css'])
</head>
{{-- Tela de recarga do PowerTAG: dados do usuário vêm do Firestore --}}
<body class="bg-gradient-to-b from-gray-100 to-gray-200 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl p-8 max-w-md w-full space-y-6">

        {{-- Cabeçalho com totem e indicador de status (campo comando_web) --}}
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">PowerTAG</p>
                <h1 class="text-2xl font-bold text-gray-900">Totem {{ $totemId }}</h1>
            </div>

                {{-- Badge de status dinâmica --}}
            <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusBadge }}">
                <span class="h-2 w-2 rounded-full {{ $statusDot }}"></span>
                {{ $statusLabel }}
            </span>
        </div>

        {{-- Mensagens de sucesso/erro --}}
        @if (session('success'))
            <div class="flex items-center gap-2 bg-emerald-50 border border-emerald-300 text-emerald-800 px-4 py-3 rounded-lg text-sm" role="status">
                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="flex items-center gap-2 bg-red-50 border border-red-300 text-red-800 px-4 py-3 rounded-lg text-sm" role="alert">
                <span class="h-2 w-2 rounded-full bg-red-500"></span>
                {{ session('error') }}
            </div>
        @endif

        {{-- Usuário autenticado: nome, id e TAG no Firestore --}}
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 space-y-3">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-blue-600 text-white font-bold">
                    {{ strtoupper(substr($userName, 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <p class="truncate font-semibold text-gray-900">{{ $userName }}</p>
                    <p class="text-xs text-gray-500">ID {{ $userId }} &middot; TAG {{ $tagUid }}</p>
                </div>
            </div>

            <div class="border-t border-gray-200 pt-3">
                <p class="text-sm text-gray-500">Saldo disponível (Firestore)</p>
                <p class="text-3xl font-bold {{ $saldo > 0 ? 'text-emerald-600' : 'text-red-600' }}">
                    R$ {{ number_format($saldo, 2, ',', '.') }}
                </p>
                <p class="mt-1 text-xs text-gray-400">Tarifa: R$ {{ number_format($tarifaKwh, 2, ',', '.') }}/kWh</p>
            </div>
        </div>

        {{-- Ações de controle do totem --}}
        <div class="space-y-3">
            {{-- Primário: envia comando_web = iniciar --}}
            <form action="{{ route('totens.iniciar', $totemId) }}" method="POST">
                @csrf
                <button
                    type="submit"
                    @disabled($saldo <= 0)
                    class="w-full inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-bold py-3 px-4 rounded-lg transition shadow-sm"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653v12.694m0 0a3.75 3.75 0 107.5 0m-7.5 0a3.75 3.75 0 007.5 0m0 0V5.653m0 0L13.5 3.75m3 1.903L18.75 3.75" />
                    </svg>
                    Iniciar Carga
                </button>
            </form>

            @if ($saldo <= 0)
                <p class="text-center text-xs font-medium text-red-600">
                    Saldo insuficiente para iniciar a recarga.
                </p>
            @endif

            {{-- Secundário: envia comando_web = parar --}}
            <form action="{{ route('totens.parar', $totemId) }}" method="POST">
                @csrf
                <button
                    type="submit"
                    class="w-full inline-flex items-center justify-center gap-2 bg-white border border-red-500 text-red-600 hover:bg-red-50 font-bold py-3 px-4 rounded-lg transition"
                >
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                        <rect x="6" y="6" width="12" height="12" rx="2" />
                    </svg>
                    Parar Carga
                </button>
            </form>
        </div>

        <p class="text-center text-xs text-gray-400">
            Comando enviado ao totem via Firebase Firestore
            <span class="font-mono">totems/{{ $totemId }}</span>
        </p>
    </div>
</body>
</html>
