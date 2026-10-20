<?php

namespace Tests\Feature;

use App\Models\Charger;
use App\Models\DemandPeak;
use App\Models\Establishment;
use App\Models\TariffEvent;
use App\Models\User;
use App\Services\DemandPricingService;
use App\Services\MeteredChargingService;
use App\Services\ReservationService;
use App\Services\TariffCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Fakes\FakeDemandDetector;
use Tests\TestCase;

class DemandPricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demand.detector' => FakeDemandDetector::class]);
        $this->travelTo(Carbon::parse('2026-10-14 12:00:00'));
    }

    private function store(string $mode = 'manual', string $percentage = '15.00'): Establishment
    {
        return Establishment::factory()->create(['pricing_mode' => $mode, 'surge_percentage' => $percentage]);
    }

    private function evidence(): array
    {
        return ['validated' => true, 'history' => ['sample_count' => 10], 'occupancy' => ['available' => 1, 'reserved' => 1, 'in_use' => 2]];
    }

    private function begin(Establishment $store, string $key = 'peak-1'): DemandPeak
    {
        return app(DemandPricingService::class)->beginConfirmedPeak($store, $key, 'test-policy-v1', $this->evidence(), now()->subMinute());
    }

    private function end(Establishment $store, string $key = 'peak-1', string $event = 'end-1'): DemandPeak
    {
        return app(DemandPricingService::class)->endConfirmedPeak($store, $key, $event, 'test-policy-v1', $this->evidence(), now());
    }

    private function expectInvalid(callable $operation): void
    {
        try {
            $operation();
            $this->fail('A operação inválida foi aceita.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }

    public function test_percentage_is_calculated_from_base_with_exact_half_up_rounding_and_no_float(): void
    {
        $this->assertSame('2.1900', TariffCalculator::adjusted('1.9000', '15.00'));
        $this->assertSame('1.2345', TariffCalculator::adjusted('1.2345', '0'));
        $this->assertSame('1.2345', TariffCalculator::adjusted('1.2345', '0.01'));
        $this->assertSame('0.0001', TariffCalculator::adjusted('0.0001', '1'));
        $this->expectInvalid(fn () => TariffCalculator::adjusted('9999.9999', '100'));
        $this->expectInvalid(fn () => TariffCalculator::adjusted('1.9', '-1'));
    }

    public function test_no_peak_is_accepted_without_a_configured_detector_and_valid_criteria(): void
    {
        $store = $this->store('automatic');
        config(['demand.detector' => null]);
        $this->expectInvalid(fn () => $this->begin($store));
        $this->assertDatabaseCount('demand_peaks', 0);
        $this->assertDatabaseCount('tariff_events', 0);
        $this->assertSame('1.9000', $store->fresh()->currentTariff());
    }

    public function test_rejected_evidence_unknown_policy_future_time_and_missing_configuration_cannot_change_prices(): void
    {
        $store = $this->store('automatic');
        $service = app(DemandPricingService::class);
        $this->expectInvalid(fn () => $service->beginConfirmedPeak($store, 'x', 'unknown', $this->evidence(), now()));
        $this->expectInvalid(fn () => $service->beginConfirmedPeak($store, 'x', 'test-policy-v1', [], now()));
        $this->expectInvalid(fn () => $service->beginConfirmedPeak($store, 'x', 'test-policy-v1', $this->evidence(), now()->addSecond()));
        $store->update(['surge_percentage' => null]);
        $this->expectInvalid(fn () => $this->begin($store));
        $this->assertDatabaseCount('demand_peaks', 0);
        $this->assertDatabaseCount('tariff_events', 0);
    }

    public function test_manual_peak_proposes_a_price_without_applying_it_and_approval_is_idempotent(): void
    {
        $store = $this->store();
        $peak = $this->begin($store);
        $service = app(DemandPricingService::class);
        $this->assertSame('pending', $peak->decision);
        $this->assertSame('2.1900', $peak->suggested_tariff);
        $this->assertSame('1.9000', $store->fresh()->currentTariff());
        $this->actingAs($store->user)->get(route('manager.demand.index'))->assertOk()->assertSee('Aplicar reajuste')->assertSee('2,1900');
        $this->post(route('manager.demand.decide', $peak), ['decision' => 'approve', 'version' => 1])->assertRedirect();
        $this->assertSame('2.1900', $store->fresh()->currentTariff());
        $service->decide($store->user, $peak, true, 1);
        $this->assertSame(1, TariffEvent::where('type', 'approved')->count());
        $this->assertSame($store->user_id, TariffEvent::where('type', 'approved')->firstOrFail()->actor_id);
    }

    public function test_manual_rejection_preserves_base_and_does_not_report_a_price_change(): void
    {
        $store = $this->store();
        $peak = $this->begin($store);
        $service = app(DemandPricingService::class);
        $service->decide($store->user, $peak, false, 1);
        $service->decide($store->user, $peak, false, 1);
        $this->assertSame('1.9000', $store->fresh()->currentTariff());
        $this->assertSame('rejected', $peak->fresh()->decision);
        $event = TariffEvent::where('type', 'rejected')->sole();
        $this->assertSame($event->previous_tariff, $event->new_tariff);
        $this->expectInvalid(fn () => $service->decide($store->user, $peak, true, 1));
    }

    public function test_automatic_peak_changes_the_shared_price_once_without_compounding(): void
    {
        $store = $this->store('automatic');
        $a = Charger::factory()->for($store)->create();
        $b = Charger::factory()->for($store)->create(['tariff_override' => '99']);
        $first = $this->begin($store);
        $second = $this->begin($store);
        $this->assertSame($first->id, $second->id);
        $this->assertSame('applied', $first->decision);
        $this->assertSame('2.1900', $store->fresh()->currentTariff());
        $this->assertSame('2.1900', $a->fresh()->currentTariff());
        // O carregador com tarifa própria não é afetado pelo reajuste do estabelecimento.
        $this->assertSame('99.0000', $b->fresh()->currentTariff());
        $this->assertDatabaseCount('demand_peaks', 1);
        $this->assertSame(1, TariffEvent::where('type', 'automatic_adjustment')->count());
        $this->expectInvalid(fn () => $this->begin($store, 'overlapping'));
    }

    public function test_duplicate_external_id_with_divergent_evidence_is_rejected(): void
    {
        $store = $this->store('automatic');
        $this->begin($store);
        $evidence = $this->evidence();
        $evidence['history']['sample_count'] = 99;
        $this->expectInvalid(fn () => app(DemandPricingService::class)->beginConfirmedPeak($store, 'peak-1', 'test-policy-v1', $evidence, now()->subMinute()));
        $this->assertDatabaseCount('demand_peaks', 1);
        $this->assertSame('2.1900', $store->fresh()->currentTariff());
    }

    public function test_confirmed_end_restores_base_automatically_in_both_modes(): void
    {
        foreach (['manual', 'automatic'] as $mode) {
            $store = $this->store($mode);
            $peak = $this->begin($store);
            if ($mode === 'manual') {
                app(DemandPricingService::class)->decide($store->user, $peak, true, 1);
            }
            $this->assertSame('2.1900', $store->fresh()->currentTariff());
            $this->end($store);
            $this->end($store);
            $this->assertSame('1.9000', $store->fresh()->currentTariff());
            $this->assertNull($store->fresh()->active_peak_id);
            $this->assertSame('ended', $peak->fresh()->status);
            $this->assertSame(1, $store->tariffEvents()->where('type', 'base_restored')->count());
        }
    }

    public function test_unapproved_or_rejected_peak_end_does_not_fabricate_a_restoration(): void
    {
        foreach ([false, true] as $reject) {
            $store = $this->store();
            $peak = $this->begin($store);
            if ($reject) {
                app(DemandPricingService::class)->decide($store->user, $peak, false, 1);
            }
            $this->end($store);
            $this->assertSame(0, $store->tariffEvents()->where('type', 'base_restored')->count());
            $this->expectInvalid(fn () => app(DemandPricingService::class)->decide($store->user, $peak, true, 1));
        }
    }

    public function test_old_end_replay_cannot_end_a_later_peak_and_older_start_is_rejected(): void
    {
        $store = $this->store('automatic');
        $this->begin($store);
        $this->end($store);
        $this->expectInvalid(fn () => $this->begin($store, 'old-peak'));
        $this->travel(2)->minutes();
        $new = $this->begin($store, 'peak-2');
        app(DemandPricingService::class)->endConfirmedPeak($store, 'peak-1', 'end-1', 'test-policy-v1', $this->evidence(), now()->subMinutes(2));
        $this->assertSame($new->id, $store->fresh()->active_peak_id);
        $this->assertSame('2.1900', $store->fresh()->currentTariff());
    }

    public function test_end_with_divergent_id_time_or_content_and_end_before_start_are_rejected(): void
    {
        $store = $this->store('automatic');
        $this->begin($store);
        $service = app(DemandPricingService::class);
        $this->expectInvalid(fn () => $service->endConfirmedPeak($store, 'peak-1', 'x', 'test-policy-v1', $this->evidence(), now()->subMinutes(2)));
        $this->end($store);
        $this->expectInvalid(fn () => $this->end($store, 'peak-1', 'different'));
        $this->assertSame('1.9000', $store->fresh()->currentTariff());
    }

    public function test_foreign_manager_or_regular_user_cannot_approve_or_read_another_peak(): void
    {
        $store = $this->store();
        $peak = $this->begin($store);
        $other = $this->store();
        $this->actingAs($other->user)->postJson(route('manager.demand.decide', $peak), ['decision' => 'approve', 'version' => 1])->assertForbidden();
        $this->get(route('manager.demand.index'))->assertOk()->assertDontSee('Pico de demanda registrado');
        $this->actingAs(User::factory()->create())->postJson(route('manager.demand.decide', $peak), ['decision' => 'approve', 'version' => 1])->assertForbidden();
        $this->assertSame('1.9000', $store->fresh()->currentTariff());
    }

    public function test_configuration_change_versions_a_pending_proposal_and_rejects_stale_approval(): void
    {
        $store = $this->store();
        $peak = $this->begin($store);
        $service = app(DemandPricingService::class);
        $service->updateSettings($store->user, $store, ['cost_per_kwh' => '2.0000', 'surge_percentage' => '20.00']);
        $this->assertSame('2.0000', $store->fresh()->currentTariff());
        $this->assertSame(2, $peak->fresh()->version);
        $this->assertSame('2.4000', $peak->fresh()->suggested_tariff);
        $this->expectInvalid(fn () => $service->decide($store->user, $peak, true, 1));
        $service->decide($store->user, $peak, true, 2);
        $this->assertSame('2.4000', $store->fresh()->currentTariff());
    }

    public function test_applied_peak_recalculates_from_new_base_and_restores_latest_base(): void
    {
        $store = $this->store('automatic');
        $this->begin($store);
        $service = app(DemandPricingService::class);
        $service->updateSettings($store->user, $store, ['cost_per_kwh' => '2.0000', 'surge_percentage' => '15.00']);
        $this->assertSame('2.3000', $store->fresh()->currentTariff());
        $this->end($store);
        $this->assertSame('2.0000', $store->fresh()->currentTariff());
    }

    public function test_switch_to_automatic_applies_a_valid_existing_peak_without_another_detection(): void
    {
        $store = $this->store();
        $peak = $this->begin($store);
        app(DemandPricingService::class)->updateSettings($store->user, $store, ['pricing_mode' => 'automatic']);
        $this->assertSame('applied', $peak->fresh()->decision);
        $this->assertSame('2.1900', $store->fresh()->currentTariff());
    }

    public function test_zero_percentage_can_record_a_peak_without_recording_a_financial_change(): void
    {
        $store = $this->store('automatic', '0');
        $this->begin($store);
        $this->end($store);
        $this->assertSame('1.9000', $store->fresh()->currentTariff());
        $this->assertSame(0, TariffEvent::whereIn('type', ['automatic_adjustment', 'base_restored'])->count());
    }

    public function test_sessions_and_reservations_keep_the_tariff_before_during_and_after_the_peak(): void
    {
        $store = $this->store('automatic');
        $driver = User::factory()->create(['balance' => 100, 'points' => 25]);
        $reservationService = app(ReservationService::class);
        $meter = app(MeteredChargingService::class);
        $beforeCharger = Charger::factory()->for($store)->create();
        $duringCharger = Charger::factory()->for($store)->create();
        $afterCharger = Charger::factory()->for($store)->create();
        $before = $reservationService->reserve($driver, $beforeCharger, (string) Str::uuid());
        $this->begin($store);
        $during = $reservationService->reserve($driver, $duringCharger, (string) Str::uuid());
        $duringSession = $meter->startConfirmed($driver, $duringCharger, 'during', $during->id);
        $this->end($store);
        $beforeSession = $meter->startConfirmed($driver, $beforeCharger, 'before', $before->id);
        $afterSession = $meter->startConfirmed($driver, $afterCharger, 'after');
        $this->assertSame('1.9000', $beforeSession->tariff_per_kwh);
        $this->assertSame('2.1900', $duringSession->fresh()->tariff_per_kwh);
        $this->assertSame('1.9000', $afterSession->tariff_per_kwh);
        $this->assertSame('100.00', $driver->fresh()->balance);
        $this->assertSame('25.00', $driver->fresh()->points);
    }

    public function test_alert_history_is_scoped_and_cannot_be_selected_through_query_parameters(): void
    {
        $own = $this->store();
        $other = $this->store();
        $this->begin($other);
        $this->actingAs($own->user)->get(route('manager.demand.index', ['establishment_id' => $other->id]))->assertOk()->assertDontSee('Pico de demanda registrado')->assertViewHas('events', fn ($events) => $events->count() === 0);
    }
}
