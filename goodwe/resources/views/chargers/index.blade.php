<x-app-layout>
    <div class="flex items-end justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold">Carregadores</h1>
            <p class="text-sm text-[#706f6c] dark:text-[#A1A09A] mt-1">
                {{ count($chargers) }} pontos disponíveis na rede
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        @foreach ($chargers as $charger)
            <div class="rounded-lg border border-[#e3e3e0] dark:border-[#3E3E3A] p-5 bg-white dark:bg-[#161615]">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-medium">{{ $charger->name }}</h2>
                        <p class="text-sm text-[#706f6c] dark:text-[#A1A09A]">{{ $charger->location }}</p>
                    </div>

                    @if ($charger->isAvailable())
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-green-700 dark:text-green-400">
                            <span class="w-2 h-2 rounded-full bg-green-500"></span>
                            Livre
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-red-700 dark:text-red-400">
                            <span class="w-2 h-2 rounded-full bg-red-500"></span>
                            Ocupado
                        </span>
                    @endif
                </div>

                <div class="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-sm text-[#706f6c] dark:text-[#A1A09A]">
                    <span>{{ $charger->powerKw }} kW · {{ $charger->connector }}</span>
                </div>

                @if ($charger->isAvailable())
                    <form method="POST" action="{{ route('chargers.schedule', $charger->id) }}" class="mt-4 flex flex-col sm:flex-row gap-2">
                        @csrf
                        <input
                            type="text"
                            name="vehicle_plate"
                            placeholder="Placa do veículo"
                            required
                            maxlength="8"
                            class="rounded-sm border border-[#e3e3e0] dark:border-[#3E3E3A] bg-transparent px-3 py-2 text-sm"
                        >
                        <select
                            name="duration_minutes"
                            class="rounded-sm border border-[#e3e3e0] dark:border-[#3E3E3A] bg-transparent px-3 py-2 text-sm"
                        >
                            <option value="15">15 min</option>
                            <option value="30" selected>30 min</option>
                            <option value="60">60 min</option>
                        </select>
                        <button
                            type="submit"
                            class="rounded-sm bg-[#1b1b18] dark:bg-white px-4 py-2 text-sm font-medium text-white dark:text-[#1b1b18]"
                        >
                            Agendar
                        </button>
                    </form>
                @else
                    <div class="mt-4 flex items-center justify-between gap-3">
                        <p class="text-sm text-[#706f6c] dark:text-[#A1A09A]">
                            Ocupado por <span class="text-[#1b1b18] dark:text-[#EDEDEC]">{{ $charger->vehiclePlate }}</span>
                            · libera em ~{{ $charger->availableInMinutes() }} min
                        </p>
                        <form method="POST" action="{{ route('chargers.release', $charger->id) }}">
                            @csrf
                            <button type="submit" class="text-sm underline underline-offset-4">Liberar (simular)</button>
                        </form>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</x-app-layout>