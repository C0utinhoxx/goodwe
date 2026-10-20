<?php

namespace Tests\Feature;

use App\Models\ChargingSession;
use App\Models\Establishment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ManagerChatbotContextTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function managerWithSessions(): Establishment
    {
        $manager = User::factory()->create(['is_admin' => true]);
        $establishment = Establishment::create([
            'user_id' => $manager->id,
            'name' => 'GoodWe Assistente',
            'address' => 'Rua do Atendimento, 10',
            'type' => Establishment::TYPE_COMERCIAL,
            'operating_hours' => '08:00 - 22:00',
            'cost_per_kwh' => 1.5,
        ]);

        $driver = User::factory()->create();
        foreach (range(1, 3) as $index) {
            ChargingSession::create([
                'user_id' => $driver->id,
                'establishment_id' => $establishment->id,
                'charger_name' => "Torre {$index}",
                'energy_kwh' => 10,
                'amount_charged' => 15,
                'payment_method' => ChargingSession::PAYMENT_WALLET,
                'status' => ChargingSession::STATUS_COMPLETED,
            ]);
        }

        return $establishment;
    }

    private function fakeOpenAi(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'id' => 'resp_teste',
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode([
                            'acao' => 'responder',
                            'resposta' => 'Sua instalação tem 3 sessões concluídas.',
                        ]),
                    ]],
                ]],
            ]),
        ]);
    }

    public function test_chat_context_builds_the_aggregates_in_a_single_query(): void
    {
        $establishment = $this->managerWithSessions();
        $this->fakeOpenAi();

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $response = $this->actingAs($establishment->user)
            ->postJson(route('manager.chatbot'), ['message' => 'Quantas sessões tive hoje?']);

        $response->assertOk()->assertJsonPath('answer', 'Sua instalação tem 3 sessões concluídas.');

        // Um único SELECT agregado (sessões + contagens por status + somatórios)
        // no lugar das seis consultas separadas que existiam antes.
        $this->assertCount(1, array_filter(
            $queries,
            fn (string $sql): bool => str_contains($sql, 'COUNT(*) as total_sessions')
        ));
        $this->assertLessThanOrEqual(1, count(array_filter(
            $queries,
            fn (string $sql): bool => str_contains($sql, 'from "charging_sessions"') && str_contains($sql, 'count(')
        )));
    }

    public function test_chat_context_is_built_even_when_the_assistant_is_unavailable(): void
    {
        $establishment = $this->managerWithSessions();
        Http::fake(['api.openai.com/*' => Http::response('', 500)]);

        $this->actingAs($establishment->user)
            ->postJson(route('manager.chatbot'), ['message' => 'Relatório de faturamento'])
            ->assertStatus(502);
    }
}
