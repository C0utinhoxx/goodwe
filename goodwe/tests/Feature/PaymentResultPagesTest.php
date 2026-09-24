<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentResultPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_success_page_renders_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('pagamento.sucesso'));

        $response->assertOk();
        $response->assertViewIs('pagamento.sucesso');
    }

    public function test_error_page_renders_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('pagamento.erro'));

        $response->assertOk();
        $response->assertViewIs('pagamento.erro');
    }

    public function test_pending_page_renders_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('pagamento.pendente'));

        $response->assertOk();
        $response->assertViewIs('pagamento.pendente');
    }

    public function test_guest_cannot_access_result_pages(): void
    {
        $response = $this->get(route('pagamento.sucesso'));

        $response->assertRedirect(route('user.login'));
    }
}
