<?php

namespace Tests\Feature;

use App\Models\Charger;
use App\Models\ChargingSession;
use App\Models\Establishment;
use App\Models\User;
use App\Services\FirestoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeFirestoreService;
use Tests\TestCase;

class TotemChargingTest extends TestCase
{
    use RefreshDatabase;

    private FakeFirestoreService $firestore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->firestore = new FakeFirestoreService;
        $this->app->instance(FirestoreService::class, $this->firestore);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('totens.carregar', 'TOT-01'))
            ->assertRedirect(route('user.login'));
    }

    public function test_recharge_page_lists_totems_and_links_to_control(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('totens.index'));

        // A página "Recarga" é o ponto de entrada para o controle do totem
        $response->assertOk();
        $response->assertSee(__('totens.title'));
        $response->assertSee(__('totens.open_recharge'));
        $response->assertSee('Lab Goodwe 01');
        $response->assertSee('Lab Goodwe 02');
        $response->assertDontSee('Totem 01');
        $response->assertDontSee('Totem 02');
        $response->assertSee('totem_01', false);
        $response->assertSee('totem_02', false);
        $response->assertSee(route('totens.carregar', 'totem_01'), false);
        $response->assertSee(route('totens.carregar', 'totem_02'), false);
    }

    public function test_recharge_page_never_links_using_the_simulator_code(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('totens.index'));

        // O id do simulador (CG-00X) não existe na coleção `totens` do Firestore,
        // então não pode virar link de controle remoto.
        foreach (['CG-001', 'CG-002', 'CG-003', 'CG-004'] as $code) {
            $response->assertDontSee('/carregar/'.$code, false);
        }
    }

    public function test_recharge_page_lists_every_configured_totem(): void
    {
        config()->set('totems.available', [
            ['id' => 'totem_09', 'name' => 'Totem 09', 'location' => 'Teste'],
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)->get(route('totens.index'))
            ->assertOk()
            ->assertSee('Totem 09')
            ->assertSee('totem_09', false)
            ->assertDontSee('totem_01', false);
    }

    public function test_guest_is_redirected_from_recharge_page(): void
    {
        $this->get(route('totens.index'))->assertRedirect(route('user.login'));
    }

    public function test_portal_sidebar_exposes_recharge_entry(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('totens.index'))
            ->assertOk()
            ->assertSee(__('nav.recharge'))
            ->assertSee(route('totens.index'), false);
    }

    public function test_show_displays_dynamic_user_data_from_firestore(): void
    {
        $user = User::factory()->create([
            'name' => 'Maria Silva',
            'balance' => 0,
        ]);

        $tagUid = "WEB_USER_{$user->id}";

        $this->firestore->seed('users', $tagUid, [
            'saldo_pontos' => 42.50,
            'status_tag' => 'ativo',
            'comando_web' => 'iniciar',
        ]);
        // O espelho do totem tem outro valor: a tela deve priorizar o canal
        // que o ESP consome (users/{TAG}.comando_web)
        $this->firestore->seed('totems', 'TOT-01', [
            'comando_web' => 'espera',
            'tarifa_kwh' => 4.99,
        ]);

        $response = $this->actingAs($user)->get(route('totens.carregar', 'TOT-01'));

        $response->assertOk();
        $response->assertSee('Maria Silva');
        $response->assertSee($tagUid, false);
        $response->assertSee(__('totens.user_id').': '.$user->id, false);
        $response->assertSee('42,50', false);                          // saldo real do Firestore
        $response->assertSee('4,99', false);                           // tarifa do totem
        $response->assertSee(__('totens.status_charging'));            // status lido do canal do ESP
        $response->assertSee(__('totens.stop_button'));                // botão secundário
        $response->assertSee(route('chargers.index'), false);          // volta para a seção de recarga
        $response->assertSee(route('totens.iniciar', 'TOT-01'), false);
        $response->assertSee(route('totens.parar', 'TOT-01'), false);
    }

    public function test_show_falls_back_to_totem_status_when_tag_has_no_command(): void
    {
        $user = User::factory()->create();
        $tagUid = "WEB_USER_{$user->id}";

        // Documento da TAG existe mas sem comando_web => usa o espelho do totem
        $this->firestore->seed('users', $tagUid, ['saldo_pontos' => 10]);
        $this->firestore->seed('totems', 'TOT-01', ['comando_web' => 'parar']);

        $this->actingAs($user)->get(route('totens.carregar', 'TOT-01'))
            ->assertOk()
            ->assertSee(__('totens.status_stopped'));
    }

    public function test_show_falls_back_to_local_balance_and_unknown_status(): void
    {
        $user = User::factory()->create(['balance' => 15.75]);
        $this->firestore->shouldFail = true;

        $response = $this->actingAs($user)->get(route('totens.carregar', 'TOT-01'));

        $response->assertOk();
        $response->assertSee('15,75', false);
        $response->assertSee(__('totens.status_offline'));
    }

    public function test_iniciar_sends_dynamic_tag_and_start_command(): void
    {
        // Cria um usuário antes para garantir que o ID do teste não seja 1
        User::factory()->create();

        $user = User::factory()->create(['balance' => 30]);
        $tagUid = "WEB_USER_{$user->id}";

        $this->firestore->seed('users', $tagUid, ['saldo_pontos' => 30]);

        $response = $this->actingAs($user)
            ->from(route('totens.carregar', 'TOT-01'))
            ->post(route('totens.iniciar', 'TOT-01'));

        $response->assertRedirect(route('totens.carregar', 'TOT-01'));
        $response->assertSessionHas('success');

        $totem = $this->firestore->documents['totems/TOT-01'];

        $this->assertSame('iniciar', $totem['comando_web']);
        $this->assertSame($tagUid, $totem['tag_uid']);
        $this->assertNotSame('WEB_USER_1', $totem['tag_uid']);

        // Comando entregue ao ESP no canal users/{TAG}.comando_web
        $this->assertSame('iniciar', $this->firestore->documents["users/{$tagUid}"]['comando_web']);
    }

    public function test_iniciar_targets_physical_tag_document_when_registered(): void
    {
        $user = User::factory()->create(['balance' => 20]);
        $user->tag_uid = 'CARD-77';
        $user->save();

        $this->actingAs($user)->post(route('totens.iniciar', 'TOT-01'))
            ->assertSessionHas('success');

        // O comando vai para o documento da TAG física (canal do ESP)...
        $this->assertSame('iniciar', $this->firestore->documents['users/CARD-77']['comando_web']);
        // ...e a TAG é a que aparece espelhada no totem
        $this->assertSame('CARD-77', $this->firestore->documents['totems/TOT-01']['tag_uid']);
        // Nada foi gravado no documento WEB_USER
        $this->assertArrayNotHasKey("users/WEB_USER_{$user->id}", $this->firestore->documents);
    }

    public function test_iniciar_creates_firestore_user_document_when_missing(): void
    {
        $user = User::factory()->create(['balance' => 10]);
        $tagUid = "WEB_USER_{$user->id}";

        $this->assertNull($this->firestore->getDocumentData('users', $tagUid));

        $this->actingAs($user)->post(route('totens.iniciar', 'TOT-01'))
            ->assertSessionHas('success');

        $created = $this->firestore->documents["users/{$tagUid}"];

        $this->assertSame($user->name, $created['nome']);
        $this->assertSame(10.0, $created['saldo_pontos']);
        $this->assertSame('ativo', $created['status_tag']);
        $this->assertSame('iniciar', $created['comando_web']);
        $this->assertSame('iniciar', $this->firestore->documents['totems/TOT-01']['comando_web']);
    }

    public function test_iniciar_blocks_user_without_balance_in_firestore(): void
    {
        $user = User::factory()->create(['balance' => 0]);

        $this->firestore->seed('users', "WEB_USER_{$user->id}", [
            'saldo_pontos' => 0,
        ]);

        $response = $this->actingAs($user)
            ->from(route('totens.carregar', 'TOT-01'))
            ->post(route('totens.iniciar', 'TOT-01'));

        $response->assertRedirect(route('totens.carregar', 'TOT-01'));
        $response->assertSessionHas('error');

        // Nenhum comando foi enviado ao totem nem ao canal do ESP
        $this->assertArrayNotHasKey('totems/TOT-01', $this->firestore->documents);
        $this->assertArrayNotHasKey(
            'comando_web',
            $this->firestore->documents["users/WEB_USER_{$user->id}"]
        );
    }

    public function test_iniciar_syncs_stale_firestore_balance_from_wallet(): void
    {
        // Firestore desatualizado (0) mas carteira com saldo => espelha a carteira
        $user = User::factory()->create(['balance' => 25]);
        $tagUid = "WEB_USER_{$user->id}";

        $this->firestore->seed('users', $tagUid, ['saldo_pontos' => 0]);

        $this->actingAs($user)->post(route('totens.iniciar', 'TOT-01'))
            ->assertSessionHas('success');

        $this->assertSame(25.0, $this->firestore->documents["users/{$tagUid}"]['saldo_pontos']);
        $this->assertSame('iniciar', $this->firestore->documents['totems/TOT-01']['comando_web']);
    }

    public function test_parar_sends_stop_command_to_totem(): void
    {
        $user = User::factory()->create();

        $this->firestore->seed('totems', 'TOT-01', ['comando_web' => 'iniciar']);

        $response = $this->actingAs($user)
            ->from(route('totens.carregar', 'TOT-01'))
            ->post(route('totens.parar', 'TOT-01'));

        $response->assertRedirect(route('totens.carregar', 'TOT-01'));
        $response->assertSessionHas('success');

        $this->assertSame('parar', $this->firestore->documents['totems/TOT-01']['comando_web']);

        // Canal do ESP
        $this->assertSame(
            'parar',
            $this->firestore->documents["users/WEB_USER_{$user->id}"]['comando_web']
        );
    }

    public function test_parar_reports_firestore_failure(): void
    {
        $user = User::factory()->create();
        $this->firestore->shouldFail = true;

        $this->actingAs($user)
            ->from(route('totens.carregar', 'TOT-01'))
            ->post(route('totens.parar', 'TOT-01'))
            ->assertSessionHas('error');
    }

    public function test_iniciar_resets_pin_digitado_to_empty_string(): void
    {
        $user = User::factory()->create(['balance' => 30]);
        $tagUid = "WEB_USER_{$user->id}";

        // Totem com PIN de sessão anterior
        $this->firestore->seed('totems', 'TOT-01', [
            'comando_web' => 'espera',
            'pin_digitado' => '1234',
        ]);

        $this->actingAs($user)
            ->from(route('totens.carregar', 'TOT-01'))
            ->post(route('totens.iniciar', 'TOT-01'))
            ->assertSessionHas('success');

        // O pin_digitado deve ser resetado para string vazia
        $this->assertSame('', $this->firestore->documents['totems/TOT-01']['pin_digitado']);
    }

    public function test_enviar_pin_updates_pin_digitado_field(): void
    {
        $user = User::factory()->create();

        $this->firestore->seed('totems', 'TOT-01', [
            'comando_web' => 'aguardando_pin',
            'pin_gerado' => '5678',
            'pin_digitado' => '',
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('totens.pin', 'TOT-01'), ['pin' => '5678']);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        // Verifica que apenas o campo pin_digitado foi atualizado
        $totem = $this->firestore->documents['totems/TOT-01'];
        $this->assertSame('5678', $totem['pin_digitado']);
        $this->assertSame('aguardando_pin', $totem['comando_web']);
        $this->assertSame('5678', $totem['pin_gerado']);
    }

    public function test_enviar_pin_validates_numeric_4_digits(): void
    {
        $user = User::factory()->create();

        // PIN com letras
        $this->actingAs($user)
            ->postJson(route('totens.pin', 'TOT-01'), ['pin' => '12ab'])
            ->assertStatus(422);

        // PIN com menos de 4 dígitos
        $this->actingAs($user)
            ->postJson(route('totens.pin', 'TOT-01'), ['pin' => '123'])
            ->assertStatus(422);

        // PIN com mais de 4 dígitos
        $this->actingAs($user)
            ->postJson(route('totens.pin', 'TOT-01'), ['pin' => '12345'])
            ->assertStatus(422);

        // PIN vazio
        $this->actingAs($user)
            ->postJson(route('totens.pin', 'TOT-01'), ['pin' => ''])
            ->assertStatus(422);
    }

    public function test_enviar_pin_reports_firestore_failure(): void
    {
        $user = User::factory()->create();
        $this->firestore->shouldFail = true;

        $response = $this->actingAs($user)
            ->postJson(route('totens.pin', 'TOT-01'), ['pin' => '1234']);

        $response->assertStatus(500);
        $response->assertJson(['success' => false]);
    }

    public function test_status_returns_current_totem_state(): void
    {
        $user = User::factory()->create();

        $this->firestore->seed('totems', 'TOT-01', [
            'comando_web' => 'aguardando_pin',
            'pin_gerado' => '4321',
            'tag_uid' => 'WEB_USER_1',
            'tarifa_kwh' => 6.99,
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('totens.status', 'TOT-01'));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'comando_web' => 'aguardando_pin',
            'pin_gerado' => '4321',
            'tag_uid' => 'WEB_USER_1',
            'tarifa_kwh' => 6.99,
        ]);
    }

    public function test_status_returns_empty_comando_when_totem_not_found(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('totens.status', 'TOT-99'));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'comando_web' => '',
        ]);
    }

    public function test_status_is_cached_until_totem_writes_invalidate_it(): void
    {
        $user = User::factory()->create();
        config()->set('totems.status_cache_seconds', 60);

        $this->firestore->seed('totems', 'TOT-01', [
            'comando_web' => 'iniciar',
            'pin_gerado' => '9999',
            'tarifa_kwh' => 2.5,
        ]);

        $this->actingAs($user)->getJson(route('totens.status', 'TOT-01'))
            ->assertOk()
            ->assertJsonPath('comando_web', 'iniciar');

        // Mudança no Firestore ainda dentro do TTL: o polling segue no cache.
        $this->firestore->seed('totems', 'TOT-01', [
            'comando_web' => 'parar',
            'pin_gerado' => '9999',
            'tarifa_kwh' => 2.5,
        ]);

        $this->actingAs($user)->getJson(route('totens.status', 'TOT-01'))
            ->assertJsonPath('comando_web', 'iniciar');

        // Um comando enviado pela web grava no totem e invalida o cache.
        $this->firestore->seed('totems', 'TOT-01', [
            'comando_web' => 'iniciar',
            'pin_gerado' => '9999',
            'tarifa_kwh' => 2.5,
        ]);
        $this->actingAs($user)->postJson(route('totens.pin', 'TOT-01'), ['pin' => '5678'])
            ->assertOk();
        $this->assertSame('5678', $this->firestore->documents['totems/TOT-01']['pin_digitado']);

        $this->actingAs($user)->getJson(route('totens.status', 'TOT-01'))
            ->assertJsonPath('comando_web', 'iniciar');
    }

    public function test_status_reports_firestore_failure(): void
    {
        $user = User::factory()->create();
        $this->firestore->shouldFail = true;

        $response = $this->actingAs($user)
            ->getJson(route('totens.status', 'TOT-01'));

        $response->assertStatus(500);
        $response->assertJson(['success' => false]);
    }

    public function test_view_contains_pin_modal_and_polling_script(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('totens.carregar', 'TOT-01'));

        $response->assertOk();
        // Modal de PIN presente no HTML
        $response->assertSee('id="pin-modal"', false);
        $response->assertSee('id="pin-form"', false);
        $response->assertSee('id="pin-input"', false);
        // Script de polling presente
        $response->assertSee('POLLING_INTERVAL', false);
        $response->assertSee('aguardando_pin', false);
        $response->assertSee('STATUS_URL', false);
        $response->assertSee('PIN_URL', false);
    }

    public function test_iniciar_registers_the_recharge_in_the_managers_dashboard(): void
    {
        $manager = User::factory()->create(['is_admin' => true]);
        $establishment = Establishment::factory()->for($manager)->create();
        Charger::factory()->for($establishment)->create([
            'code' => 'totem_01',
            'name' => 'Lab Goodwe 01',
        ]);

        $user = User::factory()->create(['balance' => 30]);
        $this->firestore->seed('totems', 'totem_01', ['tarifa_kwh' => 1.9]);

        $this->actingAs($user)->post(route('totens.iniciar', 'totem_01'))
            ->assertSessionHas('success');

        // A recarga existe no banco, vinculada ao estabelecimento do gestor...
        $session = ChargingSession::where('user_id', $user->id)->firstOrFail();
        $this->assertSame($establishment->id, $session->establishment_id);
        $this->assertSame('Lab Goodwe 01', $session->charger_name);
        $this->assertSame('pending_pin', $session->charging_state);
        $this->assertSame(ChargingSession::STATUS_PENDING, $session->status);
        // ...e o espelho não interfere no canal do equipamento.
        $this->assertSame('iniciar', $this->firestore->documents['totems/totem_01']['comando_web']);

        $this->actingAs($manager)
            ->get(route('manager.dashboard'))
            ->assertOk()
            ->assertSee('Lab Goodwe 01')
            ->assertViewHas('periodSessionsCount', 1);
    }

    public function test_polling_promotes_the_recharge_to_charging_state(): void
    {
        $user = User::factory()->create(['balance' => 30]);

        $this->actingAs($user)->post(route('totens.iniciar', 'totem_01'));
        $this->assertSame('pending_pin', ChargingSession::firstOrFail()->charging_state);

        // O ESP valida o PIN e passa a "carregando": o polling acompanha.
        $this->firestore->seed('totems', 'totem_01', ['comando_web' => 'carregando']);
        $this->actingAs($user)->getJson(route('totens.status', 'totem_01'))->assertOk();

        $session = ChargingSession::firstOrFail();
        $this->assertSame('charging', $session->charging_state);
        $this->assertSame(ChargingSession::STATUS_PENDING, $session->status);
    }

    public function test_parar_closes_the_recharge_with_the_totem_measurements(): void
    {
        $manager = User::factory()->create(['is_admin' => true]);
        $establishment = Establishment::factory()->for($manager)->create();
        Charger::factory()->for($establishment)->create(['code' => 'totem_01', 'name' => 'Lab Goodwe 01']);

        $user = User::factory()->create(['balance' => 30]);
        $this->firestore->seed('totems', 'totem_01', ['tarifa_kwh' => 1.9]);

        $this->actingAs($user)->post(route('totens.iniciar', 'totem_01'));

        // O totem reporta a medição final ao encerrar.
        $this->firestore->seed('totems', 'totem_01', [
            'comando_web' => 'carregando',
            'energia_kwh' => 12.5,
            'valor_total' => 23.75,
        ]);

        $this->actingAs($user)->post(route('totens.parar', 'totem_01'))
            ->assertSessionHas('success');

        $session = ChargingSession::firstOrFail();
        $this->assertSame('ended', $session->charging_state);
        $this->assertSame(ChargingSession::STATUS_COMPLETED, $session->status);
        $this->assertSame('12.5000', $session->measured_energy_kwh);
        $this->assertSame('23.75', $session->amount_charged);
        $this->assertSame('23.75', $session->original_amount);
        $this->assertNotNull($session->ended_at);

        $this->actingAs($manager)
            ->get(route('manager.dashboard'))
            ->assertOk()
            ->assertViewHas('periodEnergy', 12.5)
            ->assertViewHas('chargerDistribution', fn ($rows) => (int) $rows->sum('total') === 1);
    }

    public function test_parar_falls_back_to_the_totem_tariff_when_no_measurement_is_reported(): void
    {
        $user = User::factory()->create(['balance' => 30]);
        $this->firestore->seed('totems', 'totem_01', ['tarifa_kwh' => 2]);

        $this->actingAs($user)->post(route('totens.iniciar', 'totem_01'));
        $this->firestore->seed('totems', 'totem_01', ['comando_web' => 'carregando', 'energia_kwh' => 10]);

        $this->actingAs($user)->post(route('totens.parar', 'totem_01'));

        // 10 kWh x R$ 2,00 (tarifa registrada na abertura da sessão).
        $session = ChargingSession::firstOrFail();
        $this->assertSame('10.0000', $session->measured_energy_kwh);
        $this->assertSame('20.00', $session->amount_charged);
    }

    public function test_totem_recharge_without_a_registered_charger_stays_out_of_the_manager_views(): void
    {
        $manager = User::factory()->create(['is_admin' => true]);
        $establishment = Establishment::factory()->for($manager)->create();

        $user = User::factory()->create(['balance' => 30]);
        $this->actingAs($user)->post(route('totens.iniciar', 'totem_01'))->assertSessionHas('success');

        $session = ChargingSession::firstOrFail();
        $this->assertNull($session->establishment_id);
        $this->assertNull($session->charger_id);
        $this->assertSame('Lab Goodwe 01', $session->charger_name);

        $this->actingAs($manager)
            ->get(route('manager.dashboard'))
            ->assertOk()
            ->assertViewHas('periodSessionsCount', 0)
            ->assertDontSee('Lab Goodwe 01');
    }

    public function test_iniciar_reuses_the_open_recharge_instead_of_duplicating_it(): void
    {
        $user = User::factory()->create(['balance' => 30]);

        $this->actingAs($user)->post(route('totens.iniciar', 'totem_01'));
        $this->actingAs($user)->post(route('totens.iniciar', 'totem_01'));

        $this->assertSame(1, ChargingSession::where('user_id', $user->id)->count());
    }

    public function test_parar_without_an_open_recharge_does_not_create_a_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('totens.parar', 'totem_01'))
            ->assertSessionHas('success');

        $this->assertSame(0, ChargingSession::count());
    }

    public function test_recharge_is_still_recorded_when_the_session_has_no_measurement(): void
    {
        $user = User::factory()->create(['balance' => 30]);

        $this->actingAs($user)->post(route('totens.iniciar', 'totem_01'));
        $this->actingAs($user)->post(route('totens.parar', 'totem_01'));

        $session = ChargingSession::firstOrFail();
        $this->assertSame('ended', $session->charging_state);
        $this->assertSame('0.0000', $session->measured_energy_kwh);
        $this->assertSame('0.00', $session->amount_charged);
    }

    public function test_manager_tariff_change_is_mirrored_to_the_totem_documents(): void
    {
        $manager = User::factory()->create(['is_admin' => true]);
        $establishment = Establishment::factory()->for($manager)->create([
            'cnpj' => '11222333000181',
            'phone' => '11987654321',
        ]);
        Charger::factory()->for($establishment)->create(['code' => 'totem_01']);
        Charger::factory()->for($establishment)->create(['code' => 'totem_02', 'tariff_override' => '2.5000']);

        // A tela de recarga já tinha sido aberta com o valor antigo (cache).
        $this->firestore->seed('totems', 'totem_01', ['tarifa_kwh' => 1.9]);
        $this->firestore->seed('totems', 'totem_02', ['tarifa_kwh' => 2.5]);
        $this->actingAs(User::factory()->create())
            ->get(route('totens.carregar', 'totem_01'))
            ->assertSee('1,90', false);

        $this->actingAs($manager)
            ->patch(route('manager.establishment.update'), $this->establishmentPayload($establishment, ['cost_per_kwh' => '2.2000']))
            ->assertRedirect(route('manager.establishment.edit'));

        // A tarifa-base nova chega ao totem que a herda...
        $this->assertSame(2.2, $this->firestore->documents['totems/totem_01']['tarifa_kwh']);
        // ...e o equipamento com tarifa própria mantém a sua.
        $this->assertSame(2.5, $this->firestore->documents['totems/totem_02']['tarifa_kwh']);

        // A tela e o polling passam a mostrar o novo valor (cache invalidado).
        $this->actingAs(User::factory()->create())
            ->get(route('totens.carregar', 'totem_01'))
            ->assertSee('2,20', false);
        $this->actingAs(User::factory()->create())
            ->getJson(route('totens.status', 'totem_01'))
            ->assertJsonPath('tarifa_kwh', 2.2);
    }

    public function test_manager_charger_tariff_override_is_mirrored_only_to_its_own_totem(): void
    {
        $manager = User::factory()->create(['is_admin' => true]);
        $establishment = Establishment::factory()->for($manager)->create();
        $charger = Charger::factory()->for($establishment)->create(['code' => 'totem_01']);
        Charger::factory()->for($establishment)->create(['code' => 'totem_02']);

        $this->actingAs($manager)->patch(route('manager.chargers.update', $charger), [
            'name' => $charger->name,
            'location' => $charger->location,
            'power_kw' => (string) $charger->power_kw,
            'connector' => $charger->connector,
            'tariff_override' => '3.7500',
        ])->assertRedirect(route('manager.chargers.index'));

        $this->assertSame(3.75, $this->firestore->documents['totems/totem_01']['tarifa_kwh']);
        $this->assertArrayNotHasKey('totems/totem_02', $this->firestore->documents);
    }

    public function test_show_reads_tag_and_totem_in_a_single_batch(): void
    {
        $user = User::factory()->create(['balance' => 10]);
        $tagUid = "WEB_USER_{$user->id}";

        $this->firestore->seed('users', $tagUid, ['saldo_pontos' => 10]);
        $this->firestore->seed('totems', 'TOT-01', ['comando_web' => 'espera', 'tarifa_kwh' => 2.5]);

        $this->actingAs($user)->get(route('totens.carregar', 'TOT-01'))->assertOk();

        $this->assertSame(1, $this->firestore->batchReadCalls);
    }

    public function test_iniciar_reads_and_writes_firestore_in_single_batches(): void
    {
        $user = User::factory()->create(['balance' => 30]);
        $tagUid = "WEB_USER_{$user->id}";

        $this->firestore->seed('users', $tagUid, ['saldo_pontos' => 30]);
        $this->firestore->seed('totems', 'TOT-01', ['tarifa_kwh' => 2.5]);

        $this->actingAs($user)
            ->postJson(route('totens.iniciar', 'TOT-01'))
            ->assertOk()
            ->assertJson(['success' => true]);

        // A TAG e o totem são lidos em um BatchGet e gravados em um BatchWrite.
        $this->assertSame(1, $this->firestore->batchReadCalls);
        $this->assertSame(1, $this->firestore->batchWriteCalls);
        $this->assertSame('iniciar', $this->firestore->documents["users/{$tagUid}"]['comando_web']);
        $this->assertSame('iniciar', $this->firestore->documents['totems/TOT-01']['comando_web']);
    }

    public function test_iniciar_returns_json_error_when_balance_is_insufficient(): void
    {
        $user = User::factory()->create(['balance' => 0]);
        $this->firestore->seed('users', "WEB_USER_{$user->id}", ['saldo_pontos' => 0]);

        $this->actingAs($user)
            ->postJson(route('totens.iniciar', 'TOT-01'))
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => __('totens.no_balance'),
            ]);

        $this->assertArrayNotHasKey('totems/TOT-01', $this->firestore->documents);
    }

    public function test_parar_returns_json_without_redirecting_for_ajax(): void
    {
        $user = User::factory()->create();
        $this->firestore->seed('totems', 'TOT-01', ['comando_web' => 'carregando']);

        $this->actingAs($user)
            ->postJson(route('totens.parar', 'TOT-01'))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame('parar', $this->firestore->documents['totems/TOT-01']['comando_web']);
    }

    public function test_status_uses_fresh_reads_by_default_so_the_pin_appears_without_delay(): void
    {
        $this->assertSame(0, config('totems.status_cache_seconds'));

        $user = User::factory()->create();
        $this->firestore->seed('totems', 'TOT-01', ['comando_web' => 'iniciar']);

        $this->actingAs($user)->getJson(route('totens.status', 'TOT-01'))
            ->assertJsonPath('comando_web', 'iniciar');

        // O ESP grava "aguardando_pin" e o polling enxerga na hora.
        $this->firestore->seed('totems', 'TOT-01', [
            'comando_web' => 'aguardando_pin',
            'pin_gerado' => '4321',
        ]);

        $this->actingAs($user)->getJson(route('totens.status', 'TOT-01'))
            ->assertJsonPath('comando_web', 'aguardando_pin')
            ->assertJsonPath('pin_gerado', '4321');
    }

    public function test_polling_does_not_rewrite_the_session_after_it_starts_charging(): void
    {
        $user = User::factory()->create(['balance' => 30]);

        $this->actingAs($user)->post(route('totens.iniciar', 'totem_01'));
        $this->firestore->seed('totems', 'totem_01', ['comando_web' => 'carregando']);

        $this->actingAs($user)->getJson(route('totens.status', 'totem_01'))->assertOk();

        $session = ChargingSession::firstOrFail();
        $startedAt = $session->started_at;

        $this->travel(5)->seconds();
        $this->actingAs($user)->getJson(route('totens.status', 'totem_01'))->assertOk();

        // O polling não pode reiniciar o horário real da recarga a cada consulta.
        $this->assertTrue($startedAt->equalTo($session->fresh()->started_at));
    }

    public function test_recharge_view_is_wired_for_ajax_commands_and_fast_polling(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('totens.carregar', 'TOT-01'))
            ->assertOk()
            ->assertSee('data-totem-command="start"', false)
            ->assertSee('data-totem-command="stop"', false)
            ->assertSee('data-totem-feedback', false)
            ->assertSee('const IDLE_POLLING_INTERVAL', false)
            ->assertSee('const FAST_POLL_STATES', false)
            ->assertSee('1000', false);
    }

    public function test_manager_tariff_change_survives_a_firestore_outage(): void
    {
        $manager = User::factory()->create(['is_admin' => true]);
        $establishment = Establishment::factory()->for($manager)->create([
            'cnpj' => '11222333000181',
            'phone' => '11987654321',
        ]);
        Charger::factory()->for($establishment)->create(['code' => 'totem_01']);

        $this->firestore->shouldFail = true;

        $this->actingAs($manager)
            ->patch(route('manager.establishment.update'), $this->establishmentPayload($establishment, ['cost_per_kwh' => '2.2000']))
            ->assertRedirect(route('manager.establishment.edit'));

        // A tarifa local é a fonte de cobrança; o Firestore é espelho.
        $this->assertSame('2.2000', $establishment->fresh()->cost_per_kwh);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function establishmentPayload(Establishment $establishment, array $overrides = []): array
    {
        return array_merge([
            'name' => $establishment->name,
            'cnpj' => $establishment->cnpj,
            'address' => $establishment->address,
            'phone' => $establishment->phone,
            'type' => $establishment->type,
            'operating_hours' => $establishment->operating_hours,
            'cost_per_kwh' => (string) $establishment->cost_per_kwh,
            'pricing_mode' => $establishment->pricing_mode ?? 'manual',
            'surge_percentage' => (string) ($establishment->surge_percentage ?? '15.00'),
            'reservation_tolerance_minutes' => $establishment->reservation_tolerance_minutes,
            'no_show_penalty_points' => (string) $establishment->no_show_penalty_points,
        ], $overrides);
    }
}
