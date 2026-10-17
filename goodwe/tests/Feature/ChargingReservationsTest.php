<?php

namespace Tests\Feature;

use App\Models\Charger;
use App\Models\ChargingEvent;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChargingReservationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_reserves_now_with_server_tariff_and_tolerance_without_payment(): void
    {
        $user = User::factory()->create(['balance' => 100, 'points' => 15]);
        $charger = Charger::factory()->create();
        $charger->establishment->update(['cost_per_kwh' => '2.1234', 'reservation_tolerance_minutes' => 7, 'no_show_penalty_points' => '4.50']);
        $this->freezeTime();
        $key = (string) Str::uuid();
        $this->actingAs($user)->get(route('chargers.index'))->assertOk()->assertSee('2,1234')->assertSee($charger->establishment->name)->assertDontSee('duration_minutes', false);
        $this->post(route('reservations.store', $charger->code), ['idempotency_key' => $key])->assertRedirect(route('chargers.index'))->assertSessionHas('success');
        $reservation = $user->reservations()->sole();
        $this->assertSame('2.1234', $reservation->tariff_per_kwh);
        $this->assertSame(420, $reservation->tolerance_seconds);
        $this->assertSame(now()->addMinutes(7)->timestamp, $reservation->expires_at->timestamp);
        $this->assertSame('4.50', $reservation->no_show_penalty_points);
        $this->assertSame('reserved', $charger->refresh()->status());
        $this->assertSame('100.00', $user->refresh()->balance);
        $this->assertSame('15.00', $user->points);
        $this->assertDatabaseCount('transactions', 0);
        $this->get(route('chargers.index'))->assertSee('Tempo restante:')->assertSee('data-expires-at', false)->assertSee('Cancelar gratuitamente');
        $this->get(route('user.home'))->assertSee('Reserva confirmada: '.$charger->name);
        $this->post(route('reservations.store', $charger->code), ['idempotency_key' => $key])->assertSessionHas('success');
        $this->assertDatabaseCount('charging_reservations', 1);
        $this->assertDatabaseCount('charging_events', 1);
    }

    public function test_reserved_occupied_unavailable_and_missing_configuration_block_reservations(): void
    {
        $user = User::factory()->create();
        $charger = Charger::factory()->create();
        $this->actingAs($user);
        foreach (['in_use', 'unavailable'] as $status) {
            $charger->update(['physical_status' => $status]);
            $this->postJson(route('reservations.store', $charger->code), ['idempotency_key' => (string) Str::uuid()])->assertUnprocessable();
        }
        $charger->update(['physical_status' => 'available']);
        $charger->establishment->update(['reservation_tolerance_minutes' => null]);
        $this->postJson(route('reservations.store', $charger->code), ['idempotency_key' => (string) Str::uuid()])->assertUnprocessable();
        $this->get(route('chargers.index'))->assertSee('aguardando configuração');
        $charger->establishment->update(['reservation_tolerance_minutes' => 15, 'cost_per_kwh' => 0]);
        $this->postJson(route('reservations.store', $charger->code), ['idempotency_key' => (string) Str::uuid()])->assertUnprocessable();
        $charger->establishment->update(['cost_per_kwh' => 1.9]);
        app(ReservationService::class)->reserve($user, $charger, (string) Str::uuid());
        $other = User::factory()->create();
        $this->actingAs($other)->postJson(route('reservations.store', $charger->code), ['idempotency_key' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertDatabaseCount('charging_reservations', 1);
    }

    public function test_cancel_is_free_idempotent_and_preserves_physical_status(): void
    {
        $user = User::factory()->create(['points' => 12]);
        $charger = Charger::factory()->create();
        $service = app(ReservationService::class);
        $reservation = $service->reserve($user, $charger, (string) Str::uuid());
        $charger->update(['physical_status' => 'unavailable']);
        $this->actingAs($user)->post(route('reservations.cancel', $reservation))->assertSessionHas('success');
        $this->post(route('reservations.cancel', $reservation))->assertSessionHas('success');
        $this->assertSame('cancelled', $reservation->refresh()->status);
        $this->assertNull($charger->refresh()->active_reservation_id);
        $this->assertSame('unavailable', $charger->status());
        $this->assertSame('12.00', $user->refresh()->points);
        $this->assertDatabaseCount('point_movements', 0);
        $this->assertEquals(1, ChargingEvent::where('type', 'cancelled')->count());
        $this->travel(30)->minutes();
        $this->assertFalse($service->expire($reservation));
    }

    public function test_expiration_caps_penalty_and_is_idempotent_even_without_browser(): void
    {
        $user = User::factory()->create(['points' => 3]);
        $charger = Charger::factory()->create();
        $reservation = app(ReservationService::class)->reserve($user, $charger, (string) Str::uuid());
        $charger->establishment->update(['no_show_penalty_points' => 99, 'reservation_tolerance_minutes' => 1]);
        $this->travel(15)->minutes();
        $this->artisan('charging:expire-reservations')->assertSuccessful();
        $this->artisan('charging:expire-reservations')->assertSuccessful();
        $this->assertSame('expired', $reservation->refresh()->status);
        $this->assertSame('10.00', $reservation->no_show_penalty_points);
        $this->assertSame('3.00', $reservation->penalty_applied_points);
        $this->assertSame('0.00', $user->refresh()->points);
        $this->assertSame('available', $charger->refresh()->status());
        $this->assertDatabaseHas('point_movements', ['event_key' => 'reservation-no-show:'.$reservation->id, 'amount' => -3]);
        $this->assertDatabaseCount('point_movements', 1);
        $this->assertEquals(1, ChargingEvent::where('type', 'no_show')->count());
        $this->actingAs($user)->get(route('points.index'))->assertSee('Ausência na reserva #'.$reservation->id);
    }

    public function test_zero_points_and_unconfigured_penalty_record_absence_without_debt(): void
    {
        $user = User::factory()->create(['points' => 0]);
        $service = app(ReservationService::class);
        foreach ([null, '10.00'] as $penalty) {
            $charger = Charger::factory()->create();
            $charger->establishment->update(['no_show_penalty_points' => $penalty]);
            $reservation = $service->reserve($user, $charger, (string) Str::uuid());
            $charger->update(['physical_status' => 'in_use']);
            $this->travel(16)->minutes();
            $this->assertTrue($service->expire($reservation));
            $this->assertSame('0.00', $reservation->refresh()->penalty_applied_points);
            $this->assertSame('0.00', $user->refresh()->points);
            $this->assertSame('in_use', $charger->refresh()->status());
        }
        $this->assertDatabaseCount('point_movements', 1);
        $this->assertEquals(2, ChargingEvent::where('type', 'no_show')->count());
    }

    public function test_late_cancel_cannot_bypass_no_show_and_other_user_cannot_access_reservation(): void
    {
        $user = User::factory()->create(['points' => 20]);
        $other = User::factory()->create();
        $charger = Charger::factory()->create();
        $reservation = app(ReservationService::class)->reserve($user, $charger, (string) Str::uuid());
        $this->actingAs($other)->post(route('reservations.cancel', $reservation))->assertForbidden();
        $this->getJson(route('reservations.status', $reservation))->assertForbidden();
        $this->travel(16)->minutes();
        $this->actingAs($user)->postJson(route('reservations.cancel', $reservation))->assertUnprocessable();
        $this->assertSame('expired', $reservation->refresh()->status);
        $this->assertSame('10.00', $user->refresh()->points);
        $this->getJson(route('reservations.status', $reservation))->assertOk()->assertJsonPath('reservation.status', 'expired');
    }

    public function test_future_duration_and_client_tariff_are_rejected_and_guests_cannot_reserve(): void
    {
        $charger = Charger::factory()->create();
        $this->post(route('reservations.store', $charger->code), [])->assertRedirect(route('user.login'));
        $this->actingAs(User::factory()->create())->postJson(route('reservations.store', $charger->code), ['idempotency_key' => (string) Str::uuid(), 'duration_minutes' => 30, 'starts_at' => now()->addDay()->toIso8601String(), 'tariff_per_kwh' => 0.01])->assertUnprocessable()->assertJsonValidationErrors(['duration_minutes', 'starts_at', 'tariff_per_kwh']);
        $this->assertDatabaseCount('charging_reservations', 0);
    }

    public function test_cancelling_ends_tariff_guarantee_and_fresh_reservation_uses_new_tariff(): void
    {
        $user = User::factory()->create();
        $charger = Charger::factory()->create();
        $service = app(ReservationService::class);
        $reservation = $service->reserve($user, $charger, (string) Str::uuid());
        $service->cancel($user, $reservation);
        $charger->establishment->update(['cost_per_kwh' => '2.2000']);
        $new = $service->reserve($user, $charger, (string) Str::uuid());
        $this->assertSame('1.9000', $reservation->refresh()->tariff_per_kwh);
        $this->assertSame('2.2000', $new->tariff_per_kwh);
    }

    public function test_registered_equipment_cannot_be_started_or_released_by_legacy_simulator_endpoints(): void
    {
        $charger = Charger::factory()->create();
        $user = User::factory()->create(['balance' => 100]);
        $this->actingAs($user, 'sanctum')->postJson('/api/chargers/'.$charger->code.'/start', ['energy_kwh' => 30, 'amount' => 57, 'charge_type' => 'full', 'idempotency_key' => (string) Str::uuid()])->assertStatus(503)->assertJsonPath('integration_pending', true);
        $this->postJson('/api/chargers/'.$charger->code.'/release')->assertStatus(503);
        $this->getJson('/api/chargers')->assertOk()->assertJsonPath('registered_chargers.0.tariff_per_kwh', '1.9000')->assertJsonPath('chargers.0.source', 'simulation');
        $this->assertDatabaseCount('charging_sessions', 0);
        $this->assertSame('100.00', $user->refresh()->balance);
        $this->assertSame('available', $charger->refresh()->status());
    }
}
