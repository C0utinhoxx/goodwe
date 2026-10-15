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

    public function test_chargers_section_links_to_totem_control(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('chargers.index'));

        // O controle do totem é acessado a partir da seção de recarga
        $response->assertOk();
        $response->assertSee(__('totens.open_control'));
        $response->assertSee(route('totens.carregar', 'CG-001'), false);
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
}
