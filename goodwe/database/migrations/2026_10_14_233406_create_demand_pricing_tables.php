<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demand_peaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->restrictOnDelete();
            $table->string('external_id', 128);
            $table->string('criteria_reference', 128);
            $table->json('evidence');
            $table->string('status', 16);
            $table->string('decision', 16);
            $table->string('pricing_mode', 16);
            $table->decimal('base_tariff', 8, 4);
            $table->decimal('suggested_tariff', 8, 4);
            $table->decimal('percentage', 7, 2);
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('end_event_id', 128)->nullable();
            $table->json('end_evidence')->nullable();
            $table->timestamps();
            $table->unique(['establishment_id', 'external_id']);
            $table->unique(['establishment_id', 'end_event_id']);
            $table->index(['establishment_id', 'status']);
        });
        Schema::table('establishments', function (Blueprint $table) {
            $table->foreignId('active_peak_id')->nullable()->unique()->constrained('demand_peaks')->restrictOnDelete();
        });
        Schema::create('tariff_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->restrictOnDelete();
            $table->foreignId('demand_peak_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 40);
            $table->decimal('previous_tariff', 8, 4)->nullable();
            $table->decimal('new_tariff', 8, 4);
            $table->decimal('percentage', 7, 2)->nullable();
            $table->string('pricing_mode', 16)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['establishment_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tariff_events');
        Schema::table('establishments', fn (Blueprint $table) => $table->dropConstrainedForeignId('active_peak_id'));
        Schema::dropIfExists('demand_peaks');
    }
};
