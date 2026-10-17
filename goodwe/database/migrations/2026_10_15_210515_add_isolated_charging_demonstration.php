<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('demo_balance', 14, 2)->default(0);
            $table->decimal('demo_points', 14, 2)->default(0);
        });
        foreach (['chargers', 'charging_reservations', 'charging_sessions', 'transactions', 'coupons', 'point_movements', 'charging_events'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->boolean('is_demo')->default(false)->index();
            });
        }
        Schema::table('charging_sessions', function (Blueprint $table) {
            $table->decimal('demo_power_kw', 8, 2)->nullable();
            $table->unsignedInteger('demo_elapsed_seconds')->default(0);
            $table->unsignedSmallInteger('demo_speed')->default(1);
            $table->timestamp('demo_clock_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('charging_sessions', function (Blueprint $table) {
            $table->dropColumn(['demo_power_kw', 'demo_elapsed_seconds', 'demo_speed', 'demo_clock_at']);
        });
        foreach (['charging_events', 'point_movements', 'coupons', 'transactions', 'charging_sessions', 'charging_reservations', 'chargers'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropIndex(['is_demo']);
                $table->dropColumn('is_demo');
            });
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['demo_balance', 'demo_points']);
        });
    }
};
