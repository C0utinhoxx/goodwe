<x-manager-legacy-layout>
    <x-slot name="header"><div><p class="mb-2 text-sm font-semibold text-[#c62828] dark:text-red-300">{{ __('bulk.estabelecimento') }}</p><h1 class="text-3xl font-semibold">{{ $establishment ? __('review.estab_title') : __('review.estab_create') }}</h1><p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $establishment ? __('review.estab_subtitle') : __('review.estab_create_subtitle') }}</p></div></x-slot>
    <form method="POST" action="{{ $establishment ? route('manager.establishment.update') : route('manager.establishment.store') }}" data-manager-confirm="{{ __('review.confirm_form') }}" class="gw-form space-y-6">
        @csrf @if($establishment) @method('PATCH') @endif
        <p class="pt-muted">{{ __('bulk.preencha_os_dados_abaixo_campos_indicados_como_opcionais_podem_ficar_em_branco') }}</p>
        <section id="dados-estabelecimento" class="gw-card rounded-xl border border-gray-200 bg-white p-5 sm:p-7 dark:border-[#343843] dark:bg-[#1a1a1a]">
            <h2 class="mb-5 text-lg font-semibold"><span class="gw-section-number">01</span>{{ __('bulk.informacoes_do_estabelecimento') }}</h2>
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <x-manager-field name="name" :label="__('review.estab_name')" :value="$establishment?->name" maxlength="255" autocomplete="organization" />
                <x-manager-field name="cnpj" label="CNPJ" :value="$establishment?->cnpj" maxlength="18" placeholder="Ex.: 11.222.333/0001-81" />
                <div class="md:col-span-2"><x-manager-field name="address" :label="__('review.estab_address')" :value="$establishment?->address" maxlength="255" autocomplete="street-address" /></div>
                <x-manager-field name="phone" :label="__('review.estab_phone')" type="tel" :value="$establishment?->phone" maxlength="20" autocomplete="tel" />
                <x-manager-field name="operating_hours" :label="__('review.estab_hours')" :value="$establishment?->operating_hours" maxlength="255" :placeholder="__('review.estab_hours_placeholder')" />
                <label class="gw-field flex min-w-0 flex-col gap-2 text-sm font-medium">{{ __('bulk.tipo_de_estabelecimento') }}<select name="type" required class="w-full min-w-0 rounded-lg border border-gray-300 bg-gray-50 px-3 py-3 dark:border-[#343843] dark:bg-[#181b22]"><option value="">{{ __('bulk.selecione') }}</option>@foreach(['residencial'=>__('review.estab_residential'),'comercial'=>__('review.estab_commercial'),'shopping'=>'Shopping','outro'=>__('review.estab_other')] as $type=>$label)<option value="{{ $type }}" @selected(old('type',$establishment?->type)===$type)>{{ $label }}</option>@endforeach</select>@error('type')<span class="text-[#c62828] dark:text-red-300">{{ $message }}</span>@enderror</label>
                <div class="rounded-lg bg-gray-50 p-4 dark:bg-[#181b22]"><p class="text-xs text-gray-500 dark:text-gray-400">{{ __('bulk.gestor_responsavel') }}</p><p class="mt-1 font-semibold">{{ Auth::user()->name }}</p><p class="mt-1 break-all text-sm text-gray-500 dark:text-gray-400">{{ Auth::user()->email }}</p></div>
            </div>
        </section>
        <section id="configurar-tarifas" class="gw-card rounded-xl border border-gray-200 bg-white p-5 sm:p-7 dark:border-[#343843] dark:bg-[#1a1a1a]">
            <h2 class="mb-2 text-lg font-semibold"><span class="gw-section-number">02</span>{{ __('bulk.tarifa_e_demanda') }}</h2><p class="mb-5 text-sm text-gray-500 dark:text-gray-400">{{ __('review.estab_rate_details') }}</p>
            @if($establishment)<p class="mb-5 rounded-lg bg-gray-50 p-4 text-sm dark:bg-[#181b22]">{{ __('review.estab_current_rate') }} <strong>R$ {{ number_format((float)$establishment->currentTariff(),4,',','.') }}/kWh</strong></p>@endif
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <x-manager-field name="cost_per_kwh" :label="__('review.estab_base_rate')" type="number" :value="$establishment?->cost_per_kwh" min="0.0001" max="9999.9999" step="0.0001" />
                <label class="gw-field flex min-w-0 flex-col gap-2 text-sm font-medium">{{ __('bulk.modo_de_tarifacao') }}<select name="pricing_mode" required class="w-full min-w-0 rounded-lg border border-gray-300 bg-gray-50 px-3 py-3 dark:border-[#343843] dark:bg-[#181b22]"><option value="">{{ __('bulk.selecione') }}</option><option value="manual" @selected(old('pricing_mode',$establishment?->pricing_mode)==='manual')>{{ __('bulk.manual_aprovar_cada_reajuste') }}</option><option value="automatic" @selected(old('pricing_mode',$establishment?->pricing_mode)==='automatic')>{{ __('bulk.automatico_aplicar_o_percentual_configurado') }}</option></select>@error('pricing_mode')<span class="text-[#c62828] dark:text-red-300">{{ $message }}</span>@enderror</label>
                <x-manager-field name="surge_percentage" :label="__('review.estab_peak_adj')" type="number" :value="$establishment?->surge_percentage" min="0" max="99999.99" step="0.01" />
                <x-manager-field name="max_power_kw" :label="__('review.estab_max_power')" type="number" :value="$establishment?->max_power_kw" :required="false" min="0" max="999999.99" step="0.01" />
                <x-manager-field name="max_current_a" :label="__('review.estab_max_current')" type="number" :value="$establishment?->max_current_a" :required="false" min="0" max="999999.99" step="0.01" />
            </div>
            <p class="mt-5 text-sm text-gray-500 dark:text-gray-400">{{ __('review.estab_limits_help') }}</p>
            <p class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-700/40 dark:bg-amber-500/10 dark:text-amber-200">{{ __('review.estab_peak_help') }}</p>
        </section>
        <section id="regras-reserva" class="gw-card rounded-xl border border-gray-200 bg-white p-5 sm:p-7 dark:border-[#343843] dark:bg-[#1a1a1a]">
            <h2 class="mb-2 text-lg font-semibold"><span class="gw-section-number">03</span>{{ __('bulk.reservas_imediatas') }}</h2><p class="mb-5 text-sm text-gray-500 dark:text-gray-400">{{ __('review.estab_cancel_help') }}</p>
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2"><x-manager-field name="reservation_tolerance_minutes" :label="__('review.estab_tolerance')" type="number" :value="$establishment?->reservation_tolerance_minutes" min="1" step="1" /><x-manager-field name="no_show_penalty_points" :label="__('review.estab_penalty')" type="number" :value="$establishment?->no_show_penalty_points" min="0" step="0.01" /></div>
            <p class="mt-5 text-sm text-gray-500 dark:text-gray-400">{{ __('review.estab_penalty_help') }}</p>
        </section>
        <div class="gw-form-actions">@if($establishment)<a href="{{ route('manager.dashboard') }}" class="gw-button gw-button-secondary rounded-lg border border-gray-300 px-5 py-3 text-sm dark:border-[#343843]">{{ __('bulk.voltar') }}</a>@endif<button type="submit" class="gw-button gw-button-primary rounded-lg bg-[#c62828] px-6 py-3 text-sm font-semibold text-white hover:bg-[#b71c1c]">{{ $establishment ? __('review.estab_save') : __('review.estab_register') }}</button></div>
    </form>
</x-manager-legacy-layout>
