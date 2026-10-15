<?php

namespace Tests\Feature;

use App\Models\Establishment;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\ChargingSimulator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PortalUsuarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_pages_render_with_exactly_five_navigation_links_and_floating_chat(): void
    {
        $user = User::factory()->create();
        foreach (['user.home', 'chargers.index', 'wallet.index', 'wallet.deposit.form', 'wallet.history', 'points.index', 'user.profile', 'user.chatbot.page'] as $route) {
            $response = $this->actingAs($user)->get(route($route))->assertOk();
            $this->assertSame(5, preg_match_all('/<a[^>]+class="pt-nav-link /', $response->getContent()));
            $response->assertSee('data-chat-widget', false);
            $response->assertDontSee('href="'.route('user.chatbot.page').'"', false);
        }
    }

    public function test_guest_cannot_access_any_portal_page_or_mutation(): void
    {
        foreach (['user.home', 'chargers.index', 'wallet.index', 'points.index', 'user.profile', 'user.chatbot.history'] as $route) {
            $this->get(route($route))->assertRedirect(route('user.login'));
        }
        $this->patch(route('user.profile.update'), [])->assertRedirect(route('user.login'));
        $this->post(route('vehicles.store'), [])->assertRedirect(route('user.login'));
    }

    public function test_dashboard_uses_own_data_and_finds_pending_sessions_outside_recent_five(): void
    {
        $user = User::factory()->create(['balance' => 123.45, 'points' => 78]);
        $other = User::factory()->create();
        $pending = $user->chargingSessions()->create(['charger_name' => 'Minha pendência antiga', 'energy_kwh' => 0, 'amount_charged' => 0, 'payment_method' => 'wallet', 'status' => 'pending', 'created_at' => now()->subDay()]);
        $pending->forceFill(['created_at' => now()->subDay()])->save();
        for ($i = 0; $i < 6; $i++) {
            $user->chargingSessions()->create(['charger_name' => 'Minha recarga', 'energy_kwh' => 3, 'amount_charged' => 5, 'payment_method' => 'wallet', 'status' => 'completed']);
        }
        $other->chargingSessions()->create(['charger_name' => 'Recarga privada de outro usuário', 'energy_kwh' => 3, 'amount_charged' => 5, 'payment_method' => 'wallet', 'status' => 'completed']);
        $this->actingAs($user)->get(route('user.home'))->assertOk()
            ->assertSee('123,45')->assertSee('Minha pendência antiga')
            ->assertDontSee('Recarga privada de outro usuário')
            ->assertViewHas('totalSessions', 6)
            ->assertViewHas('sessions', fn ($sessions) => $sessions->count() === 5 && ! $sessions->contains('status', 'pending'));
    }

    public function test_user_can_edit_allowed_profile_fields_without_changing_privileges(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->patch(route('user.profile.update'), ['name' => 'Nome atualizado', 'email' => 'atualizado@example.test', 'phone' => '(11) 99999-9999', 'is_admin' => true, 'balance' => 9999])->assertRedirect()->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertSame('Nome atualizado', $user->name);
        $this->assertNull($user->email_verified_at);
        $this->assertFalse($user->isGestor());
        $this->assertEquals(0, $user->balance);
    }

    public function test_profile_rejects_duplicate_email_and_invalid_phone(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user)->patch(route('user.profile.update'), ['name' => 'Teste', 'email' => $other->email, 'phone' => 'telefone inválido'])->assertSessionHasErrors(['email', 'phone']);
    }

    public function test_profile_accepts_unchanged_email(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->patch(route('user.profile.update'), ['name' => $user->name, 'email' => $user->email, 'phone' => null])->assertSessionHasNoErrors();
    }

    public function test_vehicle_creation_normalizes_plate_and_assigns_authenticated_owner(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user)->post(route('vehicles.store'), ['brand' => 'BYD', 'model' => 'Dolphin', 'plate' => 'abc-1d23', 'battery_capacity_kwh' => 44.9, 'user_id' => $other->id])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('vehicles', ['user_id' => $user->id, 'plate' => 'ABC1D23']);
        $this->assertDatabaseMissing('vehicles', ['user_id' => $other->id]);
    }

    public function test_vehicle_rejects_invalid_or_duplicate_plate_and_invalid_capacity(): void
    {
        $user = User::factory()->create();
        Vehicle::factory()->for($user)->create(['plate' => 'ABC1D23']);
        $this->actingAs($user)->post(route('vehicles.store'), ['brand' => 'BYD', 'model' => 'Dolphin', 'plate' => 'ABC1D23', 'battery_capacity_kwh' => 0])->assertSessionHasErrors(['plate', 'battery_capacity_kwh']);
        $this->post(route('vehicles.store'), ['brand' => 'BYD', 'model' => 'Dolphin', 'plate' => 'INVÁLIDA'])->assertSessionHasErrors('plate');
    }

    public function test_vehicle_limit_is_enforced(): void
    {
        $user = User::factory()->create();
        Vehicle::factory()->count(10)->for($user)->create();
        $this->actingAs($user)->post(route('vehicles.store'), ['brand' => 'BYD', 'model' => 'Dolphin', 'plate' => 'ZZZ1A23'])->assertSessionHas('error');
        $this->assertSame(10, $user->vehicles()->count());
    }

    public function test_only_vehicle_owner_can_delete_and_see_vehicle(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $vehicle = Vehicle::factory()->for($owner)->create(['plate' => 'ZXZ9Y88']);
        $this->actingAs($other)->get(route('user.profile'))->assertDontSee('ZXZ9Y88');
        $this->delete(route('vehicles.destroy', $vehicle))->assertForbidden();
        $this->actingAs($owner)->delete(route('vehicles.destroy', $vehicle))->assertRedirect();
        $this->assertDatabaseMissing('vehicles', ['id' => $vehicle->id]);
    }

    public function test_scheduling_charges_current_backend_rate_and_records_session(): void
    {
        ChargingSimulator::release('CG-001');
        $user = User::factory()->create(['balance' => 100]);
        $this->actingAs($user)->post(route('chargers.schedule', 'CG-001'), ['idempotency_key' => (string) Str::uuid(), 'vehicle_plate' => 'ABC1D23', 'duration_minutes' => 30])->assertRedirect(route('chargers.index'))->assertSessionHas('success');
        $this->assertEquals(80.50, $user->refresh()->balance);
        $this->assertDatabaseHas('charging_sessions', ['user_id' => $user->id, 'energy_kwh' => 11.10, 'amount_charged' => 19.50]);
        ChargingSimulator::release('CG-001');
    }

    public function test_insufficient_balance_does_not_occupy_charger_or_record_payment(): void
    {
        ChargingSimulator::release('CG-001');
        $user = User::factory()->create(['balance' => 0]);
        $this->actingAs($user)->post(route('chargers.schedule', 'CG-001'), ['idempotency_key' => (string) Str::uuid(), 'vehicle_plate' => 'ABC1D23', 'duration_minutes' => 30])->assertSessionHas('error');
        $this->assertTrue(ChargingSimulator::find('CG-001')->isAvailable());
        $this->assertDatabaseCount('charging_sessions', 0);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_invalid_charging_duration_is_rejected(): void
    {
        $user = User::factory()->create(['balance' => 100]);
        $this->actingAs($user)->post(route('chargers.schedule', 'CG-001'), ['idempotency_key' => (string) Str::uuid(), 'vehicle_plate' => 'ABC1D23', 'duration_minutes' => 999])->assertSessionHasErrors('duration_minutes');
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_wallet_distinguishes_points_from_money_and_lists_only_own_transactions(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $user->transactions()->create(['type' => 'cashback', 'amount' => 10, 'description' => 'Cashback de 10% - 1000 pontos', 'status' => 'approved']);
        $user->transactions()->create(['type' => 'points_redeem', 'amount' => 5, 'description' => 'Resgate de 500 pontos - Resgate existente', 'status' => 'approved']);
        $other->transactions()->create(['type' => 'deposit', 'amount' => 999, 'description' => 'Transação privada', 'status' => 'approved']);
        $this->actingAs($user)->get(route('wallet.index'))->assertOk()->assertSee('1.000 pts')->assertDontSee('Transação privada');
        $this->get(route('wallet.history'))->assertOk()->assertSee('Detalhes')->assertSee('Resgate existente');
        $this->get(route('points.index'))->assertOk()->assertSee('Histórico de pontos')->assertDontSee('1.000 pts')->assertSee('5%')->assertSee('10%')->assertSee('15%');
    }

    public function test_history_paginates_all_sessions_instead_of_limiting_to_five(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 20; $i++) {
            $user->chargingSessions()->create(['charger_name' => 'CG-'.$i, 'energy_kwh' => 1, 'amount_charged' => 2, 'status' => 'completed', 'payment_method' => 'wallet']);
        }
        $this->actingAs($user)->get(route('wallet.history'))->assertOk()->assertViewHas('sessions', fn ($sessions) => $sessions->total() === 20 && $sessions->count() === 15);
        $this->get(route('wallet.history', ['sessions_page' => 2]))->assertOk()->assertViewHas('sessions', fn ($sessions) => $sessions->count() === 5 && ! $sessions->contains('status', 'pending'));
    }

    public function test_checkout_form_uses_existing_gateway_and_signals_unconfigured_payment(): void
    {
        $user = User::factory()->create();
        config(['mercadopago.access_token' => null]);
        $this->actingAs($user)->get(route('wallet.deposit.form'))->assertOk()->assertSee('Pagamento externo indisponível')->assertSee('action="'.route('checkout').'"', false)->assertSee('disabled', false);
        config(['mercadopago.access_token' => 'test-token']);
        $this->get(route('wallet.deposit.form'))->assertOk()->assertDontSee('Pagamento externo indisponível');
    }

    public function test_floating_chat_uses_existing_ai_and_session_history_can_be_cleared(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.openai.com/*' => Http::response(['output' => [['content' => [['type' => 'output_text', 'text' => 'Resposta da integração']]]]])]);
        $user = User::factory()->create();
        $this->actingAs($user)->postJson(route('user.chatbot'), ['message' => 'Qual meu saldo?'])->assertOk()->assertJsonPath('answer', 'Resposta da integração');
        Http::assertSent(fn ($request) => $request['input'][0]['content'] === 'Qual meu saldo?' && str_contains($request['instructions'], $user->email));
        $this->getJson(route('user.chatbot.history'))->assertOk()->assertJsonCount(2, 'history')->assertJsonPath('history.0.content', 'Qual meu saldo?');
        $this->deleteJson(route('user.chatbot.clear'))->assertOk();
        $this->getJson(route('user.chatbot.history'))->assertJsonCount(0, 'history');
    }

    public function test_chat_handles_gateway_failure_and_rejects_invalid_messages(): void
    {
        Http::fake(['api.openai.com/*' => Http::response([], 503)]);
        $user = User::factory()->create();
        $this->actingAs($user)->postJson(route('user.chatbot'), ['message' => 'Olá'])->assertStatus(500);
        $this->postJson(route('user.chatbot'), ['message' => str_repeat('x', 1001)])->assertUnprocessable();
        $this->getJson(route('user.chatbot.history'))->assertJsonCount(0, 'history');
    }

    public function test_manager_pages_keep_the_original_layout_and_user_portal_still_has_five_links(): void
    {
        $manager = Establishment::factory()->create()->user;
        $this->actingAs($manager)->get(route('manager.dashboard'))->assertOk()->assertDontSee('data-chat-widget', false)->assertDontSee('pt-shell')->assertDontSee(route('wallet.index'))->assertDontSee(route('user.profile'));
        $this->get(route('manager.chatbot.page'))->assertOk()->assertDontSee('data-chat-widget', false);
        $this->get(route('user.home'))->assertForbidden();
        $this->actingAs(User::factory()->create());
        $response = $this->get(route('user.home'))->assertOk();
        $this->assertSame(5, preg_match_all('/<a[^>]+class="pt-nav-link /', $response->getContent()));
        $this->get(route('user.profile'))->assertDontSee('Acessar painel do gestor');
    }
}
