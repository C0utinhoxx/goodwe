<x-manager-legacy-layout>
    <x-slot name="header"><div><p class="mb-2 text-sm font-semibold text-[#c62828] dark:text-red-300">ESTABELECIMENTO</p><h1 class="text-3xl font-semibold">{{ $establishment ? 'Meu Estabelecimento' : 'Cadastre seu estabelecimento' }}</h1><p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $establishment ? 'Dados e regras utilizados por todos os seus carregadores.' : 'Vincule seu único estabelecimento para começar a administrar a operação.' }}</p></div></x-slot>
    <form method="POST" action="{{ $establishment ? route('manager.establishment.update') : route('manager.establishment.store') }}" data-manager-confirm="Confirmar os dados e as configurações? Durante um pico ativo, a tarifa será recalculada a partir da nova base e do percentual informado. Reservas e sessões anteriores mantêm suas regras." class="gw-form space-y-6">
        @csrf @if($establishment) @method('PATCH') @endif
        <p class="pt-muted">Preencha os dados abaixo. Campos indicados como opcionais podem ficar em branco.</p>
        <section id="dados-estabelecimento" class="gw-card rounded-xl border border-gray-200 bg-white p-5 sm:p-7 dark:border-[#343843] dark:bg-[#1a1a1a]">
            <h2 class="mb-5 text-lg font-semibold"><span class="gw-section-number">01</span>Informações do estabelecimento</h2>
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <x-manager-field name="name" label="Nome do estabelecimento" :value="$establishment?->name" maxlength="255" autocomplete="organization" />
                <x-manager-field name="cnpj" label="CNPJ" :value="$establishment?->cnpj" maxlength="18" placeholder="Ex.: 11.222.333/0001-81" />
                <div class="md:col-span-2"><x-manager-field name="address" label="Endereço completo" :value="$establishment?->address" maxlength="255" autocomplete="street-address" /></div>
                <x-manager-field name="phone" label="Telefone comercial" type="tel" :value="$establishment?->phone" maxlength="20" autocomplete="tel" />
                <x-manager-field name="operating_hours" label="Horário de funcionamento" :value="$establishment?->operating_hours" maxlength="255" placeholder="Informe os dias e horários" />
                <label class="gw-field flex min-w-0 flex-col gap-2 text-sm font-medium">Tipo de estabelecimento<select name="type" required class="w-full min-w-0 rounded-lg border border-gray-300 bg-gray-50 px-3 py-3 dark:border-[#343843] dark:bg-[#181b22]"><option value="">Selecione</option>@foreach(['residencial'=>'Residencial','comercial'=>'Comercial','shopping'=>'Shopping','outro'=>'Outro'] as $type=>$label)<option value="{{ $type }}" @selected(old('type',$establishment?->type)===$type)>{{ $label }}</option>@endforeach</select>@error('type')<span class="text-[#c62828] dark:text-red-300">{{ $message }}</span>@enderror</label>
                <div class="rounded-lg bg-gray-50 p-4 dark:bg-[#181b22]"><p class="text-xs text-gray-500 dark:text-gray-400">Gestor responsável</p><p class="mt-1 font-semibold">{{ Auth::user()->name }}</p><p class="mt-1 break-all text-sm text-gray-500 dark:text-gray-400">{{ Auth::user()->email }}</p></div>
            </div>
        </section>
        <section id="configurar-tarifas" class="gw-card rounded-xl border border-gray-200 bg-white p-5 sm:p-7 dark:border-[#343843] dark:bg-[#1a1a1a]">
            <h2 class="mb-2 text-lg font-semibold"><span class="gw-section-number">02</span>Tarifa e demanda</h2><p class="mb-5 text-sm text-gray-500 dark:text-gray-400">Defina o valor do kWh e como os reajustes por demanda serão aprovados. A tarifa é a mesma para todos os seus carregadores.</p>
            @if($establishment)<p class="mb-5 rounded-lg bg-gray-50 p-4 text-sm dark:bg-[#181b22]">Tarifa vigente: <strong>R$ {{ number_format((float)$establishment->currentTariff(),4,',','.') }}/kWh</strong></p>@endif
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <x-manager-field name="cost_per_kwh" label="Tarifa-base (R$/kWh)" type="number" :value="$establishment?->cost_per_kwh" min="0.0001" max="9999.9999" step="0.0001" />
                <label class="gw-field flex min-w-0 flex-col gap-2 text-sm font-medium">Modo de tarifação<select name="pricing_mode" required class="w-full min-w-0 rounded-lg border border-gray-300 bg-gray-50 px-3 py-3 dark:border-[#343843] dark:bg-[#181b22]"><option value="">Selecione</option><option value="manual" @selected(old('pricing_mode',$establishment?->pricing_mode)==='manual')>Manual — aprovar cada reajuste</option><option value="automatic" @selected(old('pricing_mode',$establishment?->pricing_mode)==='automatic')>Automático — aplicar o percentual configurado</option></select>@error('pricing_mode')<span class="text-[#c62828] dark:text-red-300">{{ $message }}</span>@enderror</label>
                <x-manager-field name="surge_percentage" label="Reajuste em períodos de pico (%)" type="number" :value="$establishment?->surge_percentage" min="0" max="99999.99" step="0.01" />
            </div>
            <p class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-700/40 dark:bg-amber-500/10 dark:text-amber-200">A detecção de picos depende da definição dos critérios de demanda e da integração do detector. A escolha do modo automático não inicia reajustes por conta própria.</p>
        </section>
        <section id="regras-reserva" class="gw-card rounded-xl border border-gray-200 bg-white p-5 sm:p-7 dark:border-[#343843] dark:bg-[#1a1a1a]">
            <h2 class="mb-2 text-lg font-semibold"><span class="gw-section-number">03</span>Reservas imediatas</h2><p class="mb-5 text-sm text-gray-500 dark:text-gray-400">O cancelamento voluntário é gratuito. A penalidade por ausência é limitada aos pontos disponíveis do usuário.</p>
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2"><x-manager-field name="reservation_tolerance_minutes" label="Tempo de tolerância (minutos)" type="number" :value="$establishment?->reservation_tolerance_minutes" min="1" step="1" /><x-manager-field name="no_show_penalty_points" label="Penalidade por ausência (pontos)" type="number" :value="$establishment?->no_show_penalty_points" min="0" step="0.01" /></div>
            <p class="mt-5 text-sm text-gray-500 dark:text-gray-400">Informe zero para não descontar pontos por ausência. Alterações se aplicam às novas reservas.</p>
        </section>
        <div class="gw-form-actions">@if($establishment)<a href="{{ route('manager.dashboard') }}" class="gw-button gw-button-secondary rounded-lg border border-gray-300 px-5 py-3 text-sm dark:border-[#343843]">Voltar</a>@endif<button type="submit" class="gw-button gw-button-primary rounded-lg bg-[#c62828] px-6 py-3 text-sm font-semibold text-white hover:bg-[#b71c1c]">{{ $establishment ? 'Salvar alterações' : 'Cadastrar estabelecimento' }}</button></div>
    </form>
</x-manager-legacy-layout>
