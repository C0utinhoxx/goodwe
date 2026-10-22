<?php

namespace Tests\Feature;

use App\Models\Charger;
use App\Models\ChargingReservation;
use App\Models\ChargingSession;
use App\Models\Establishment;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AccountSeparationTest extends TestCase
{
    use RefreshDatabase;

    private function registration(array $overrides = []): array
    {
        return array_merge(['name' => 'Gestora de teste', 'email' => 'gestora@example.test', 'phone' => '11987654321', 'password' => 'secret123', 'password_confirmation' => 'secret123'], $overrides);
    }

    public function test_public_pages_offer_separate_logins_and_registration_actions(): void
    {
        $this->get(route('user.login'))->assertOk()->assertSee(route('manager.login'))->assertSee('action="'.route('login-user').'"', false);
        $this->get(route('manager.login'))->assertOk()->assertSee('action="'.route('manager.login.store').'"', false)->assertSee(route('user.password.recovery'));
        $this->get(route('manager.register'))->assertOk()->assertSee('action="'.route('manager.register.store').'"', false)->assertSee('password_confirmation');
    }

    public function test_manager_self_registers_and_reaches_setup_without_creating_an_establishment(): void
    {
        $this->post(route('manager.register.store'), $this->registration(['is_admin' => false, 'balance' => 999, 'points' => 999, 'establishment_id' => 123]))
            ->assertRedirect(route('manager.establishment.setup'));
        $manager = User::where('email', 'gestora@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($manager);
        $this->assertTrue($manager->isGestor());
        $this->assertTrue(Hash::check('secret123', $manager->password));
        $this->assertSame('0.00', $manager->balance);
        $this->assertSame('0.00', $manager->points);
        $this->assertDatabaseCount('establishments', 0);

        $this->get(route('manager.establishment.setup'))
        ->assertOk()
        ->assertSee(__('bulk.cadastrar_estabelecimento'));

    }

    public function test_manager_registration_uses_existing_validation_and_global_email_uniqueness(): void
    {
        User::factory()->create(['email' => 'existing@example.test']);
        $this->post(route('manager.register.store'), $this->registration(['email' => 'existing@example.test', 'phone' => 'abc', 'password_confirmation' => 'wrong']))
            ->assertSessionHasErrors(['email', 'phone', 'password_confirmation']);
        $this->assertDatabaseCount('users', 1);
        $this->assertGuest();
    }

    public function test_user_registration_cannot_assign_manager_or_financial_permissions(): void
    {
        $this->post(route('user-store'), $this->registration(['is_admin' => true, 'role' => 'manager', 'balance' => 999, 'points' => 999]))->assertRedirect(route('user.login'));
        $user = User::where('email', 'gestora@example.test')->firstOrFail();
        $this->assertFalse($user->isGestor());
        $this->assertSame('0.00', $user->balance);
        $this->assertSame('0.00', $user->points);
        $this->assertGuest();
    }

    public function test_user_login_rejects_manager_even_with_correct_password(): void
    {
        $manager = User::factory()->create(['is_admin' => true]);
        $this->post(route('login-user'), ['email' => $manager->email, 'password' => 'password', 'is_admin' => true])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_manager_login_rejects_driver_even_with_correct_password(): void
    {
        $user = User::factory()->create();
        $this->post(route('manager.login.store'), ['email' => $user->email, 'password' => 'password', 'is_admin' => false])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_driver_login_uses_driver_home_despite_tampered_profile_or_intended_url(): void
    {
        $user = User::factory()->create();
        $this->withSession(['url.intended' => route('manager.dashboard')])->post(route('login-user'), ['email' => $user->email, 'password' => 'password', 'is_admin' => true])->assertRedirect(route('user.home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_manager_login_without_establishment_redirects_to_setup(): void
    {
        $manager = User::factory()->create(['is_admin' => true]);
        $this->withSession(['url.intended' => route('wallet.index')])->post(route('manager.login.store'), ['email' => $manager->email, 'password' => 'password'])->assertRedirect(route('manager.establishment.setup'));
        $this->assertAuthenticatedAs($manager);
        $this->assertDatabaseCount('establishments', 0);
    }

    public function test_existing_manager_with_establishment_reaches_dashboard_and_keeps_historical_balances(): void
    {
        $manager = User::factory()->create(['is_admin' => true, 'balance' => 125, 'points' => 35]);
        $establishment = Establishment::factory()->for($manager)->create();
        $this->post(route('manager.login.store'), ['email' => $manager->email, 'password' => 'password'])->assertRedirect(route('manager.dashboard'));
        $this->get(route('manager.establishment.setup'))->assertRedirect(route('manager.establishment.edit'));
        $this->get(route('manager.dashboard'))->assertOk()->assertSee($establishment->name);
        $this->assertSame('125.00', $manager->fresh()->balance);
        $this->assertSame('35.00', $manager->fresh()->points);
    }

    public function test_root_and_guest_pages_redirect_authenticated_accounts_to_their_own_portal(): void
    {
        foreach ([User::factory()->create(), User::factory()->create(['is_admin' => true]), Establishment::factory()->create()->user] as $account) {
            $this->actingAs($account);
            foreach (['/', route('user.login'), route('user.cadastro'), route('manager.login'), route('manager.register')] as $url) {
                $this->get($url)->assertRedirect(route($account->portalRoute()));
            }
        }
    }

    public function test_guests_are_directed_to_the_login_for_the_requested_environment(): void
    {
        $this->get(route('manager.dashboard'))->assertRedirect(route('manager.login'));
        $this->get(route('manager.establishment.setup'))->assertRedirect(route('manager.login'));
        $this->get(route('user.home'))->assertRedirect(route('user.login'));
        $this->getJson('/api/wallet')->assertUnauthorized();
    }

    public function test_user_cannot_access_any_administrative_route(): void
    {
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->create());
        foreach (['manager.dashboard', 'manager.dashboard.export', 'manager.establishment.setup', 'manager.chatbot.page'] as $name) {
            $this->get(route($name))->assertForbidden();
        }
        $this->postJson(route('manager.chatbot'), ['message' => 'dados'])->assertForbidden();
        $this->deleteJson(route('manager.chatbot.clear'))->assertForbidden();
    }

    public function test_manager_cannot_access_or_mutate_any_personal_web_feature(): void
    {
        Http::preventStrayRequests();
        $manager = User::factory()->create(['is_admin' => true, 'balance' => 100, 'points' => 50]);
        $reservation = ChargingReservation::factory()->for($manager)->create();
        $session = ChargingSession::factory()->for($manager)->create();
        $vehicle = $manager->vehicles()->create(['brand' => 'BYD', 'model' => 'Dolphin', 'plate' => 'ABC1D23']);
        $this->actingAs($manager);
        foreach (['user.home', 'chargers.index', 'wallet.index', 'wallet.deposit.form', 'wallet.history', 'points.index', 'user.profile', 'user.chatbot.page', 'user.chatbot.history'] as $name) {
            $this->get(route($name))->assertForbidden();
        }
        $this->get(route('charging-sessions.show', $session))->assertForbidden();
        $this->get(route('reservations.status', $reservation))->assertForbidden();
        foreach ([route('wallet.deposit.store'), route('points.redeem'), route('vehicles.store'), route('user.chatbot'), route('checkout'), route('reservations.store', $reservation->charger), route('reservations.cancel', $reservation), route('charging-sessions.pay', $session), route('chargers.schedule', 'CG-001'), route('chargers.release', 'CG-001')] as $url) {
            $this->postJson($url, [])->assertForbidden();
        }
        $this->patchJson(route('user.profile.update'), ['is_admin' => false])->assertForbidden();
        $this->deleteJson(route('vehicles.destroy', $vehicle))->assertForbidden();
        $this->deleteJson(route('user.chatbot.clear'))->assertForbidden();
        foreach (['pagamento.sucesso', 'pagamento.erro', 'pagamento.pendente'] as $name) {
            $this->get(route($name))->assertForbidden();
        }
        $this->assertSame('100.00', $manager->fresh()->balance);
        $this->assertSame('50.00', $manager->fresh()->points);
        $this->assertTrue($manager->fresh()->isGestor());
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_profile_update_cannot_promote_user_or_modify_financial_fields(): void
    {
        $user = User::factory()->create(['balance' => 100, 'points' => 25]);
        $this->actingAs($user)->patch(route('user.profile.update'), ['name' => 'Novo nome', 'email' => $user->email, 'phone' => $user->phone, 'is_admin' => true, 'balance' => 999, 'points' => 999])->assertRedirect();
        $this->assertFalse($user->fresh()->isGestor());
        $this->assertSame('100.00', $user->fresh()->balance);
        $this->assertSame('25.00', $user->fresh()->points);
        $this->assertSame('Novo nome', $user->fresh()->name);
    }

    public function test_manager_without_establishment_cannot_access_any_administrative_data(): void
    {
        Http::preventStrayRequests();
        $other = Establishment::factory()->create();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        foreach (['manager.dashboard', 'manager.dashboard.export', 'manager.chatbot.page'] as $name) {
            $this->get(route($name))->assertRedirect(route('manager.establishment.setup'));
            $this->getJson(route($name))->assertForbidden();
        }
        $this->postJson(route('manager.chatbot'), ['message' => 'dados'])->assertForbidden();
        $this->deleteJson(route('manager.chatbot.clear'))->assertForbidden();
        $this->get(route('manager.establishment.setup'))->assertOk()->assertDontSee($other->name);
        $this->assertDatabaseCount('establishments', 1);
        Http::assertNothingSent();
    }

    public function test_database_prevents_multiple_establishment_links_without_deleting_the_existing_one(): void
    {
        $manager = User::factory()->create(['is_admin' => true]);
        $establishment = Establishment::factory()->for($manager)->create();
        try {
            Establishment::factory()->for($manager)->create();
            $this->fail('Foi permitido vincular um segundo estabelecimento.');
        } catch (UniqueConstraintViolationException) {
            $this->assertDatabaseCount('establishments', 1);
            $this->assertDatabaseHas('establishments', ['id' => $establishment->id, 'user_id' => $manager->id]);
        }
    }

    public function test_dashboard_and_csv_only_include_the_managers_establishment(): void
    {
        $own = Establishment::factory()->create();
        $other = Establishment::factory()->create();
        $customer = User::factory()->create(['name' => 'Cliente da loja']);
        $payment = Transaction::create(['user_id' => $customer->id, 'type' => 'charge_payment', 'amount' => 17, 'description' => 'Pagamento de teste', 'status' => 'approved']);
        ChargingSession::factory()->for($own)->for($customer)->create(['transaction_id' => $payment->id, 'charger_name' => 'Recarga própria', 'status' => 'completed', 'amount_charged' => 17, 'energy_kwh' => 10]);
        ChargingSession::factory()->for($other)->create(['charger_name' => 'Recarga sigilosa', 'status' => 'completed', 'amount_charged' => 900, 'energy_kwh' => 500]);
        Charger::factory()->for($own)->create(['name' => 'Carregador próprio']);
        Charger::factory()->for($other)->create(['name' => 'Carregador sigiloso']);
        $response = $this->actingAs($own->user)->get(route('manager.dashboard', ['establishment_id' => $other->id]));
        $response->assertOk()->assertSee('Recarga própria')->assertSee('Carregador próprio')->assertDontSee('Recarga sigilosa')->assertDontSee('Carregador sigiloso')->assertDontSee($other->name)->assertViewHas('totalSessions', 1)->assertViewHas('totalUsers', 1)->assertViewHas('totalRevenue', fn ($amount) => (float) $amount === 17.0);
        $csv = $this->get(route('manager.dashboard.export', ['establishment_id' => $other->id]))->assertOk()->streamedContent();
        $this->assertStringContainsString('Recarga própria', $csv);
        $this->assertStringNotContainsString('Recarga sigilosa', $csv);
    }

    public function test_manager_assistant_sends_only_scoped_operations_and_discards_old_global_history(): void
    {
        $own = Establishment::factory()->create();
        $other = Establishment::factory()->create();
        $customer = User::factory()->create(['name' => 'Cliente próprio', 'balance' => 987.65, 'points' => 345.67]);
        ChargingSession::factory()->for($own)->for($customer)->create(['charger_name' => 'Sessão da loja']);
        ChargingSession::factory()->for($other)->create(['charger_name' => 'Sessão secreta']);
        Transaction::create(['user_id' => $customer->id, 'type' => 'deposit', 'amount' => 100, 'description' => 'Depósito pessoal secreto', 'status' => 'approved']);
        Charger::factory()->for($own)->create(['name' => 'Equipamento da loja']);
        Charger::factory()->for($other)->create(['name' => 'Equipamento secreto']);
        Http::fake(['api.openai.com/*' => Http::response(['output' => [['content' => [['type' => 'output_text', 'text' => 'Resposta segura']]]]])]);
        $this->actingAs($own->user)->withSession(['manager_chat_history' => [['role' => 'assistant', 'content' => 'Histórico global secreto']]])
            ->postJson(route('manager.chatbot'), ['message' => 'Resumo', 'establishment_id' => $other->id])->assertOk()->assertJsonPath('answer', 'Resposta segura');
        Http::assertSent(function ($request) use ($other) {
            $context = $request['instructions'];
            $this->assertStringContainsString('Sessão da loja', $context);
            $this->assertStringContainsString('Equipamento da loja', $context);
            foreach (['Sessão secreta', 'Equipamento secreto', 'Depósito pessoal secreto', $other->name, '987.65', '345.67', 'Saldo da carteira:'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $context);
            }
            $this->assertCount(1, $request['input']);

            return true;
        });
        $this->get(route('manager.chatbot.page'))->assertOk()->assertSee('Resposta segura')->assertDontSee('Histórico global secreto');
        $this->get(route('manager.dashboard'))->assertOk()->assertSee('Resposta segura')->assertDontSee('Histórico global secreto');
        $this->deleteJson(route('manager.chatbot.clear'))->assertOk();
        $this->get(route('manager.chatbot.page'))->assertDontSee('Resposta segura');
    }

    public function test_manager_login_api_is_rejected_without_creating_token_or_session(): void
    {
        $manager = User::factory()->create(['is_admin' => true]);
        $this->postJson('/api/login', ['email' => $manager->email, 'password' => 'password'])->assertUnauthorized();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertGuest('web');
    }

    public function test_api_registration_cannot_create_manager_or_credit_wallet(): void
    {
        $this->postJson('/api/register', $this->registration(['is_admin' => true, 'balance' => 999, 'points' => 999]))->assertCreated()->assertJsonPath('user.is_admin', false);
        $user = User::where('email', 'gestora@example.test')->firstOrFail();
        $this->assertFalse($user->isGestor());
        $this->assertSame('0.00', $user->balance);
        $this->assertSame('0.00', $user->points);
    }

    public function test_existing_manager_token_cannot_use_personal_apis_but_can_log_out(): void
    {
        $manager = User::factory()->create(['is_admin' => true]);
        $reservation = ChargingReservation::factory()->for($manager)->create();
        $session = ChargingSession::factory()->for($manager)->create();
        $token = $manager->createToken('nfc-charger-app')->plainTextToken;
        $this->withToken($token);
        foreach (['/api/user', '/api/wallet', '/api/wallet/sessions', '/api/wallet/points', '/api/reservations/'.$reservation->id] as $url) {
            $this->getJson($url)->assertForbidden();
        }
        foreach (['/api/wallet/deposit', '/api/wallet/redeem-points', '/api/chargers/CG-001/start', '/api/chargers/CG-001/schedule', '/api/chargers/CG-001/release', '/api/chargers/'.$reservation->charger->code.'/reserve', '/api/reservations/'.$reservation->id.'/cancel', '/api/sessions/'.$session->id.'/pay'] as $url) {
            $this->postJson($url, [])->assertForbidden();
        }
        $this->postJson('/api/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_driver_api_login_is_stateless_and_preserves_mobile_contract_and_logout(): void
    {
        $user = User::factory()->create();
        $response = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk()->assertJsonStructure(['message', 'token', 'user' => ['id', 'name', 'email', 'phone', 'balance', 'points', 'is_admin']]);
        $this->assertGuest('web');
        $this->withToken($response->json('token'))->getJson('/api/wallet')->assertOk();
        $this->postJson('/api/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_logout_ends_the_session_and_returns_to_the_corresponding_login(): void
    {
        foreach ([false, true] as $isManager) {
            $user = User::factory()->create(['is_admin' => $isManager]);
            $this->actingAs($user)->withSession(['manager_chat_history' => ['secret'], 'password_recovery_user_id' => $user->id]);
            $this->post(route('logout'))->assertRedirect(route($isManager ? 'manager.login' : 'user.login'))->assertSessionMissing('manager_chat_history')->assertSessionMissing('password_recovery_user_id');
            $this->assertGuest();
        }
    }

    public function test_manager_can_recover_password_without_changing_profile_or_creating_establishment(): void
    {
        $manager = User::factory()->create(['is_admin' => true]);
        $this->post(route('user.password.verify'), ['email' => $manager->email, 'phone' => $manager->phone])->assertSessionHas('success');
        $this->post(route('user.password.update'), ['password' => 'newsecret', 'password_confirmation' => 'newsecret'])->assertRedirect(route('manager.login'));
        $this->assertTrue(Hash::check('newsecret', $manager->fresh()->password));
        $this->assertTrue($manager->fresh()->isGestor());
        $this->assertDatabaseCount('establishments', 0);
    }

    public function test_api_logout_also_accepts_session_authentication_without_a_persistent_token(): void
    {
        $manager = User::factory()->create(['is_admin' => true]);
        $this->actingAs($manager, 'web')->postJson('/api/logout')->assertOk();
        $this->assertGuest('web');
    }

    public function test_authentication_rejects_non_scalar_credentials_and_registration_fields(): void
    {
        $user = User::factory()->create();
        foreach (['login-user', 'manager.login.store', 'api.login'] as $name) {
            $this->postJson(route($name), ['email' => $user->email, 'password' => array_fill(0, 6, 'x')])->assertUnprocessable()->assertJsonValidationErrors('password');
        }
        $this->postJson(route('manager.register.store'), $this->registration(['name' => ['malformed'], 'password' => array_fill(0, 6, 'x')]))->assertUnprocessable()->assertJsonValidationErrors(['name', 'password']);
        $this->assertDatabaseCount('users', 1);
        $this->assertGuest();
    }

    public function test_repeated_login_attempts_are_throttled_without_authenticating(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('manager.login.store'), ['email' => $user->email, 'password' => 'wrongpassword'])->assertSessionHasErrors('email');
        }
        $this->post(route('manager.login.store'), ['email' => $user->email, 'password' => 'password'])->assertStatus(429);
        $this->assertGuest();
    }
}
