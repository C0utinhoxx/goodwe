<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MobileViewportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * O portal não pode sofrer zoom por duplo toque/pinça no celular.
     */
    private function assertMobileZoomIsDisabled(TestResponse $response): void
    {
        $response->assertOk()
            ->assertSee('maximum-scale=1', false)
            ->assertSee('user-scalable=no', false);
    }

    public function test_guest_login_pages_block_mobile_zoom(): void
    {
        $this->assertMobileZoomIsDisabled($this->get(route('user.login')));
        $this->assertMobileZoomIsDisabled($this->get(route('manager.login')));
        $this->assertMobileZoomIsDisabled($this->get(route('user.cadastro')));
    }

    public function test_user_portal_pages_block_mobile_zoom(): void
    {
        $user = User::factory()->create();

        $this->assertMobileZoomIsDisabled($this->actingAs($user)->get(route('user.profile')));
    }

    public function test_manager_portal_pages_block_mobile_zoom(): void
    {
        $manager = User::factory()->create(['is_admin' => true]);

        $this->assertMobileZoomIsDisabled($this->actingAs($manager)->get(route('manager.establishment.setup')));
        $this->assertMobileZoomIsDisabled($this->actingAs($manager)->get(route('manager.profile')));
    }
}
