<?php

namespace Tests\Feature;

use App\Models\Charger;
use App\Models\ChargingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReserveFromPaymentScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['reservations.fee_enabled' => true]);
    }

    public function test_payment_screen_keeps_the_standard_flow_and_shows_the_reserve_button_top_right(): void
    {
        $this->travelTo('2026-01-05 10:00:00'); // segunda-feira

        $user = User::factory()->create(['balance' => 100]);
        $charger = Charger::factory()->create();
        $session = ChargingSession::factory()->for($user)->create([
            'charger_id' => $charger->id,
            'establishment_id' => $charger->establishment_id,
        ]);

        $this->actingAs($user)->get(route('charging-sessions.show', $session))
            ->assertOk()
            // Fluxo padrão intacto: pagamento da recarga continua no card principal.
            ->assertSee('Recarga encerrada · pagamento pendente')
            ->assertSee('Confirmar pagamento')
            // Botão no canto superior direito abre o diálogo de reserva.
            ->assertSee('data-charger-open="reservar-sessao"', false)
            ->assertSee('Reservar ponto')
            ->assertSee('id="reservar-sessao"', false)
            ->assertSee('action="'.route('reservations.store', $charger->code).'"', false)
            ->assertSee('R$ 45,98')
            ->assertSee('Seg–qui +10%', false)
            ->assertSee('Sex–dom +20%', false)
            ->assertSee('Confirmar reserva · R$ 45,98', false)
            ->assertSee('não é devolvida');
    }

    public function test_legacy_session_without_registered_charger_keeps_the_button_with_guidance(): void
    {
        $user = User::factory()->create(['balance' => 100]);
        $session = ChargingSession::factory()->for($user)->create([
            'charger_id' => null,
            'charger_name' => 'Carregador legado',
            'establishment_id' => null,
        ]);

        $this->actingAs($user)->get(route('charging-sessions.show', $session))
            ->assertOk()
            ->assertSee('data-charger-open="reservar-sessao"', false)
            ->assertSee('Reservar ponto')
            ->assertSee('Ver carregadores disponíveis');
    }

    public function test_reserve_from_the_dialog_charges_the_fee_and_confirms(): void
    {
        $this->travelTo('2026-01-05 10:00:00');

        $user = User::factory()->create(['balance' => 100]);
        $charger = Charger::factory()->create();
        $session = ChargingSession::factory()->for($user)->create([
            'charger_id' => $charger->id,
            'establishment_id' => $charger->establishment_id,
        ]);

        $this->actingAs($user)
            ->from(route('charging-sessions.show', $session))
            ->post(route('reservations.store', $charger->code), ['idempotency_key' => (string) Str::uuid()])
            ->assertRedirect(route('chargers.index'))
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'R$ 45,98'));

        $reservation = $user->reservations()->sole();
        $this->assertSame('45.98', $reservation->reservation_fee);
        $this->assertSame('54.02', $user->refresh()->balance);
        $this->assertDatabaseHas('transactions', ['user_id' => $user->id, 'type' => 'reservation_fee', 'amount' => 45.98]);
    }

    public function test_reserve_click_when_charger_is_unavailable_returns_with_the_reason_on_the_same_screen(): void
    {
        $this->travelTo('2026-01-05 10:00:00');

        $user = User::factory()->create(['balance' => 100]);
        $charger = Charger::factory()->create(['physical_status' => 'in_use']);
        $session = ChargingSession::factory()->for($user)->create([
            'charger_id' => $charger->id,
            'establishment_id' => $charger->establishment_id,
        ]);

        $this->actingAs($user)
            ->from(route('charging-sessions.show', $session))
            ->post(route('reservations.store', $charger->code), ['idempotency_key' => (string) Str::uuid()])
            ->assertRedirect(route('charging-sessions.show', $session))
            ->assertSessionHas('error', 'Este carregador não está disponível para reserva.');

        $this->actingAs($user)->get(route('charging-sessions.show', $session))
            ->assertOk()
            ->assertSee('Este carregador não está disponível para reserva.')
            ->assertSee('Reservar ponto');

        $this->assertDatabaseCount('charging_reservations', 0);
        $this->assertSame('100.00', $user->refresh()->balance);
    }
}
