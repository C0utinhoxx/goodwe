<?php

namespace Tests\Feature;

use App\Models\AtendimentoHumano;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SupportDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function supportUser(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_support' => true])->save();

        return $user;
    }

    private function ticket(User $owner, string $status, string $subject): AtendimentoHumano
    {
        return AtendimentoHumano::create([
            'user_id' => $owner->id,
            'mensagem' => 'Descrição do problema: '.$subject,
            'motivo' => $subject,
            'status' => $status,
        ]);
    }

    public function test_support_dashboard_lists_tickets_with_aggregated_totals(): void
    {
        $owner = User::factory()->create();
        $this->ticket($owner, 'aguardando', 'Pagamento recusado');
        $this->ticket($owner, 'resolvido', 'Dúvida de tarifa');

        $response = $this->actingAs($this->supportUser())->get(route('support.dashboard'));

        $response->assertOk();
        $response->assertViewHas('tickets', fn ($tickets) => $tickets->total() === 2);
        $response->assertViewHas('totais', fn (array $totais): bool => $totais === [
            'todos' => 2,
            'aguardando' => 1,
            'em_atendimento' => 0,
            'resolvido' => 1,
        ]);
        $response->assertSee('Pagamento recusado');
        $response->assertSee($owner->name);
    }

    public function test_status_filter_limits_the_list_without_changing_totals(): void
    {
        $owner = User::factory()->create();
        $this->ticket($owner, 'aguardando', 'Carregador sem resposta');
        $this->ticket($owner, 'resolvido', 'Cupom não aplicado');

        $response = $this->actingAs($this->supportUser())
            ->get(route('support.dashboard', ['status' => 'aguardando']));

        $response->assertOk();
        $response->assertViewHas('tickets', fn ($tickets) => $tickets->total() === 1);
        $response->assertDontSee('Cupom não aplicado');
        // Os totais continuam globais (é o cartão de resumo da categoria).
        $response->assertViewHas('totais', fn (array $totais): bool => $totais['todos'] === 2);
    }

    public function test_totals_come_from_a_single_aggregate_query(): void
    {
        $owner = User::factory()->create();
        $this->ticket($owner, 'aguardando', 'Falha ao iniciar recarga');
        $this->ticket($owner, 'em_atendimento', 'Saldo não creditado');

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->actingAs($this->supportUser())->get(route('support.dashboard'))->assertOk();

        $ticketQueries = array_values(array_filter(
            $queries,
            fn (string $sql): bool => str_contains($sql, 'atendimento_humanos')
        ));

        // 1 agregado de totais + contagem e leitura da paginação.
        $this->assertCount(1, array_filter(
            $ticketQueries,
            fn (string $sql): bool => str_contains($sql, 'as todos')
        ));
        $this->assertLessThanOrEqual(3, count($ticketQueries));
    }

    public function test_guests_and_regular_users_cannot_open_the_support_dashboard(): void
    {
        $this->get(route('support.dashboard'))->assertRedirect(route('user.login'));

        $this->actingAs(User::factory()->create())
            ->get(route('support.dashboard'))
            ->assertForbidden();
    }
}
