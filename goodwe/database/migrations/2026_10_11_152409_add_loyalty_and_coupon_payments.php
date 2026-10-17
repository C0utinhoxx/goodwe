<?php

use App\Services\DecimalAmount;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('points', 14, 2)->default(0)->change();
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->timestamp('credited_at')->nullable();
            $table->string('gateway_payment_id')->nullable()->unique();
            $table->decimal('refunded_amount', 10, 2)->default(0);
            $table->decimal('refund_requested_amount', 10, 2)->default(0);
            $table->boolean('refund_review_required')->default(false);
        });
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('tier');
            $table->string('name');
            $table->unsignedTinyInteger('discount_percent');
            $table->decimal('points_cost', 14, 2);
            $table->string('status')->default('available');
            $table->uuid('redemption_key');
            $table->timestamp('redeemed_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'redemption_key']);
            $table->index(['user_id', 'status']);
        });
        Schema::create('point_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type');
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_after', 14, 2);
            $table->string('description');
            $table->string('event_key')->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'type']);
        });
        Schema::table('charging_sessions', function (Blueprint $table) {
            $table->decimal('original_amount', 10, 2)->nullable();
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->foreignId('coupon_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('transaction_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->uuid('idempotency_key')->nullable();
            $table->unique(['user_id', 'idempotency_key']);
        });
        DB::table('charging_sessions')->update(['original_amount' => DB::raw('amount_charged')]);
        DB::transaction(function () {
            foreach (DB::table('users')->orderBy('id')->get() as $user) {
                $balance = 0;
                foreach (DB::table('transactions')->where('user_id', $user->id)->where('type', 'deposit')->where('status', 'approved')->orderBy('id')->get() as $deposit) {
                    $points = DecimalAmount::percentage(DecimalAmount::units($deposit->amount), 10);
                    $balance += $points;
                    DB::table('transactions')->where('id', $deposit->id)->update(['credited_at' => $deposit->updated_at ?? now()]);
                    DB::table('point_movements')->insert(['user_id' => $user->id, 'transaction_id' => $deposit->id, 'type' => 'deposit', 'amount' => DecimalAmount::decimal($points), 'balance_after' => DecimalAmount::decimal($balance), 'description' => 'Depósito confirmado: conversão para a regra de 10%', 'event_key' => 'deposit:'.$deposit->id, 'metadata' => json_encode(['legacy_conversion' => true]), 'created_at' => $deposit->created_at ?? now(), 'updated_at' => now()]);
                }
                DB::table('users')->where('id', $user->id)->update(['points' => DecimalAmount::decimal($balance)]);
                DB::table('point_movements')->insert(['user_id' => $user->id, 'type' => 'adjustment', 'amount' => 0, 'balance_after' => DecimalAmount::decimal($balance), 'description' => 'Conversão do programa antigo: saldo recalculado por depósitos confirmados', 'event_key' => 'legacy-conversion:'.$user->id, 'metadata' => json_encode(['previous_points' => $user->points, 'recalculated_points' => DecimalAmount::decimal($balance), 'wallet_unchanged' => true]), 'created_at' => now(), 'updated_at' => now()]);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            foreach (DB::table('point_movements')->where('event_key', 'like', 'legacy-conversion:%')->get() as $conversion) {
                $metadata = json_decode($conversion->metadata, true);
                DB::table('users')->where('id', $conversion->user_id)->update(['points' => $metadata['previous_points']]);
            }
        });
        Schema::table('charging_sessions', function (Blueprint $table) {
            $table->dropForeign(['coupon_id']);
            $table->dropForeign(['transaction_id']);
            $table->dropUnique(['user_id', 'idempotency_key']);
            $table->dropUnique(['coupon_id']);
            $table->dropUnique(['transaction_id']);
            $table->dropColumn(['original_amount', 'discount_amount', 'coupon_id', 'transaction_id', 'idempotency_key']);
        });
        Schema::dropIfExists('point_movements');
        Schema::dropIfExists('coupons');
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique(['gateway_payment_id']);
            $table->dropColumn(['credited_at', 'gateway_payment_id', 'refunded_amount', 'refund_requested_amount', 'refund_review_required']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->integer('points')->default(0)->change();
        });
    }
};
