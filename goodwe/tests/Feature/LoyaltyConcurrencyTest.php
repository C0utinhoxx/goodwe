<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Transaction;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class LoyaltyConcurrencyTest extends TestCase
{
    private string $databasePath;

    private string $barrier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->databasePath = tempnam(sys_get_temp_dir(), 'loyalty-concurrency-');
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
Illuminate\Support\Facades\DB::listen(function($q) { if (str_contains($q->sql, 'from "users"')) { usleep(150000); } });
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

    public function test_simultaneous_redemptions_cannot_spend_same_points(): void
    {
        $user = User::factory()->create(['points' => 10]);
        $ops = [];
        foreach ([0, 1] as $i) {
            $key = (string) Str::uuid();
            $ops[] = "app(App\\Services\\LoyaltyService::class)->redeem(App\\Models\\User::find({$user->id}), 'bronze', '{$key}')->id";
        }
        $results = $this->race($ops);
        $this->assertCount(1, array_filter($results, fn ($r) => $r['ok']));
        $this->assertContains('App\\Exceptions\\InsufficientPointsException', array_column($results, 'error'));
        $this->assertSame('0.00', $user->refresh()->points);
        $this->assertDatabaseCount('coupons', 1);
    }

    public function test_simultaneous_payments_cannot_use_same_coupon(): void
    {
        $user = User::factory()->create(['balance' => 100]);
        $coupon = Coupon::factory()->for($user)->create();
        $ops = [];
        foreach ([0, 1] as $i) {
            $key = (string) Str::uuid();
            $ops[] = "app(App\\Services\\WalletService::class)->payCharging(App\\Models\\User::find({$user->id}), 50, 'CG-001', 10, {$coupon->id}, '{$key}')->id";
        }
        $results = $this->race($ops);
        $this->assertCount(1, array_filter($results, fn ($r) => $r['ok']));
        $this->assertContains('Illuminate\\Validation\\ValidationException', array_column($results, 'error'));
        $this->assertSame('55.00', $user->refresh()->balance);
        $this->assertDatabaseCount('charging_sessions', 1);
        $this->assertSame('used', $coupon->refresh()->status);
    }

    public function test_simultaneous_payments_cannot_overdraw_wallet(): void
    {
        $user = User::factory()->create(['balance' => 20]);
        $ops = [];
        foreach ([0, 1] as $i) {
            $key = (string) Str::uuid();
            $ops[] = "app(App\\Services\\WalletService::class)->payCharging(App\\Models\\User::find({$user->id}), 19.5, 'CG-001', 10, null, '{$key}')->id";
        }
        $results = $this->race($ops);
        $this->assertCount(1, array_filter($results, fn ($r) => $r['ok']));
        $this->assertContains('App\\Exceptions\\InsufficientBalanceException', array_column($results, 'error'));
        $this->assertSame('0.50', $user->refresh()->balance);
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_simultaneous_duplicate_requests_return_same_redemption(): void
    {
        $user = User::factory()->create(['points' => 10]);
        $key = (string) Str::uuid();
        $op = "app(App\\Services\\LoyaltyService::class)->redeem(App\\Models\\User::find({$user->id}), 'bronze', '{$key}')->id";
        $results = $this->race([$op, $op]);
        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame($results[0]['id'], $results[1]['id']);
        $this->assertDatabaseCount('coupons', 1);
        $this->assertSame('0.00', $user->refresh()->points);
    }

    public function test_simultaneous_confirmations_credit_deposit_once(): void
    {
        $user = User::factory()->create();
        $deposit = Transaction::create(['user_id' => $user->id, 'type' => 'deposit', 'amount' => 100, 'description' => 'Depósito', 'status' => 'pending']);
        $op = "(function(){app(App\\Services\\WalletService::class)->creditApprovedDeposit(App\\Models\\Transaction::find({$deposit->id}), 'payment-race'); return 1;})()";
        $results = $this->race([$op, $op]);
        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame('100.00', $user->refresh()->balance);
        $this->assertSame('10.00', $user->points);
        $this->assertDatabaseCount('point_movements', 1);
    }

    public function test_simultaneous_duplicate_payments_return_same_payment(): void
    {
        $user = User::factory()->create(['balance' => 100]);
        $coupon = Coupon::factory()->for($user)->create();
        $key = (string) Str::uuid();
        $op = "app(App\\Services\\WalletService::class)->payCharging(App\\Models\\User::find({$user->id}), 50, 'CG-001', 10, {$coupon->id}, '{$key}')->id";
        $results = $this->race([$op, $op]);
        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame($results[0]['id'], $results[1]['id']);
        $this->assertSame('55.00', $user->refresh()->balance);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseCount('charging_sessions', 1);
    }

    public function test_legacy_conversion_uses_confirmed_deposits_and_preserves_cash_and_old_history(): void
    {
        $migration = require glob(database_path('migrations/*add_loyalty_and_coupon_payments.php'))[0];
        $migration->down();
        $user = User::factory()->create(['balance' => '432.10', 'points' => 9990]);
        foreach ([['deposit', 100, 'approved'], ['deposit', 85.50, 'approved'], ['deposit', 500, 'pending'], ['deposit', 5000, 'rejected'], ['cashback', 10, 'approved'], ['points_redeem', 5, 'approved'], ['charge_payment', 50, 'approved']] as [$type,$amount,$status]) {
            Transaction::create(['user_id' => $user->id, 'type' => $type, 'amount' => $amount, 'status' => $status, 'description' => 'Registro legado preservado']);
        }
        $migration->up();
        $this->assertSame('18.55', $user->refresh()->points);
        $this->assertSame('432.10', $user->balance);
        $this->assertDatabaseCount('transactions', 7);
        $this->assertDatabaseCount('point_movements', 3);
        $conversion = $user->pointMovements()->where('type', 'adjustment')->sole();
        $this->assertEquals(9990, $conversion->metadata['previous_points']);
        $deposit = $user->transactions()->where('type', 'deposit')->where('status', 'approved')->first();
        app(WalletService::class)->creditApprovedDeposit($deposit, 'legacy-payment');
        $this->assertSame('18.55', $user->refresh()->points);
        $this->assertSame('432.10', $user->balance);
    }
}
