<?php

namespace Tests\Feature;

use App\Models\Charger;
use App\Models\ChargingSession;
use App\Models\Establishment;
use App\Models\Transaction;
use App\Models\User;
use App\Services\EstablishmentService;
use App\Services\MeteredChargingService;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ManagerAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge(['name' => 'Estabelecimento de teste', 'cnpj' => '11.222.333/0001-81', 'address' => 'Endereço de teste', 'phone' => '11987654321', 'type' => 'comercial', 'operating_hours' => '08:00–22:00', 'cost_per_kwh' => '1.9000', 'pricing_mode' => 'manual', 'surge_percentage' => '15.00', 'reservation_tolerance_minutes' => 15, 'no_show_penalty_points' => '10.00'], $overrides);
    }

    private function chargerPayload(array $overrides = []): array
    {
        return array_merge(['code' => 'GW-TEST-01', 'name' => 'Carregador de teste', 'location' => 'Garagem', 'power_kw' => '22.00', 'connector' => 'Type 2'], $overrides);
    }

    private function store(): Establishment
    {
        $manager = User::factory()->create(['is_admin' => true]);

        return app(EstablishmentService::class)->create($manager, $this->payload(['cnpj' => '11222333000181']));
    }

    public function test_manager_registers_one_establishment_and_unlocks_the_dashboard(): void
    {
        $manager = User::factory()->create(['is_admin' => true]);
        $this->actingAs($manager)->get(route('manager.establishment.setup'))->assertOk()->assertSee('name="cnpj"', false)->assertSee('Cadastrar estabelecimento');
        $this->post(route('manager.establishment.store'), $this->payload())->assertRedirect(route('manager.dashboard'));
        $establishment = $manager->fresh()->establishment;
        $this->assertSame($manager->id, $establishment->user_id);
        $this->assertSame('11222333000181', $establishment->cnpj);
        $this->assertSame('1.9000', $establishment->currentTariff());
        $this->assertSame('15.00', $establishment->surge_percentage);
        $this->assertDatabaseCount('establishments', 1);
        $this->assertDatabaseCount('tariff_events', 1);
        $this->get(route('manager.dashboard'))->assertOk()->assertSee($establishment->name);
        $this->get(route('manager.establishment.setup'))->assertRedirect(route('manager.establishment.edit'));
    }

    public function test_store_without_configuration_does_not_receive_business_defaults(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))->postJson(route('manager.establishment.store'), ['name' => 'Loja'])->assertUnprocessable()->assertJsonValidationErrors(['cnpj', 'cost_per_kwh', 'pricing_mode', 'surge_percentage', 'reservation_tolerance_minutes', 'no_show_penalty_points']);
        $this->assertDatabaseCount('establishments', 0);
    }

    public function test_cnpj_normalization_prevents_duplicates_across_managers(): void
    {
        $this->store();
        $this->actingAs(User::factory()->create(['is_admin' => true]))->postJson(route('manager.establishment.store'), $this->payload(['cnpj' => '11222333000181']))->assertUnprocessable()->assertJsonValidationErrors('cnpj');
        $this->assertDatabaseCount('establishments', 1);
    }

    public function test_alphanumeric_cnpj_is_supported_with_its_verifier_digits(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))->post(route('manager.establishment.store'), $this->payload(['cnpj' => '12.abc.345/01de-35']))->assertRedirect(route('manager.dashboard'));
        $this->assertDatabaseHas('establishments', ['cnpj' => '12ABC34501DE35']);
    }

    public function test_invalid_cnpj_formats_repeated_digits_and_wrong_verifiers_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        foreach (['11111111111111', '00000000000000', '11222333000180', '12ABC34501DE36', '11@222333000181', '1122233300018', '1.122.233/30001-81'] as $cnpj) {
            $this->postJson(route('manager.establishment.store'), $this->payload(['cnpj' => $cnpj]))->assertUnprocessable()->assertJsonValidationErrors('cnpj');
        }
        $this->assertDatabaseCount('establishments', 0);
    }

    public function test_manager_cannot_create_a_second_establishment_or_assign_another_responsible(): void
    {
        $establishment = $this->store();
        $this->actingAs($establishment->user)->postJson(route('manager.establishment.store'), $this->payload(['cnpj' => '04.252.011/0001-10']))->assertUnprocessable();
        $this->actingAs(User::factory()->create(['is_admin' => true]))->postJson(route('manager.establishment.store'), $this->payload(['cnpj' => '04.252.011/0001-10', 'user_id' => $establishment->user_id]))->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $this->assertDatabaseCount('establishments', 1);
    }

    public function test_financial_and_reservation_configuration_is_validated_without_negative_values(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        foreach ([['cost_per_kwh' => '-1'], ['cost_per_kwh' => '0'], ['cost_per_kwh' => '1.12345'], ['surge_percentage' => '-10'], ['surge_percentage' => '1.234'], ['cost_per_kwh' => '9999', 'surge_percentage' => '100'], ['reservation_tolerance_minutes' => 0], ['reservation_tolerance_minutes' => 1.5], ['no_show_penalty_points' => '-10'], ['no_show_penalty_points' => '1.234'], ['pricing_mode' => 'unknown']] as $invalid) {
            $this->postJson(route('manager.establishment.store'), $this->payload($invalid))->assertUnprocessable();
        }
        $this->assertDatabaseCount('establishments', 0);
    }

    public function test_zero_adjustment_and_zero_penalty_are_explicit_valid_configurations(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))->post(route('manager.establishment.store'), $this->payload(['surge_percentage' => '0', 'no_show_penalty_points' => '0']))->assertRedirect(route('manager.dashboard'));
        $this->assertDatabaseHas('establishments', ['surge_percentage' => 0, 'no_show_penalty_points' => 0]);
    }

    public function test_legacy_establishment_is_preserved_and_can_complete_missing_configuration(): void
    {
        $establishment = Establishment::factory()->create();
        $this->assertNull($establishment->cnpj);
        $this->assertNull($establishment->pricing_mode);
        $this->actingAs($establishment->user)->patch(route('manager.establishment.update'), $this->payload())->assertRedirect(route('manager.establishment.edit'));
        $this->assertSame($establishment->id, $establishment->fresh()->id);
        $this->assertSame('11222333000181', $establishment->fresh()->cnpj);
    }

    public function test_establishment_update_ignores_foreign_url_selection_and_rejects_owner_or_effective_tariff_payload(): void
    {
        $own = $this->store();
        $other = Establishment::factory()->create(['name' => 'Loja de outra pessoa']);
        $this->actingAs($own->user)->get(route('manager.establishment.edit', ['id' => $other->id]))->assertOk()->assertSee($own->name)->assertDontSee($other->name);
        $this->patchJson(route('manager.establishment.update'), $this->payload(['user_id' => $other->user_id, 'peak_tariff' => '0.01']))->assertUnprocessable()->assertJsonValidationErrors(['user_id', 'peak_tariff']);
        $this->assertSame($own->user_id, $own->fresh()->user_id);
        $this->assertSame('Loja de outra pessoa', $other->fresh()->name);
    }

    public function test_charger_is_registered_with_pending_activation_and_the_owners_establishment(): void
    {
        $establishment = $this->store();
        $this->actingAs($establishment->user)->post(route('manager.chargers.store'), $this->chargerPayload())->assertRedirect(route('manager.chargers.index'));
        $charger = Charger::firstOrFail();
        $this->assertSame($establishment->id, $charger->establishment_id);
        $this->assertSame('pending', $charger->activation_status);
        $this->assertSame('unavailable', $charger->physical_status);
        $this->assertFalse($charger->canReserve());
        $this->get(route('manager.chargers.index'))->assertOk()->assertSee('Ativação física pendente');
    }

    public function test_charger_identifiers_reject_path_delimiters_and_accept_device_style_identifiers(): void
    {
        $store = $this->store();
        $this->actingAs($store->user)->postJson(route('manager.chargers.store'), $this->chargerPayload(['code' => 'device/path']))->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->post(route('manager.chargers.store'), $this->chargerPayload(['code' => 'AA:BB:CC:01-02.03']))->assertRedirect(route('manager.chargers.index'));
        $this->assertDatabaseHas('chargers', ['code' => 'AA:BB:CC:01-02.03', 'establishment_id' => $store->id]);
    }

    public function test_knowing_another_charger_identifier_does_not_reassign_it(): void
    {
        $own = $this->store();
        $other = Charger::factory()->create(['code' => 'GW-OWNED']);
        $this->actingAs($own->user)->postJson(route('manager.chargers.store'), $this->chargerPayload(['code' => $other->code]))->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertSame($other->establishment_id, $other->fresh()->establishment_id);
        $this->assertDatabaseCount('chargers', 1);
    }

    public function test_charger_editor_cannot_change_identity_activation_physical_status_or_tariff(): void
    {
        $own = $this->store();
        $charger = Charger::factory()->for($own)->create();
        $this->actingAs($own->user)->patchJson(route('manager.chargers.update', $charger), array_merge($this->chargerPayload(), ['physical_status' => 'available', 'activation_status' => 'active', 'establishment_id' => $own->id, 'tariff_override' => '0.01']))->assertUnprocessable()->assertJsonValidationErrors(['code', 'physical_status', 'activation_status', 'establishment_id', 'tariff_override']);
        $this->assertNull($charger->fresh()->tariff_override);
        $data = $this->chargerPayload(['name' => 'Nome atualizado']);
        unset($data['code']);
        $this->patch(route('manager.chargers.update', $charger), $data)->assertRedirect(route('manager.chargers.index'));
        $this->assertSame('Nome atualizado', $charger->fresh()->name);
        $this->assertSame($charger->code, $charger->fresh()->code);
    }

    public function test_manager_cannot_read_or_edit_a_foreign_charger(): void
    {
        $own = $this->store();
        $other = Charger::factory()->create();
        $this->actingAs($own->user)->get(route('manager.chargers.edit', $other))->assertForbidden();
        $data = $this->chargerPayload();
        unset($data['code']);
        $this->patchJson(route('manager.chargers.update', $other), $data)->assertForbidden();
        $this->get(route('manager.chargers.index'))->assertDontSee($other->code);
    }

    public function test_driver_cannot_use_the_new_administrative_routes(): void
    {
        $charger = Charger::factory()->create();
        $this->actingAs(User::factory()->create());
        foreach (['manager.establishment.setup', 'manager.establishment.edit', 'manager.chargers.index', 'manager.chargers.create', 'manager.demand.index'] as $name) {
            $this->get(route($name))->assertForbidden();
        }
        $this->postJson(route('manager.establishment.store'), $this->payload())->assertForbidden();
        $this->patchJson(route('manager.establishment.update'), $this->payload())->assertForbidden();
        $this->postJson(route('manager.chargers.store'), $this->chargerPayload())->assertForbidden();
        $this->get(route('manager.chargers.edit', $charger))->assertForbidden();
    }

    public function test_manager_without_establishment_cannot_skip_registration_to_access_operations(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        foreach (['manager.establishment.edit', 'manager.chargers.index', 'manager.chargers.create', 'manager.demand.index'] as $name) {
            $this->get(route($name))->assertRedirect(route('manager.establishment.setup'));
        }
        $this->postJson(route('manager.chargers.store'), $this->chargerPayload())->assertForbidden();
    }

    public function test_portal_and_api_show_the_unique_tariff_even_with_a_legacy_override(): void
    {
        $store = $this->store();
        $a = Charger::factory()->for($store)->create(['tariff_override' => '9.0000']);
        $b = Charger::factory()->for($store)->create(['tariff_override' => '7.0000']);
        $this->assertSame('1.9000', $a->currentTariff());
        $this->assertSame('1.9000', $b->currentTariff());
        $driver = User::factory()->create(['balance' => 100]);
        $this->actingAs($driver)->get(route('chargers.index'))->assertOk()->assertSee('1,9000');
        $registered = $this->getJson('/api/chargers')->assertOk()->json('registered_chargers');
        $this->assertSame('1.9000', collect($registered)->firstWhere('id', $a->code)['tariff_per_kwh']);
        $reserve = app(ReservationService::class)->reserve($driver, $a, (string) Str::uuid());
        $session = app(MeteredChargingService::class)->startConfirmed($driver, $b, 'without-reservation');
        $this->assertSame('1.9000', $reserve->tariff_per_kwh);
        $this->assertSame('1.9000', $session->tariff_per_kwh);
        $this->assertSame('9.0000', $a->fresh()->tariff_override);
    }

    public function test_settings_update_changes_new_operations_but_preserves_reserved_price_deadline_and_penalty(): void
    {
        $store = $this->store();
        $a = Charger::factory()->for($store)->create();
        $b = Charger::factory()->for($store)->create();
        $driver = User::factory()->create(['balance' => 100, 'points' => 50]);
        $reservation = app(ReservationService::class)->reserve($driver, $a, (string) Str::uuid());
        $expires = $reservation->expires_at->toIso8601String();
        $this->actingAs($store->user)->patch(route('manager.establishment.update'), $this->payload(['cost_per_kwh' => '2.2000', 'reservation_tolerance_minutes' => 7, 'no_show_penalty_points' => '2.00']))->assertRedirect();
        $reservation->refresh();
        $this->assertSame('1.9000', $reservation->tariff_per_kwh);
        $this->assertSame(900, $reservation->tolerance_seconds);
        $this->assertSame('10.00', $reservation->no_show_penalty_points);
        $this->assertSame($expires, $reservation->expires_at->toIso8601String());
        $old = app(MeteredChargingService::class)->startConfirmed($driver, $a, 'reserved', $reservation->id);
        $new = app(MeteredChargingService::class)->startConfirmed($driver, $b, 'new');
        $this->assertSame('1.9000', $old->tariff_per_kwh);
        $this->assertSame('2.2000', $new->tariff_per_kwh);
        $this->assertSame('100.00', $driver->fresh()->balance);
        $this->assertSame('50.00', $driver->fresh()->points);
    }

    public function test_configured_no_show_penalty_is_capped_and_not_duplicated_and_cancel_remains_free(): void
    {
        $store = $this->store();
        $driver = User::factory()->create(['points' => 3]);
        $charger = Charger::factory()->for($store)->create();
        $service = app(ReservationService::class);
        $reservation = $service->reserve($driver, $charger, (string) Str::uuid());
        $this->travel(16)->minutes();
        $this->assertTrue($service->expire($reservation));
        $this->assertFalse($service->expire($reservation));
        $this->assertSame('0.00', $driver->fresh()->points);
        $this->assertSame('3.00', $reservation->fresh()->penalty_applied_points);
        $new = $service->reserve($driver, $charger->fresh(), (string) Str::uuid());
        $service->cancel($driver, $new);
        $this->assertSame('0.00', $driver->fresh()->points);
        $this->assertDatabaseCount('point_movements', 1);
    }

    public function test_physical_availability_confirmation_cannot_activate_a_pending_administrative_registration(): void
    {
        $store = $this->store();
        $charger = $store->chargers()->create($this->chargerPayload() + ['physical_status' => 'unavailable', 'activation_status' => 'pending']);
        $service = app(MeteredChargingService::class);
        $service->physicalStatusConfirmed($charger, 'available', 'status-test');
        $driver = User::factory()->create(['balance' => 100]);
        $this->assertSame('unavailable', $charger->fresh()->status());
        try {
            $service->startConfirmed($driver, $charger->fresh(), 'pending-device');
            $this->fail('Ativação pendente foi ignorada.');
        } catch (ValidationException) {
        }
        $this->assertDatabaseCount('charging_sessions', 0);
        $this->actingAs($driver)->postJson(route('reservations.store', $charger), ['idempotency_key' => (string) Str::uuid()])->assertUnprocessable();
    }

    public function test_dashboard_counts_actual_registered_statuses_and_only_confirmed_financial_revenue(): void
    {
        $store = $this->store();
        $driver = User::factory()->create();
        $transaction = Transaction::create(['user_id' => $driver->id, 'type' => 'charge_payment', 'amount' => 20, 'description' => 'Pagamento de teste', 'status' => 'approved']);
        ChargingSession::factory()->for($store)->for($driver)->create(['status' => 'completed', 'payment_status' => 'paid', 'amount_charged' => 20, 'transaction_id' => $transaction->id, 'measured_energy_kwh' => '12.3456']);
        ChargingSession::factory()->for($store)->for($driver)->create(['measured_energy_kwh' => '5.6789']);
        ChargingSession::factory()->for($store)->for($driver)->create(['status' => 'completed', 'amount_charged' => 900, 'measured_energy_kwh' => '2.0000', 'transaction_id' => null]);
        ChargingSession::factory()->create(['status' => 'completed', 'amount_charged' => 999]);
        $this->actingAs($store->user)->get(route('manager.dashboard'))->assertOk()->assertViewHas('totalRevenue', fn ($value) => (float) $value === 20.0)->assertViewHas('totalEnergy', fn ($value) => abs((float) $value - 20.0245) < 0.00001)->assertViewHas('totalSessions', 3)->assertSee('Valores pendentes não compõem a receita');
    }

    public function test_demand_page_has_no_fabricated_peaks_and_configuration_is_not_a_detector(): void
    {
        $store = $this->store();
        $this->actingAs($store->user)->get(route('manager.demand.index'))->assertOk()->assertSee('Detecção de picos aguardando critérios e integração')->assertDontSee('Pico de demanda registrado');
        $this->assertDatabaseCount('demand_peaks', 0);
        $this->assertSame('1.9000', $store->fresh()->currentTariff());
    }
}
