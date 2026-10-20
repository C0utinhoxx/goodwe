<?php

namespace Tests\Feature;

use App\Models\Charger;
use App\Models\ChargingReservation;
use App\Models\Establishment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChargerListingTest extends TestCase
{
    use RefreshDatabase;

    private function charger(array $overrides = []): Charger
    {
        return Charger::factory()->create($overrides);
    }

    private function reservedCharger(array $overrides = []): Charger
    {
        $charger = $this->charger($overrides);
        $reservation = ChargingReservation::factory()->create([
            'charger_id' => $charger->id,
            'establishment_id' => $charger->establishment_id,
        ]);
        $charger->update(['active_reservation_id' => $reservation->id]);

        return $charger->fresh();
    }

    public function test_sql_status_expression_matches_the_php_helper(): void
    {
        $chargers = [
            $this->charger(),
            $this->charger(['physical_status' => 'unavailable']),
            $this->charger(['activation_status' => 'pending']),
            $this->charger(['physical_status' => 'in_use']),
            $this->reservedCharger(),
        ];

        // É o que garante que o filtro da listagem e as estatísticas da home
        // (que filtram no banco) não divergem de Charger::status() (PHP).
        foreach ($chargers as $charger) {
            $this->assertSame(
                $charger->status(),
                Charger::query()
                    ->whereKey($charger->id)
                    ->selectRaw(Charger::STATUS_SQL.' as computed_status')
                    ->value('computed_status'),
                "Divergência de status para o carregador #{$charger->id}"
            );
        }
    }

    public function test_listing_filters_by_status_search_and_power_on_the_server(): void
    {
        $establishment = Establishment::factory()->create();
        $this->charger(['establishment_id' => $establishment->id, 'name' => 'Torre Norte A', 'power_kw' => 22]);
        $inUse = $this->charger(['establishment_id' => $establishment->id, 'name' => 'Torre Norte B', 'power_kw' => 11, 'physical_status' => 'in_use']);
        $reserved = $this->reservedCharger(['establishment_id' => $establishment->id, 'name' => 'Torre Sul C', 'power_kw' => 7]);
        $unavailable = $this->charger(['establishment_id' => $establishment->id, 'name' => 'Garagem D', 'power_kw' => 50, 'physical_status' => 'unavailable']);

        $user = User::factory()->create();

        $this->actingAs($user)->get(route('chargers.index'))
            ->assertOk()
            ->assertViewHas('chargers', fn ($chargers) => $chargers->total() === 4);

        $this->actingAs($user)->get(route('chargers.index', ['status' => 'in_use']))
            ->assertViewHas('chargers', fn ($chargers) => $chargers->pluck('id')->all() === [$inUse->id]);

        $this->actingAs($user)->get(route('chargers.index', ['status' => 'reserved']))
            ->assertViewHas('chargers', fn ($chargers) => $chargers->pluck('id')->all() === [$reserved->id]);

        $this->actingAs($user)->get(route('chargers.index', ['status' => 'unavailable']))
            ->assertViewHas('chargers', fn ($chargers) => $chargers->pluck('id')->all() === [$unavailable->id]);

        // Busca por nome, localização ou estabelecimento.
        $this->actingAs($user)->get(route('chargers.index', ['q' => 'torre norte']))
            ->assertViewHas('chargers', fn ($chargers) => $chargers->total() === 2);

        $this->actingAs($user)->get(route('chargers.index', ['q' => $establishment->name]))
            ->assertViewHas('chargers', fn ($chargers) => $chargers->total() === 4);

        // Filtro de potência.
        $this->actingAs($user)->get(route('chargers.index', ['power' => 11]))
            ->assertViewHas('chargers', fn ($chargers) => $chargers->pluck('id')->all() === [$inUse->id]);

        // Valor inválido de status cai no comportamento sem filtro.
        $this->actingAs($user)->get(route('chargers.index', ['status' => 'qualquer-coisa']))
            ->assertViewHas('chargers', fn ($chargers) => $chargers->total() === 4);
    }

    public function test_listing_is_paginated_instead_of_loading_every_charger(): void
    {
        Charger::factory()->count(30)->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('chargers.index'));

        $response->assertOk();
        $response->assertViewHas('chargers', fn ($chargers) => $chargers->total() === 30 && $chargers->count() === 24);
        $response->assertSee('page=2');

        $this->actingAs($user)->get(route('chargers.index', ['page' => 2]))
            ->assertViewHas('chargers', fn ($chargers) => $chargers->count() === 6);
    }

    public function test_empty_results_distinguish_filters_from_an_empty_catalogue(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('chargers.index'))
            ->assertOk()
            ->assertSee('Nenhum carregador cadastrado para reservas');

        $this->charger();

        $this->actingAs($user)->get(route('chargers.index', ['q' => 'inexistente']))
            ->assertOk()
            ->assertSee('Nenhum carregador corresponde aos filtros selecionados')
            ->assertDontSee('Nenhum carregador cadastrado para reservas');
    }
}
