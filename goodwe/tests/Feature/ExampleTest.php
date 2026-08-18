<?php

namespace Tests\Feature;

use App\Models\ChargingPoint;
use App\Models\User;
use App\Services\ChargingSimulator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->actingAs($user);
    }

    public function test_the_application_redirects_to_the_chargers_list(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('chargers.index'));
    }

    public function test_chargers_index_lists_all_charging_points(): void
    {
        $response = $this->get(route('chargers.index'));

        $response->assertOk();
        $response->assertSee(__('chargers.title'));
    }

    public function test_a_charging_point_starts_available(): void
    {
        $charger = ChargingSimulator::find('CG-001');

        $this->assertNotNull($charger);
        $this->assertTrue($charger->isAvailable());
        $this->assertSame(ChargingPoint::STATUS_AVAILABLE, $charger->status());
    }

    public function test_a_free_charging_point_can_be_scheduled(): void
    {
        $response = $this->from(route('chargers.index'))
            ->post(route('chargers.schedule', 'CG-003'), [
                'vehicle_plate' => 'TEST-1X2',
                'duration_minutes' => 30,
            ]);

        $response->assertRedirect(route('chargers.index'));

        $charger = ChargingSimulator::find('CG-003');

        $this->assertSame(ChargingPoint::STATUS_OCCUPIED, $charger->status());
        $this->assertSame('TEST-1X2', $charger->vehiclePlate);
    }

    public function test_an_occupied_charging_point_cannot_be_scheduled(): void
    {
        $response = $this->from(route('chargers.index'))
            ->post(route('chargers.schedule', 'CG-002'), [
                'vehicle_plate' => 'ABC-2X3',
                'duration_minutes' => 30,
            ]);

        $response->assertRedirect(route('chargers.index'));
        $response->assertSessionHas('error');

        $charger = ChargingSimulator::find('CG-002');

        $this->assertSame(ChargingPoint::STATUS_OCCUPIED, $charger->status());
        $this->assertNotSame('ABC-2X3', $charger->vehiclePlate);
    }

    public function test_an_occupied_charging_point_can_be_released(): void
    {
        $response = $this->post(route('chargers.release', 'CG-002'));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $charger = ChargingSimulator::find('CG-002');

        $this->assertTrue($charger->isAvailable());
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        auth()->logout();

        $response = $this->get(route('chargers.index'));

        $response->assertRedirect(route('user.login'));
    }
}
