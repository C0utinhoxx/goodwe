<?php

namespace Tests\Feature;

use App\Models\Establishment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TmpDumpManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_dump(): void
    {
        $establishment = Establishment::factory()->create();
        $html = $this->actingAs($establishment->user)->get(route('manager.dashboard'))->assertOk()->getContent();

        file_put_contents(storage_path('framework/views/_dump_manager.html'), $html);
        $this->assertTrue(true);
    }
}
