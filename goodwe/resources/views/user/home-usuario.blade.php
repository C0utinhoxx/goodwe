
<x-user-layout>
    <x-slot name="pageTitle">{{ __('portal.home') }}</x-slot>

    <x-slot name="header">
        <div>
            <p class="pt-eyebrow">{{ __('userhome.overview') }}</p>

            <h1 class="pt-heading">
                {{ __('home.greeting', ['name' => explode(' ', trim(Auth::user()->name))[0]]) }}
                <span class="pt-wave">👋</span>
            </h1>

            <p class="pt-muted pt-subheading">{{ __('userhome.subtitle') }}</p>
        </div>
    </x-slot>

    <section class="pt-hero">
        <div class="pt-hero-content">
            <span class="pt-hero-eyebrow">
                <span class="pt-dot"></span>
                {{ __('userhome.welcome') }}
            </span>

            <h2>
                {{ __('userhome.hero_title') }}<br>
                <em>{{ __('userhome.hero_highlight') }}</em>
            </h2>

            <p>{{ __('userhome.hero_description') }}</p>

            <div class="pt-hero-actions">
                <a href="{{ route('totens.index') }}" class="pt-button pt-button-light">
                    <i data-lucide="map-pin"></i>
                    {{ __('home.find_charger') }}
                    <i data-lucide="arrow-up-right"></i>
                </a>

                <a href="{{ route('wallet.deposit.form') }}" class="pt-hero-link">
                    {{ __('userhome.add_balance') }}
                    <i data-lucide="arrow-right"></i>
                </a>
            </div>
        </div>

        <div class="pt-hero-illustration" aria-hidden="true">
            <div class="pt-orbit orbit-outer"></div>
            <div class="pt-orbit orbit-inner"></div>

            <div class="pt-charge-symbol">
                <i data-lucide="zap"></i>
            </div>

            <span class="pt-orbit-label label-one">{{ __('userhome.electric') }}</span>
            <span class="pt-orbit-label label-two">POWER ON</span>
        </div>
    </section>

    <section class="pt-stats-grid" aria-label="{{ __('userhome.account_summary') }}">
        <article class="pt-stat">
            <div class="pt-stat-top">
                <span class="pt-stat-icon green">
                    <i data-lucide="wallet"></i>
                </span>
                <span class="pt-mini-label">{{ __('home.available_balance') }}</span>
            </div>

            <strong>R$ {{ number_format(Auth::user()->balance ?? 0, 2, ',', '.') }}</strong>

            <a href="{{ route('wallet.deposit.form') }}">
                {{ __('userhome.add_balance') }}
                <i data-lucide="arrow-up-right"></i>
            </a>
        </article>

        <article class="pt-stat">
            <div class="pt-stat-top">
                <span class="pt-stat-icon purple">
                    <i data-lucide="gift"></i>
                </span>
                <span class="pt-mini-label">{{ __('portal.points') }}</span>
            </div>

            <strong>
                {{ number_format(Auth::user()->points ?? 0, 2, ',', '.') }}
                <small>pts</small>
            </strong>

            <a href="{{ route('points.index') }}">
                {{ __('home.see_benefits') }}
                <i data-lucide="arrow-up-right"></i>
            </a>
        </article>

        <article class="pt-stat">
            <div class="pt-stat-top">
                <span class="pt-stat-icon blue">
                    <i data-lucide="zap"></i>
                </span>
                <span class="pt-mini-label">{{ __('userhome.completed_charges') }}</span>
            </div>

            <strong>{{ $totalSessions }}</strong>

            <a href="{{ route('wallet.history') }}">
                {{ __('userhome.view_history') }}
                <i data-lucide="arrow-up-right"></i>
            </a>
        </article>

        <article class="pt-stat">
            <div class="pt-stat-top">
                <span class="pt-stat-icon amber">
                    <i data-lucide="plug-zap"></i>
                </span>
                <span class="pt-mini-label">{{ __('userhome.available_chargers') }}</span>
            </div>

            <strong>
                {{ $availableChargers }}
                <small>/ {{ $totalChargers }}</small>
            </strong>

            <a href="{{ route('totens.index') }}">
                {{ __('userhome.view_stations') }}
                <i data-lucide="arrow-up-right"></i>
            </a>
        </article>
    </section>

    @foreach($activeReservations as $reservation)
        <section
            class="pt-info-panel pt-mt"
            data-reservation-card
            data-reservation-status="active"
            data-expires-at="{{ $reservation->expires_at->toIso8601String() }}"
            data-server-time="{{ now()->toIso8601String() }}"
            data-status-url="{{ route('reservations.status', $reservation) }}"
        >
            <span class="pt-info-icon">
                <i data-lucide="calendar-clock"></i>
            </span>

            <div>
                <strong data-reservation-state>
                    {{ __('userhome.reservation_confirmed', ['charger' => $reservation->charger->name]) }}
                </strong>

                <p>
                    {{ $reservation->establishment->name }}
                    · {{ __('userhome.guaranteed_rate') }}:
                    R$ {{ number_format($reservation->tariff_per_kwh, 4, ',', '.') }}/kWh.
                </p>

                <p>
                    {{ __('userhome.time_remaining') }}:
                    <strong data-reservation-countdown>
                        {{ __('userhome.checking_time') }}
                    </strong>
                </p>

                <a href="{{ route('chargers.index') }}#minhas-reservas">
                    {{ __('userhome.manage_reservation') }}
                </a>
            </div>
        </section>
    @endforeach

    @if($pendingSession)
        <section class="pt-info-panel pt-mt">
            <span class="pt-info-icon">
                <i data-lucide="battery-charging"></i>
            </span>

            <div>
                <strong>
                    {{ __('userhome.pending_charge', ['charger' => $pendingSession->charger_name]) }}
                </strong>

                <p>
                    {{ __('userhome.session_registered', ['date' => $pendingSession->created_at?->format('d/m/Y H:i') ?? '-']) }}
                    {{ __('userhome.iot_notice') }}
                </p>

                <a href="{{ $pendingSession->charging_state ? route('charging-sessions.show', $pendingSession) : route('wallet.history') }}">
                    {{ $pendingSession->charging_state === 'ended'
                        ? __('userhome.review_payment')
                        : __('userhome.view_session') }}
                    <i data-lucide="arrow-right"></i>
                </a>
            </div>
        </section>
    @endif

    <div class="pt-section-grid">
        <section class="pt-card pt-recent">
            <div class="pt-card-heading">
                <div>
                    <p class="pt-eyebrow">{{ __('userhome.your_history') }}</p>
                    <h2>{{ __('userhome.latest_charges') }}</h2>
                    <p class="pt-form-note">{{ __('userhome.history_notice') }}</p>
                </div>

                <a href="{{ route('wallet.history') }}" class="pt-text-link">
                    {{ __('userhome.view_all') }}
                    <i data-lucide="arrow-up-right"></i>
                </a>
            </div>

            @forelse($sessions as $session)
                <div class="pt-activity-row">
                    <span class="pt-activity-icon">
                        <i data-lucide="battery-charging"></i>
                    </span>

                    <div class="pt-activity-copy">
                        <strong>{{ $session->charger_name }}</strong>

                        <small>
                            {{ $session->created_at?->format('d/m/Y · H:i') }}
                            ·
                            {{ number_format(
                                $session->measured_energy_kwh ?? $session->energy_kwh ?? 0,
                                $session->measured_energy_kwh !== null ? 4 : 2,
                                ',',
                                '.'
                            ) }}
                            kWh
                            ·
                            {{ $session->charging_state
                                ? __('userhome.confirmed_consumption')
                                : __('userhome.previous_record') }}
                        </small>
                    </div>

                    <div class="pt-activity-end">
                        <strong>
                            {{ $session->payment_status === 'pending'
                                ? __('userhome.pending_payment')
                                : 'R$ '.number_format($session->amount_charged ?? 0, 2, ',', '.') }}
                        </strong>

                        <span class="pt-status {{ $session->status === 'completed' ? 'success' : 'neutral' }}">
                            {{ match($session->status) {
                                'completed' => __('userhome.status_completed'),
                                'pending' => __('userhome.status_pending'),
                                'cancelled' => __('userhome.status_cancelled'),
                                default => ucfirst((string) $session->status)
                            } }}
                        </span>
                    </div>
                </div>
            @empty
                <div class="pt-empty">
                    <i data-lucide="battery-charging"></i>
                    <strong>{{ __('userhome.no_charges') }}</strong>
                    <p>{{ __('userhome.no_charges_description') }}</p>

                    <a href="{{ route('totens.index') }}" class="pt-button pt-button-primary">
                        {{ __('userhome.explore_chargers') }}
                    </a>
                </div>
            @endforelse
        </section>

        <div class="pt-right-stack">
            <section class="pt-card">
                <div class="pt-card-heading">
                    <div>
                        <p class="pt-eyebrow">{{ __('userhome.shortcuts') }}</p>
                        <h2>{{ __('userhome.quick_access') }}</h2>
                    </div>
                </div>

                <div class="pt-quick-grid">
                    <a href="{{ route('totens.index') }}">
                        <span class="pt-quick-icon green">
                            <i data-lucide="map-pin"></i>
                        </span>
                        <strong>{{ __('nav.chargers') }}</strong>
                        <small>{{ __('userhome.find_station') }}</small>
                        <i data-lucide="arrow-up-right" class="pt-quick-arrow"></i>
                    </a>

                    <a href="{{ route('wallet.deposit.form') }}">
                        <span class="pt-quick-icon blue">
                            <i data-lucide="plus-circle"></i>
                        </span>
                        <strong>{{ __('userhome.add_balance') }}</strong>
                        <small>{{ __('userhome.top_up_wallet') }}</small>
                        <i data-lucide="arrow-up-right" class="pt-quick-arrow"></i>
                    </a>

                    <a href="{{ route('points.index') }}">
                        <span class="pt-quick-icon purple">
                            <i data-lucide="gift"></i>
                        </span>
                        <strong>{{ __('userhome.benefits') }}</strong>
                        <small>{{ __('userhome.track_points') }}</small>
                        <i data-lucide="arrow-up-right" class="pt-quick-arrow"></i>
                    </a>

                    <a href="{{ route('user.profile') }}">
                        <span class="pt-quick-icon amber">
                            <i data-lucide="car-front"></i>
                        </span>
                        <strong>{{ __('userhome.my_vehicles') }}</strong>
                        <small>{{ __('userhome.manage_profile') }}</small>
                        <i data-lucide="arrow-up-right" class="pt-quick-arrow"></i>
                    </a>
                </div>
            </section>

            <section class="pt-info-panel">
                <span class="pt-info-icon">
                    <i data-lucide="lightbulb"></i>
                </span>

                <div>
                    <strong>{{ __('userhome.points_tip_title') }}</strong>
                    <p>{{ __('userhome.points_tip_description') }}</p>

                    <a href="{{ route('points.index') }}">
                        {{ __('userhome.learn_more') }}
                        <i data-lucide="arrow-right"></i>
                    </a>
                </div>
            </section>
        </div>
    </div>
</x-user-layout>
