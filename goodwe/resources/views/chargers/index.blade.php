<x-user-layout>
    <x-slot name="pageTitle">Carregadores</x-slot>
    <x-slot name="header"><div><p class="pt-eyebrow">REDE POWERTAG</p><h1 class="pt-heading">{{ __('chargers.title') }} <span class="pt-heading-icon">⚡</span></h1><p class="pt-muted pt-subheading">Consulte as estações e programe uma recarga no ambiente de demonstração.</p></div></x-slot>
    <section class="pt-card pt-filter-panel"><div class="pt-filter-top"><div class="pt-filter-search"><i data-lucide="search"></i><label for="charger-search" class="sr-only">Buscar carregador</label><input id="charger-search" type="search" placeholder="Buscar por nome, local ou conector..." data-charger-search></div><label class="pt-select-label">Status <select data-charger-status><option value="all">Todos</option><option value="available">Disponíveis</option><option value="occupied">Ocupados</option></select></label><label class="pt-select-label">Potência <select data-charger-power><option value="all">Todas</option><option value="7">7 kW</option><option value="11">11 kW</option><option value="22">22 kW</option><option value="50">50 kW (rede existente)</option></select></label></div><div class="pt-filter-foot"><span><span class="pt-dot"></span> <strong data-charger-count>{{ count($chargers) }}</strong> carregadores encontrados</span><span>Ambiente simulado · Disponibilidade sujeita a atualização</span></div></section>
    <section class="pt-chargers-grid" data-chargers-list>
        @foreach($chargers as $charger)
            @php($available = $charger->isAvailable())
            <article class="pt-card pt-charger" data-charger-card data-search="{{ mb_strtolower($charger->name.' '.$charger->location.' '.$charger->connector) }}" data-status="{{ $available ? 'available' : 'occupied' }}" data-power="{{ $charger->powerKw }}">
                <div class="pt-charger-top"><span class="pt-station-icon"><i data-lucide="plug-zap"></i></span><span class="pt-status {{ $available ? 'success' : 'neutral' }}"><span class="pt-dot"></span>{{ $available ? 'Disponível' : 'Ocupado' }}</span></div>
                <h2>{{ $charger->name }}</h2><p class="pt-station-location"><i data-lucide="map-pin"></i> {{ $charger->location }}</p>
                <div class="pt-station-specs"><span><i data-lucide="zap"></i> {{ $charger->powerKw }} kW</span><span><i data-lucide="cable"></i> {{ $charger->connector }}</span></div>
                <div class="pt-station-foot"><div><small>Tarifa de referência</small><strong>R$ 1,90 <span>/ kWh</span></strong></div><button type="button" class="pt-button pt-button-primary" data-charger-open="charger-{{ $loop->index }}">{{ $available ? 'Detalhes e recarga' : 'Ver detalhes' }} <i data-lucide="arrow-right"></i></button></div>
                @if(!$available)<p class="pt-charger-occupied">Estação atualmente ocupada. Consulte novamente mais tarde.</p>@endif
            </article>
        @endforeach
    </section>
    <div class="pt-empty" data-charger-empty hidden>Nenhum carregador corresponde aos filtros selecionados.</div>
    @foreach($chargers as $charger)
        <dialog id="charger-{{ $loop->index }}" class="pt-dialog" aria-labelledby="charger-title-{{ $loop->index }}">
            <div class="pt-dialog-body"><form method="POST" action="{{ route('chargers.schedule', $charger->id) }}">@csrf
                <input type="hidden" name="idempotency_key" value="{{ Illuminate\Support\Str::uuid() }}">
                <div class="pt-dialog-top"><div><p class="pt-eyebrow">PROGRAMAR RECARGA</p><h2 id="charger-title-{{ $loop->index }}">{{ $charger->name }}</h2><p class="pt-muted">{{ $charger->location }}</p></div><button type="button" class="pt-dialog-close" data-dialog-close aria-label="Fechar"><i data-lucide="x"></i></button></div>
                <div class="pt-dialog-highlight"><span><i data-lucide="zap"></i> {{ $charger->powerKw }} kW</span><span><i data-lucide="cable"></i> {{ $charger->connector }}</span><span>Tarifa de referência: R$ 1,90/kWh</span></div>
                @if($charger->isAvailable())
                @if($vehicles->isNotEmpty())
                    <label class="pt-field">Escolher veículo cadastrado <select data-vehicle-select><option value="">Digitar placa manualmente</option>@foreach($vehicles as $vehicle)<option value="{{ $vehicle->plate }}">{{ $vehicle->brand }} {{ $vehicle->model }} · {{ $vehicle->plate }}</option>@endforeach</select></label>
                @endif
                <label class="pt-field">Placa do veículo <input type="text" name="vehicle_plate" maxlength="8" minlength="7" pattern="[A-Za-z]{3}-?[0-9][A-Za-z0-9][0-9]{2}" placeholder="ABC1D23" value="{{ old('vehicle_plate') }}" required autocomplete="off"></label>
                <label class="pt-field">Duração desejada <select name="duration_minutes" required data-duration><option value="15" @selected((int) old('duration_minutes', 30) === 15)>15 minutos</option><option value="30" @selected((int) old('duration_minutes', 30) === 30)>30 minutos</option><option value="45" @selected((int) old('duration_minutes', 30) === 45)>45 minutos</option><option value="60" @selected((int) old('duration_minutes', 30) === 60)>60 minutos</option><option value="120" @selected((int) old('duration_minutes', 30) === 120)>120 minutos</option></select></label>
                <p class="pt-form-note">Você possui {{ $coupons->count() }} {{ $coupons->count() === 1 ? 'cupom disponível' : 'cupons disponíveis' }}.</p>
                <label class="pt-field">Aplicar cupom <select name="coupon_id" data-payment-coupon><option value="" data-percent="0">Sem cupom</option>@foreach($coupons as $coupon)<option value="{{ $coupon->id }}" data-percent="{{ $coupon->discount_percent }}">{{ $coupon->name }} · {{ $coupon->discount_percent }}% OFF · {{ $coupon->code }}</option>@endforeach</select></label>
                <dl class="pt-payment-summary" data-estimate data-minute-cents="{{ config('loyalty.simulation.cents_per_minute') }}" data-energy-hundredths="{{ config('loyalty.simulation.energy_hundredths_per_minute') }}">
                    <div><dt>Energia estimada no simulador</dt><dd data-estimate-energy>—</dd></div>
                    <div><dt>Tarifa por kWh (referência)</dt><dd>R$ {{ number_format(config('loyalty.simulation.cents_per_kwh') / 100, 2, ',', '.') }}</dd></div>
                    <div><dt>Valor original da simulação</dt><dd data-estimate-price>—</dd></div>
                    <div><dt>Desconto do cupom</dt><dd data-estimate-discount>—</dd></div>
                    <div class="pt-payment-total"><dt>Total a pagar agora</dt><dd data-estimate-total>—</dd></div>
                    <div><dt>Saldo na carteira</dt><dd>R$ {{ number_format(auth()->user()->balance, 2, ',', '.') }}</dd></div>
                </dl>
                <p class="pt-form-note">Esta é uma programação na simulação existente. O custo exibido corresponde à cobrança atual do simulador (R$ 0,65/min), diferente da tarifa de referência de R$ 1,90/kWh. Não inicia o ESP32. A energia é calculada pelo simulador, sem medição física; a disponibilidade pode reiniciar entre acessos.</p>
                <div class="pt-form-actions"><button type="button" data-dialog-close class="pt-button pt-button-outline">Cancelar</button><button type="submit" class="pt-button pt-button-primary">Confirmar pagamento <i data-lucide="arrow-right"></i></button></div>
                @else
                    <p class="pt-muted">Carregador ocupado no ambiente simulado. A programação fica disponível quando a estação estiver livre.</p>
                    <div class="pt-form-actions"><button type="button" data-dialog-close class="pt-button pt-button-outline">Fechar detalhes</button></div>
                @endif
            </form></div>
        </dialog>
    @endforeach
</x-user-layout>
