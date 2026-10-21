<?php

namespace Tests\Feature;

use App\Models\Establishment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerProfileTest extends TestCase
{
    use RefreshDatabase;

    private function manager(array $overrides = []): User
    {
        return User::factory()->create(array_merge(['is_admin' => true], $overrides));
    }

    public function test_manager_can_view_own_profile_with_personal_information_form(): void
    {
        $manager = $this->manager([
            'name' => 'Gestor Perfil',
            'email' => 'gestor.perfil@example.test',
            'phone' => '11987654321',
        ]);
        Establishment::factory()->for($manager)->create();

        $this->actingAs($manager)
            ->get(route('manager.profile'))
            ->assertOk()
            ->assertSee('Meu perfil')
            ->assertSee('Informações pessoais')
            ->assertSee('Gestor Perfil')
            ->assertSee('gestor.perfil@example.test')
            ->assertSee('11987654321')
            ->assertSee('action="'.route('manager.profile.update').'"', false)
            ->assertSee(route('manager.profile'), false);
    }

    public function test_manager_without_establishment_can_open_profile_before_completing_setup(): void
    {
        $this->get(route('manager.profile'))->assertRedirect(route('manager.login'));

        $this->actingAs($this->manager())->get(route('manager.profile'))->assertOk();
    }

    public function test_manager_updates_own_personal_data(): void
    {
        $manager = $this->manager();

        $this->actingAs($manager)
            ->patch(route('manager.profile.update'), [
                'name' => 'Nome Atualizado',
                'email' => 'atualizado@example.test',
                'phone' => '(11) 99999-9999',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $manager->refresh();
        $this->assertSame('Nome Atualizado', $manager->name);
        $this->assertSame('atualizado@example.test', $manager->email);
        $this->assertSame('(11) 99999-9999', $manager->phone);
        $this->assertTrue($manager->isGestor());
    }

    public function test_manager_profile_update_rejects_invalid_and_duplicate_data(): void
    {
        $manager = $this->manager();
        User::factory()->create(['email' => 'ocupado@example.test']);

        $this->actingAs($manager)
            ->patch(route('manager.profile.update'), [
                'name' => '',
                'email' => 'ocupado@example.test',
                'phone' => 'telefone inválido',
            ])
            ->assertSessionHasErrors(['name', 'email', 'phone']);

        $this->assertSame($manager->name, $manager->fresh()->name);
        $this->assertSame($manager->email, $manager->fresh()->email);
    }

    public function test_manager_profile_update_cannot_change_role_or_financial_fields(): void
    {
        $manager = $this->manager(['balance' => 100, 'points' => 50]);

        $this->actingAs($manager)
            ->patch(route('manager.profile.update'), [
                'name' => 'Novo nome',
                'email' => $manager->email,
                'phone' => $manager->phone,
                'is_admin' => false,
                'balance' => 999,
                'points' => 999,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $manager->refresh();
        $this->assertTrue($manager->isGestor());
        $this->assertSame('100.00', $manager->balance);
        $this->assertSame('50.00', $manager->points);
    }

    public function test_regular_user_cannot_access_gestor_profile_routes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('manager.profile'))->assertForbidden();
        $this->actingAs($user)
            ->patch(route('manager.profile.update'), [
                'name' => 'Invasor',
                'email' => $user->email,
                'phone' => $user->phone,
            ])
            ->assertForbidden();

        $this->assertSame($user->name, $user->fresh()->name);
    }

    public function test_manager_profile_link_is_offered_in_the_account_menu(): void
    {
        $manager = Establishment::factory()->create()->user;

        $this->actingAs($manager)
            ->get(route('manager.dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('manager.profile').'"', false);
    }
}
