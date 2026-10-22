<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demand_alert_states', function (Blueprint $table) {
            $table->id();

            $table->foreignId('establishment_id')
                ->constrained('establishments')
                ->cascadeOnDelete();

            $table->string('type', 30);

            $table->string('condition', 20)->default('normal');

            $table->unsignedInteger('consecutive_checks')->default(0);

            $table->timestamp('first_detected_at')->nullable();

            $table->timestamp('last_detected_at')->nullable();

            $table->timestamp('last_checked_at')->nullable();

            $table->decimal('last_measured_value', 12, 3)->nullable();

            $table->timestamps();

            $table->unique(
                ['establishment_id', 'type'],
                'demand_alert_states_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demand_alert_states');
    }
};
