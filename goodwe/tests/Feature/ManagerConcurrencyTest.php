<?php

namespace Tests\Feature;

use App\Models\Charger;
use App\Models\ChargingReservation;
use App\Models\DemandPeak;
use App\Models\Establishment;
use App\Models\TariffEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ManagerConcurrencyTest extends TestCase
{
    private string $databasePath;

    private string $barrier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->databasePath = tempnam(sys_get_temp_dir(), 'manager-concurrency-');
        $this->barrier = $this->databasePath.'.go';
        config(['database.connections.loyalty_concurrency' => array_merge(config('database.connections.sqlite'), ['database' => $this->databasePath, 'url' => null, 'busy_timeout' => 10000]), 'database.default' => 'loyalty_concurrency']);
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    }

    protected function tearDown(): void
    {
        DB::disconnect('loyalty_concurrency');
        foreach (glob($this->databasePath.'*') as $file) {
            unlink($file);
        }
        parent::tearDown();
    }

    private function race(array $operations): array
    {
        $processes = [];
        foreach ($operations as $index => $operation) {
            $bootstrap = 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();';
            $bootstrap = str_replace('\\\\', '\\', $bootstrap);
            $bootstrap .= '$app["config"]->set("database.default", "sqlite"); $app["config"]->set("database.connections.sqlite.database", '.var_export($this->databasePath, true).'); $app["config"]->set("database.connections.sqlite.url", null); Illuminate\\Support\\Facades\\DB::statement("PRAGMA busy_timeout=10000");';
            $bootstrap = str_replace('\\\\', '\\', $bootstrap);
            $bootstrap .= 'touch('.var_export($this->databasePath.'.ready'.$index, true).'); while(!file_exists('.var_export($this->barrier, true).')) {usleep(1000);}';
            $bootstrap .= <<<'PHP'
Illuminate\Support\Facades\DB::listen(function($q) { if (str_contains($q->sql, 'from "users"') || str_contains($q->sql, 'from "chargers"')) { usleep(150000); } });
PHP;

            $bootstrap = str_replace('\\\\', '\\', $bootstrap);
            $bootstrap .= 'try {$result='.$operation.'; echo json_encode(["ok"=>true,"id"=>$result]);} catch(Throwable $e) {echo json_encode(["ok"=>false,"error"=>get_class($e),"message"=>$e->getMessage()]);}';
            $process = new Process([PHP_BINARY, '-r', $bootstrap], base_path(), ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $this->databasePath, 'DB_URL' => '', 'CACHE_STORE' => 'array']);
            $process->setTimeout(15);
            $process->start();
            $processes[] = $process;
        }
        $deadline = microtime(true) + 5;
        while ((! file_exists($this->databasePath.'.ready0') || ! file_exists($this->databasePath.'.ready1')) && microtime(true) < $deadline) {
            usleep(1000);
        }
        touch($this->barrier);
        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $result = json_decode($process->getOutput(), true);
            $this->assertIsArray($result, $process->getOutput());
            $results[] = $result;
        }

        return $results;
    }

    private function establishmentData(string $cnpj): array
    {
        return ['name' => 'Loja de teste', 'cnpj' => $cnpj, 'address' => 'Endereço de teste', 'phone' => '11987654321', 'type' => 'comercial', 'operating_hours' => '08:00–22:00', 'cost_per_kwh' => '1.9000', 'pricing_mode' => 'manual', 'surge_percentage' => '15.00', 'reservation_tolerance_minutes' => 15, 'no_show_penalty_points' => '10.00'];
    }

    public function test_two_simultaneous_creations_cannot_give_one_manager_two_establishments(): void
    {
        $manager = User::factory()->create(['is_admin' => true]);
        $operations = [];
        foreach (['11222333000181', '04252011000110'] as $cnpj) {
            $operations[] = 'app(App\\Services\\EstablishmentService::class)->create(App\\Models\\User::find('.$manager->id.'),'.var_export($this->establishmentData($cnpj), true).')->id';
        }
        $results = $this->race($operations);
        $this->assertCount(1, array_filter($results, fn ($r) => $r['ok']));
        $this->assertDatabaseCount('establishments', 1);
        $this->assertDatabaseCount('tariff_events', 1);
        $this->assertContains('Illuminate\\Validation\\ValidationException', array_column($results, 'error'));
    }

    public function test_two_managers_cannot_simultaneously_register_the_same_cnpj(): void
    {
        $operations = [];
        foreach (User::factory()->count(2)->create(['is_admin' => true]) as $manager) {
            $operations[] = 'app(App\\Services\\EstablishmentService::class)->create(App\\Models\\User::find('.$manager->id.'),'.var_export($this->establishmentData('11222333000181'), true).')->id';
        }
        $results = $this->race($operations);
        $this->assertCount(1, array_filter($results, fn ($r) => $r['ok']));
        $this->assertDatabaseCount('establishments', 1);
        $this->assertDatabaseCount('tariff_events', 1);
    }

    public function test_simultaneous_approvals_apply_only_one_adjustment(): void
    {
        $store = Establishment::factory()->create(['pricing_mode' => 'manual', 'surge_percentage' => '15.00']);
        $peak = DemandPeak::factory()->for($store)->create();
        $store->update(['active_peak_id' => $peak->id]);
        $operation = 'app(App\\Services\\DemandPricingService::class)->decide(App\\Models\\User::find('.$store->user_id.'),App\\Models\\DemandPeak::find('.$peak->id.'),true,1)->id';
        $results = $this->race([$operation, $operation]);
        $this->assertCount(2, array_filter($results, fn ($r) => $r['ok']));
        $this->assertSame('2.1900', $store->fresh()->currentTariff());
        $this->assertSame(1, TariffEvent::where('type', 'approved')->count());
    }

    public function test_reservation_racing_with_configuration_change_captures_one_consistent_version(): void
    {
        $store = Establishment::factory()->create(['pricing_mode' => 'manual', 'surge_percentage' => '15.00']);
        $charger = Charger::factory()->for($store)->create();
        $driver = User::factory()->create();
        $key = (string) Str::uuid();
        $operations = [
            'app(App\\Services\\ReservationService::class)->reserve(App\\Models\\User::find('.$driver->id.'),App\\Models\\Charger::find('.$charger->id.'),'.var_export($key, true).')->id',
            'app(App\\Services\\DemandPricingService::class)->updateSettings(App\\Models\\User::find('.$store->user_id.'),App\\Models\\Establishment::find('.$store->id.'),["cost_per_kwh"=>"2.2000","reservation_tolerance_minutes"=>7,"no_show_penalty_points"=>"2.00"])->id',
        ];
        $results = $this->race($operations);
        $this->assertCount(2, array_filter($results, fn ($r) => $r['ok']));
        $reservation = ChargingReservation::sole();
        $snapshot = [$reservation->tariff_per_kwh, $reservation->tolerance_seconds, $reservation->no_show_penalty_points];
        $this->assertContains($snapshot, [['1.9000', 900, '10.00'], ['2.2000', 420, '2.00']]);
        $this->assertSame('2.2000',$store->fresh()->currentTariff());
    }
}
