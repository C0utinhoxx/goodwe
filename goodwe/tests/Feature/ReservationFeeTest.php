<?php

namespace Tests\Feature;

use App\Models\Charger;
use App\Models\ChargingSession;
use App\Models\User;
use App\Services\FirestoreService;
use App\Services\ReservationService;
use App\Services\WebChargingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReservationFeeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // A suíte padrão roda com a taxa desligada (phpunit.xml) para manter
        // os demais testes determinísticos; aqui o recurso é ativado.
        config(['reservations.fee_enabled' => true]);
    }

    public function test_weekday_fee_is_charged_on_reserve_and_weekend_costs_more(): void
    {
        $this->travelTo('2026-01-05 10:00:00'); // segunda-feira

        $user = User::factory()->create(['balance' => 100]);
        $charger = Charger::factory()->create();

        app(ReservationService::class)->reserve($user, $charger, (string) Str::uuid());

        // Base: tarifa R$ 1,90/kWh × 1h (média padrão) × 22 kW = R$ 41,80.
        // Segunda a quinta: +10% => R$ 45,98.
        $reservation = $user->reservations()->sole();
        $this->assertSame('45.98', $reservation->reservation_fee);
        $this->assertSame('54.02', $user->refresh()->balance);
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => 'reservation_fee',
            'amount' => 45.98,
            'status' => 'approved',
            'reference' => 'reservation-fee:'.$reservation->id,
        ]);

        // Sexta a domingo: +20% => R$ 50,16.
        $this->travel(4)->days(); // 2026-01-09, sexta-feira
        $weekendUser = User::factory()->create(['balance' => 100]);
        $weekendCharger = Charger::factory()->create();

        app(ReservationService::class)->reserve($weekendUser, $weekendCharger, (string) Str::uuid());

        $this->assertSame('50.16', $weekendUser->reservations()->sole()->reservation_fee);
        $this->assertSame('49.84', $weekendUser->refresh()->balance);
    }

    public function test_fee_uses_average_duration_of_recent_sessions(): void
    {
        config(['reservations.fee_average_cache_seconds' => 0]);
        $this->travelTo('2026-01-05 10:00:00'); // segunda-feira

        $user = User::factory()->create(['balance' => 100]);
        $charger = Charger::factory()->create();

        // Duas recargas de 30 minutos: a média cai para meia hora.
        foreach ([90, 30] as $minutesAgo) {
            ChargingSession::factory()->create([
                'user_id' => $user->id,
                'charger_id' => $charger->id,
                'establishment_id' => $charger->establishment_id,
                'started_at' => now()->subMinutes($minutesAgo + 30),
                'ended_at' => now()->subMinutes($minutesAgo),
            ]);
        }

        app(ReservationService::class)->reserve($user, $charger, (string) Str::uuid());

        // Base: R$ 1,90 × 0,5h × 22 kW = R$ 20,90; +10% => R$ 22,99.
        $this->assertSame('22.99', $user->reservations()->sole()->reservation_fee);
        $this->assertSame('77.01', $user->refresh()->balance);
    }

    public function test_reservation_fee_requires_sufficient_wallet_balance(): void
    {
        $this->travelTo('2026-01-05 10:00:00');

        $user = User::factory()->create(['balance' => 1]);
        $charger = Charger::factory()->create();

        $this->actingAs($user)
            ->postJson(route('reservations.store', $charger->code), ['idempotency_key' => (string) Str::uuid()])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Saldo insuficiente para pagar a taxa de reserva de R$ 45,98. Adicione créditos à carteira.');

        $this->assertDatabaseCount('charging_reservations', 0);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertSame('1.00', $user->refresh()->balance);
        $this->assertSame('available', $charger->refresh()->status());
    }

    public function test_fee_is_charged_once_per_idempotency_key_and_kept_on_cancel(): void
    {
        $this->travelTo('2026-01-05 10:00:00');

        $user = User::factory()->create(['balance' => 100]);
        $charger = Charger::factory()->create();
        $service = app(ReservationService::class);
        $key = (string) Str::uuid();

        $first = $service->reserve($user, $charger, $key);
        $second = $service->reserve($user, $charger, $key);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('54.02', $user->refresh()->balance);
        $this->assertDatabaseCount('transactions', 1);

        $service->cancel($user, $first);

        $this->assertSame('cancelled', $first->refresh()->status);
        $this->assertSame('45.98', $first->reservation_fee);
        $this->assertSame('54.02', $user->refresh()->balance);
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_reservation_without_enabled_fee_charges_nothing(): void
    {
        config(['reservations.fee_enabled' => false]);

        $user = User::factory()->create(['balance' => 100]);
        $charger = Charger::factory()->create();

        app(ReservationService::class)->reserve($user, $charger, (string) Str::uuid());

        $this->assertSame('0.00', $user->reservations()->sole()->reservation_fee);
        $this->assertSame('100.00', $user->refresh()->balance);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_tag_reserved_is_synced_on_reserve_start_cancel_and_expire(): void
    {
        $this->travelTo('2026-01-05 10:00:00');
        $firestore = app(FirestoreService::class);
        $service = app(ReservationService::class);

        $user = User::factory()->create(['balance' => 100, 'tag_uid' => null]);
        $charger = Charger::factory()->create();

        $reservation = $service->reserve($user, $charger, (string) Str::uuid());
        $this->assertTrue($firestore->documents["totems/{$charger->code}"]['tag_reservada']);
        $this->assertTrue($firestore->documents["users/WEB_USER_{$user->id}"]['tag_reservada']);

        // O início da recarga consome a garantia: flag liberado nos dois documentos.
        app(WebChargingService::class)->start($user, $reservation);
        $this->assertFalse($firestore->documents["totems/{$charger->code}"]['tag_reservada']);
        $this->assertFalse($firestore->documents["users/WEB_USER_{$user->id}"]['tag_reservada']);

        // Libera o ponto fisicamente para os próximos cenários (a sessão real
        // encerraria o carregador pelo fluxo normal de recarga).
        $charger->refresh()->update(['physical_status' => 'available', 'active_session_id' => null, 'physical_status_updated_at' => now()]);

        // Cancelamento também libera o ponto para a TAG usada pelo usuário.
        $tagged = User::factory()->create(['balance' => 100, 'tag_uid' => 'TAG99']);
        $second = $service->reserve($tagged, $charger, (string) Str::uuid());
        $this->assertTrue($firestore->documents['users/TAG99']['tag_reservada']);
        $service->cancel($tagged, $second);
        $this->assertFalse($firestore->documents['users/TAG99']['tag_reservada']);

        // Ausência (expiração) libera o flag do totem sem depender do portal.
        $third = $service->reserve($tagged, $charger, (string) Str::uuid());
        $this->assertTrue($firestore->documents["totems/{$charger->code}"]['tag_reservada']);
        $this->travel(16)->minutes();
        $this->assertTrue($service->expire($third));
        $this->assertFalse($firestore->documents["totems/{$charger->code}"]['tag_reservada']);
        $this->assertFalse($firestore->documents['users/TAG99']['tag_reservada']);
    }

    public function test_firestore_failure_does_not_break_the_reservation(): void
    {
        $this->travelTo('2026-01-05 10:00:00');
        app(FirestoreService::class)->shouldFail = true;

        $user = User::factory()->create(['balance' => 100]);
        $charger = Charger::factory()->create();

        $reservation = app(ReservationService::class)->reserve($user, $charger, (string) Str::uuid());

        $this->assertSame('active', $reservation->status);
        $this->assertSame('45.98', $reservation->reservation_fee);
        $this->assertSame('54.02', $user->refresh()->balance);
        $this->assertSame($reservation->id, $charger->refresh()->active_reservation_id);
    }

    public function test_charger_dialog_shows_the_fee_before_confirmation(): void
    {
        $this->travelTo('2026-01-05 10:00:00');

        $charger = Charger::factory()->create();

        $this->actingAs(User::factory()->create(['balance' => 100]))
            ->get(route('chargers.index'))
            ->assertOk()
            ->assertSee('Taxa de reserva')
            ->assertSee('R$ 45,98', false)
            ->assertSee('Confirmar reserva · R$ 45,98', false)
            ->assertSee('não é devolvida');
    }
}
