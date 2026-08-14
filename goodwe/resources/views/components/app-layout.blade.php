<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Laravel') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] dark:text-[#EDEDEC] min-h-screen antialiased">
        <header class="border-b border-[#e3e3e0] dark:border-[#3E3E3A]">
            <nav class="max-w-4xl mx-auto px-6 py-4 flex items-center justify-between">
                <a href="{{ route('chargers.index') }}" class="font-medium">ChargeGrid Intelligence</a>
                <span class="text-sm text-[#706f6c] dark:text-[#A1A09A]">Simulação de recarga</span>
            </nav>
        </header>

        <main class="max-w-4xl mx-auto px-6 py-8">
            @if (session('success'))
                <div class="mb-6 px-4 py-3 rounded-sm border border-green-600/30 bg-green-600/10 text-green-700 dark:text-green-400 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-6 px-4 py-3 rounded-sm border border-red-600/30 bg-red-600/10 text-red-700 dark:text-red-400 text-sm">
                    {{ session('error') }}
                </div>
            @endif

            {{ $slot }}
        </main>
    </body>
</html>