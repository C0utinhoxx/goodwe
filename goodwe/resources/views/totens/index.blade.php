<x-user-layout>
    <x-slot name="pageTitle">{{ __('totens.title') }}</x-slot>

    <x-slot name="header">
        <div>
            <p class="pt-eyebrow">POWERTAG</p>
            <h1 class="pt-heading">{{ __('totens.title') }}</h1>
            <p class="pt-muted pt-subheading">{{ __('totens.index_subtitle') }}</p>
        </div>
    </x-slot>

    <section class="pt-card pt-filter-panel pt-mt">
        <div class="pt-filter-foot">
            <span>{{ __('totens.index_kicker') }}</span>
            <span>{{ __('totens.index_count', ['count' => count($totems)]) }}</span>
        </div>
    </section>

    @if ($totems === [])
        <section class="pt-card pt-mt">
            <div class="pt-empty">
                <i data-lucide="plug-zap"></i>
                <strong>{{ __('totens.index_empty_title') }}</strong>
                <p>{{ __('totens.index_empty_body') }}</p>
            </div>
        </section>
    @else
        <section class="pt-chargers-grid">
            @foreach ($totems as $totem)
                <article class="pt-card pt-charger">
                    <div class="pt-charger-top">
                        <span class="pt-station-icon"><i data-lucide="battery-charging"></i></span>
                        <span class="pt-status neutral font-mono">{{ $totem['id'] }}</span>
                    </div>

                    <h2>{{ $totem['name'] }}</h2>
                    <p class="pt-station-location">
                        <i data-lucide="map-pin"></i>
                        {{ $totem['location'] }}
                    </p>

                    <div class="pt-station-foot">
                        <div>
                            <small>{{ __('totens.totem_id') }}</small>
                            <strong class="font-mono">{{ $totem['id'] }}</strong>
                        </div>
                        <a href="{{ route('totens.carregar', $totem['id']) }}" class="pt-button pt-button-primary">
                            <i data-lucide="zap"></i>
                            {{ __('totens.open_recharge') }}
                        </a>
                    </div>
                </article>
            @endforeach
        </section>

        <p class="pt-form-note pt-mt">{{ __('totens.index_note') }}</p>
    @endif
</x-user-layout>
