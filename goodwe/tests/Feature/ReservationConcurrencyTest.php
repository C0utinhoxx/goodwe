<?php

namespace Tests\Feature;

use App\Models\Charger;
use App\Models\ChargingEvent;
use App\Models\ChargingReservation;
use App\Models\ChargingSession;
use App\Models\Coupon;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ReservationConcurrencyTest extends TestCase
{
    private string $databasePath;

    private string $barrier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->databasePath = tempnam(sys_get_temp_dir(), 'reservation-concurrency-');
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

    public function test_simultaneous_reservations_cannot_claim_same_charger(): void
    {
        $charger = Charger::factory()->create();
        $users = User::factory()->count(2)->create();
        $operations = [];
        foreach ($users as $user) {
            $key = (string) Str::uuid();
            $operations[] = "app(App\\Services\\ReservationService::class)->reserve(App\\Models\\User::find({$user->id}), App\\Models\\Charger::find({$charger->id}), '{$key}')->id";
        }
        $results = $this->race($operations);
        $this->assertCount(1, array_filter($results, fn ($r) => $r['ok']));
        $this->assertContains('Illuminate\\Validation\\ValidationException', array_column($results, 'error'));
        $this->assertDatabaseCount('charging_reservations', 1);
        $this->assertSame('reserved', $charger->refresh()->status());
        $this->assertEquals(ChargingReservation::sole()->id, $charger->active_reservation_id);
    }

    public function test_simultaneous_duplicate_reservation_is_idempotent(): void
    {
        $charger = Charger::factory()->create();
        $user = User::factory()->create();
        $key = (string) Str::uuid();
        $op = "app(App\\Services\\ReservationService::class)->reserve(App\\Models\\User::find({$user->id}), App\\Models\\Charger::find({$charger->id}), '{$key}')->id";
        $results = $this->race([$op, $op]);
        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame($results[0]['id'], $results[1]['id']);
        $this->assertDatabaseCount('charging_reservations', 1);
    }

    public function test_simultaneous_expiration_applies_no_show_once(): void
    {
        $user = User::factory()->create(['points' => 3]);
        $charger = Charger::factory()->create();
        $reservation = app(ReservationService::class)->reserve($user, $charger, (string) Str::uuid());
        $reservation->update(['expires_at' => now()->subMinute()]);
        $op = "app(App\\Services\\ReservationService::class)->expire(App\\Models\\ChargingReservation::find({$reservation->id}))";
        $results = $this->race([$op, $op]);
        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame('0.00', $user->refresh()->points);
        $this->assertDatabaseCount('point_movements', 1);
        $this->assertEquals(1, ChargingEvent::where('type', 'no_show')->count());
    }

    public function test_simultaneous_confirmation_pays_session_once(): void
    {
        $user = User::factory()->create(['balance' => 100]);
        $session = ChargingSession::factory()->for($user)->create();
        $coupon = Coupon::factory()->for($user)->create();
        $key = (string) Str::uuid();
        $op = "app(App\\Services\\WalletService::class)->paySession(App\\Models\\User::find({$user->id}), App\\Models\\ChargingSession::find({$session->id}), {$coupon->id}, '{$key}')->id";
        $results = $this->race([$op, $op]);
        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame($results[0]['id'], $results[1]['id']);
        $this->assertSame('48.70', $user->refresh()->balance);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertSame('paid', $session->refresh()->payment_status);
        $this->assertSame('used', $coupon->refresh()->status);
    }

    public function test_simultaneous_session_payments_cannot_reuse_coupon_or_overdraw_cash(): void
    {
        $user = User::factory()->create(['balance' => 60]);
        $sessions = ChargingSession::factory()->for($user)->count(2)->create();
        $coupon = Coupon::factory()->for($user)->create();
        $operations = [];
        foreach ($sessions as $session) {
            $key = (string) Str::uuid();
            $operations[] = "app(App\\Services\\WalletService::class)->paySession(App\\Models\\User::find({$user->id}), App\\Models\\ChargingSession::find({$session->id}), {$coupon->id}, '{$key}')->id";
        }
        $results = $this->race($operations);
        $this->assertCount(1, array_filter($results, fn ($r) => $r['ok']));
        $this->assertContains('Illuminate\\Validation\\ValidationException', array_column($results, 'error'));
        $this->assertSame('8.70', $user->refresh()->balance);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertEquals(1, $user->chargingSessions()->where('payment_status', 'pending')->count());
    }
}
