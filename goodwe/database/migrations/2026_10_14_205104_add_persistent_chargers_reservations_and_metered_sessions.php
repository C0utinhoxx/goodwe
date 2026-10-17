<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->unsignedInteger('reservation_tolerance_minutes')->nullable();
            $table->decimal('no_show_penalty_points', 14, 2)->nullable();
        });
        Schema::create('chargers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->restrictOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('location');
            $table->decimal('power_kw', 8, 2);
            $table->string('connector');
            $table->string('physical_status')->default('unavailable');
            $table->timestamp('physical_status_updated_at')->nullable();
            $table->decimal('tariff_override', 8, 4)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->timestamps();
            $table->index(['establishment_id', 'physical_status']);
        });
        Schema::create('charging_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('charger_id')->constrained()->restrictOnDelete();
            $table->foreignId('establishment_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('active');
            $table->uuid('idempotency_key');
            $table->decimal('tariff_per_kwh', 8, 4);
            $table->unsignedInteger('tolerance_seconds');
            $table->decimal('no_show_penalty_points', 14, 2)->nullable();
            $table->decimal('penalty_applied_points', 14, 2)->default(0);
            $table->timestamp('reserved_at');
            $table->timestamp('expires_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('penalty_processed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['status', 'expires_at']);
        });
        Schema::table('charging_sessions', function (Blueprint $table) {
            $table->foreignId('charger_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('charging_reservation_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('external_session_id')->nullable();
            $table->decimal('tariff_per_kwh', 8, 4)->nullable();
            $table->decimal('measured_energy_kwh', 12, 4)->nullable();
            $table->string('charging_state')->nullable();
            $table->string('payment_status')->nullable();
            $table->unsignedBigInteger('last_meter_sequence')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('last_meter_at')->nullable();
            $table->timestamp('stop_requested_at')->nullable();
            $table->timestamp('stop_confirmed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->boolean('balance_exceeded')->default(false);
            $table->unique(['charger_id', 'external_session_id']);
            $table->index(['user_id', 'payment_status']);
        });
        Schema::table('chargers', function (Blueprint $table) {
            $table->foreignId('active_reservation_id')->nullable()->unique()->constrained('charging_reservations')->restrictOnDelete();
            $table->foreignId('active_session_id')->nullable()->unique()->constrained('charging_sessions')->restrictOnDelete();
        });
        Schema::create('charging_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('charger_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('charging_reservation_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('charging_session_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type');
            $table->string('event_key')->unique();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charging_events');
        Schema::table('chargers', function (Blueprint $table) {
            $table->dropForeign(['active_reservation_id']);
            $table->dropForeign(['active_session_id']);
            $table->dropUnique(['active_reservation_id']);
            $table->dropUnique(['active_session_id']);
            $table->dropColumn(['active_reservation_id', 'active_session_id']);
        });
        Schema::table('charging_sessions', function (Blueprint $table) {
            $table->dropForeign(['charger_id']);
            $table->dropForeign(['charging_reservation_id']);
            $table->dropUnique(['charging_reservation_id']);
            $table->dropUnique(['charger_id', 'external_session_id']);
            $table->dropIndex(['user_id', 'payment_status']);
            $table->dropColumn(['charger_id', 'charging_reservation_id', 'external_session_id', 'tariff_per_kwh', 'measured_energy_kwh', 'charging_state', 'payment_status', 'last_meter_sequence', 'started_at', 'ended_at', 'last_meter_at', 'stop_requested_at', 'stop_confirmed_at', 'paid_at', 'balance_exceeded']);
        });
        Schema::dropIfExists('charging_reservations');
        Schema::dropIfExists('chargers');
        Schema::table('establishments', function (Blueprint $table) {
            $table->dropColumn(['reservation_tolerance_minutes', 'no_show_penalty_points']);
        });
    }
};
