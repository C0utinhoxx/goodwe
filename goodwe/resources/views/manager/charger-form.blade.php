<x-manager-legacy-layout>
    <x-slot name="header"><div><p class="mb-2 text-sm font-semibold text-[#c62828] dark:text-red-300">EQUIPAMENTOS</p><h1 class="text-3xl font-semibold">{{ $charger ? 'Editar carregador' : 'Adicionar carregador' }}</h1><p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ Auth::user()->establishment->name }}</p></div></x-slot>
    <form method="POST" action="{{ $charger ? route('manager.chargers.update',$charger) : route('manager.chargers.store') }}" data-manager-confirm="Confirmar o cadastro administrativo? O estado físico e a ativação dependem da integração do equipamento." class="gw-form gw-card rounded-xl border border-gray-200 bg-white p-5 sm:p-7 dark:border-[#343843] dark:bg-[#1a1a1a]">
        @csrf @if($charger) @method('PATCH') @endif
        <div class="mb-6 rounded-lg bg-gray-50 p-4 text-sm text-gray-600 dark:bg-[#181b22] dark:text-gray-300">A tarifa é atrelada a cada carregador. Deixe o campo de tarifa vazio para herdar a tarifa-base do estabelecimento, ou informe um valor próprio para este equipamento. Cadastrar um identificador não comprova a posse nem ativa fisicamente o equipamento.</div>
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
            <x-manager-field name="name" label="Nome do carregador" :value="$charger?->name" maxlength="255" />
            @if($charger)<div class="rounded-lg bg-gray-50 p-4 dark:bg-[#181b22]"><p class="text-sm text-gray-500 dark:text-gray-400">Identificador do equipamento</p><p class="mt-1 break-all font-mono">{{ $charger->code }}</p><p class="mt-2 text-xs text-gray-500 dark:text-gray-400">A identidade do equipamento é preservada após o cadastro.</p></div>@else<x-manager-field name="code" label="Identificador do equipamento" maxlength="255" autocomplete="off" />@endif
            <div class="md:col-span-2"><x-manager-field name="location" label="Localização no estabelecimento" :value="$charger?->location" maxlength="255" /></div>
            <x-manager-field name="power_kw" label="Potência (kW)" type="number" :value="$charger?->power_kw" min="0.01" max="999999.99" step="0.01" />
            <x-manager-field name="connector" label="Conector" :value="$charger?->connector" maxlength="255" />
            <x-manager-field name="tariff_override" label="Tarifa do carregador (R$/kWh)" type="number" :value="$charger?->tariff_override" :required="false" min="0" max="9999.9999" step="0.0001" placeholder="Vazio = herda do estabelecimento" />
            <div class="rounded-lg bg-gray-50 p-4 dark:bg-[#181b22]"><p class="text-sm text-gray-500 dark:text-gray-400">Tarifa vigente deste carregador</p><p class="mt-1 font-semibold">R$ {{ number_format((float) ($charger?->currentTariff() ?? 0), 4, ',', '.') }}/kWh</p><p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Calculada a partir da tarifa própria ou da tarifa-base do estabelecimento.</p></div>
            <x-manager-field name="lat" label="Latitude" type="number" :value="$charger?->lat" :required="false" min="-90" max="90" step="any" />
            <x-manager-field name="lng" label="Longitude" type="number" :value="$charger?->lng" :required="false" min="-180" max="180" step="any" />
        </div>
        <div class="gw-form-actions"><a href="{{ route('manager.chargers.index') }}" class="gw-button gw-button-secondary rounded-lg border border-gray-300 px-5 py-3 text-sm dark:border-[#343843]">Voltar</a><button type="submit" class="gw-button gw-button-primary rounded-lg bg-[#c62828] px-6 py-3 text-sm font-semibold text-white hover:bg-[#b71c1c]">{{ $charger ? 'Salvar carregador' : 'Cadastrar carregador' }}</button></div>
    </form>
</x-manager-legacy-layout>
