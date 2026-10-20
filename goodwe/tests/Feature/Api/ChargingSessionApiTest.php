<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\FirestoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeFirestoreService;
use Tests\TestCase;

class ChargingSessionApiTest extends TestCase
{
    use RefreshDatabase;

    private FakeFirestoreService $firestore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->firestore = new FakeFirestoreService;
        $this->app->instance(FirestoreService::class, $this->firestore);
    }

    private function seedSessions(int $quantity): void
    {
        foreach (range(1, $quantity) as $index) {
            $this->firestore->seed('charging_sessions', "session-{$index}", [
                'id' => "session-{$index}",
                'valor' => $index,
            ]);
        }
    }

    private function driver(): User
    {
        return User::factory()->create();
    }

    public function test_list_returns_sessions_within_the_default_window(): void
    {
        $this->seedSessions(12);

        $response = $this->actingAs($this->driver(), 'sanctum')
            ->getJson(route('api.firestore.charging-sessions.index'));

        $response->assertOk();
        $this->assertCount(12, $response->json());
    }

    public function test_list_honours_the_per_page_window(): void
    {
        $this->seedSessions(12);

        $response = $this->actingAs($this->driver(), 'sanctum')
            ->getJson(route('api.firestore.charging-sessions.index', ['per_page' => 5]));

        $response->assertOk();
        $this->assertCount(5, $response->json());
    }

    public function test_per_page_is_clamped_to_a_positive_value(): void
    {
        $this->seedSessions(12);

        $response = $this->actingAs($this->driver(), 'sanctum')
            ->getJson(route('api.firestore.charging-sessions.index', ['per_page' => 0]));

        $response->assertOk();
        $this->assertCount(1, $response->json());
    }

    public function test_guests_cannot_list_sessions(): void
    {
        $this->getJson(route('api.firestore.charging-sessions.index'))->assertUnauthorized();
    }
}
