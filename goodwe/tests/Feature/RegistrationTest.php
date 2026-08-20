<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_with_email_phone_and_password(): void
    {
        $response = $this->post(route('user-store'), [
            'name' => 'João Silva',
            'email' => 'joao@example.com',
            'phone' => '11987654321',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertRedirect(route('user.login'));

        $this->assertDatabaseHas('users', [
            'email' => 'joao@example.com',
            'phone' => '11987654321',
        ]);
    }

    public function test_registration_requires_phone(): void
    {
        $response = $this->post(route('user-store'), [
            'name' => 'João Silva',
            'email' => 'joao@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertSessionHasErrors('phone');
        $this->assertDatabaseMissing('users', ['email' => 'joao@example.com']);
    }

    public function test_registration_rejects_invalid_phone(): void
    {
        $response = $this->post(route('user-store'), [
            'name' => 'João Silva',
            'email' => 'joao@example.com',
            'phone' => 'ab',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertSessionHasErrors('phone');
        $this->assertDatabaseMissing('users', ['email' => 'joao@example.com']);
    }
}
