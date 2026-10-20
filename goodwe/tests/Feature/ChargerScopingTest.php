<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChargerScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_menu_hides_chargers_section_and_points_to_recharge_map(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('user.home'))->assertOk();

        // O item de menu "Carregadores" saiu da navegação do usuário.
        $this->assertStringNotContainsString('href="'.route('chargers.index').'"', $response->getContent());
        // O usuário encontra estações pelo mapa da tela "Recarga".
        $this->assertSame(1, preg_match_all('/<a[^>]+href="'.preg_quote(route('totens.index'), '/').'"[^>]+class="pt-nav-link /', $response->getContent()));
        $this->assertSame(5, preg_match_all('/<a[^>]+class="pt-nav-link /', $response->getContent()));
    }

    public function test_recharge_screen_embeds_the_charger_map_assets_and_api(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('totens.index'))->assertOk();

        // Leaflet hospedado localmente (sem dependência de CDN externo) + container do mapa.
        $response->assertSee('vendor/leaflet/1.9.4/leaflet.css', false)
            ->assertSee('vendor/leaflet/1.9.4/leaflet.js', false)
            ->assertSee('id="pt-map-canvas"', false)
            ->assertSee('css/charger-map.css', false)
            ->assertSee('js/charger-map.js', false);
        $this->assertStringNotContainsString('unpkg.com', $response->getContent());
        // Mesma API de carregadores usada pelo app NFC (chamada pelo JS do mapa).
        $this->assertStringContainsString('/api/chargers', file_get_contents(public_path('js/charger-map.js')));
    }
}
