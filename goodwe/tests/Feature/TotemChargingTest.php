<?php

namespace Tests\Feature;

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
            'comando_web' => 'espera',
        ]);
        $this->firestore->seed('totems', 'TOT-01', [
            'comando_web' => 'iniciar',
            'tarifa_kwh' => 4.99,
        ]);

        $response = $this->actingAs($user)->get(route('totens.carregar', 'TOT-01'));

        $response->assertOk();
        $response->assertSee('Maria Silva');
        $response->assertSee($tagUid, false);
        $response->assertSee('ID '.$user->id, false);
        $response->assertSee('42,50', false);   // saldo real do Firestore
        $response->assertSee('4,99', false);    // tarifa do totem
        $response->assertSee('Carregando');     // status derivado de comando_web
        $response->assertSee('Parar Carga');    // botão secundário
    }

    public function test_show_falls_back_to_local_balance_and_unknown_status(): void
    {
        $user = User::factory()->create(['balance' => 15.75]);
        $this->firestore->shouldFail = true;

        $response = $this->actingAs($user)->get(route('totens.carregar', 'TOT-01'));

        $response->assertOk();
        $response->assertSee('15,75', false);
        $response->assertSee('Sem conexão');
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

        // Nenhum comando foi enviado ao totem
        $this->assertArrayNotHasKey('totems/TOT-01', $this->firestore->documents);
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
}
