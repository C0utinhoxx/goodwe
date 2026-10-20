<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_recovery_page_is_accessible(): void
    {
        $response = $this->get(route('user.password.recovery'));

        $response->assertOk()
            ->assertSee('name="password"', false)
            ->assertSee('disabled', false);
    }

    public function test_recovery_verifies_identity_with_email_and_phone(): void
    {
        $user = User::factory()->create(['phone' => '11987654321']);

        $response = $this->post(route('user.password.verify'), [
            'email' => $user->email,
            'phone' => $user->phone,
        ]);

        $response->assertSessionHas('success');
        $this->assertTrue(session('password_recovery_user_id') === $user->id);
    }

    public function test_recovery_fails_when_phone_does_not_match(): void
    {
        $user = User::factory()->create(['phone' => '11987654321']);

        $response = $this->post(route('user.password.verify'), [
            'email' => $user->email,
            'phone' => '11999999999',
        ]);

        $response->assertSessionHasErrors('phone');
        $this->assertNull(session('password_recovery_user_id'));
    }

    public function test_password_is_updated_after_successful_verification(): void
    {
        $user = User::factory()->create(['phone' => '11987654321']);

        $this->withSession([
            'password_recovery_user_id' => $user->id,
            'password_recovery_verified' => true,
        ]);

        $response = $this->post(route('user.password.update'), [
            'password' => 'nova123',
            'password_confirmation' => 'nova123',
        ]);

        $response->assertRedirect(route('user.login'));

        $this->assertTrue(Hash::check('nova123', $user->fresh()->password));
    }

    public function test_password_cannot_be_changed_without_verification(): void
    {
        $user = User::factory()->create(['phone' => '11987654321']);

        $response = $this->post(route('user.password.update'), [
            'password' => 'nova123',
            'password_confirmation' => 'nova123',
        ]);

        $response->assertRedirect(route('user.password.recovery'));

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_password_update_requires_confirmation(): void
    {
        $user = User::factory()->create(['phone' => '11987654321']);

        $this->withSession(['password_recovery_user_id' => $user->id]);

        $response = $this->post(route('user.password.update'), [
            'password' => 'nova123',
            'password_confirmation' => 'diferente123',
        ]);

        $response->assertSessionHasErrors('password');
    }
}
