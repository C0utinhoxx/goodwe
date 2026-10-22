
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demand_alerts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('establishment_id')
                ->constrained('establishments')
                ->cascadeOnDelete();

            $table->string('type', 30);

            $table->string('level', 20);

            $table->string('status', 20)->default('active');

            $table->string('title');

            $table->text('message');

            $table->decimal('measured_value', 12, 3)->nullable();

            $table->decimal('threshold_value', 12, 3)->nullable();

            $table->string('unit', 20)->nullable();

            $table->json('details')->nullable();

            $table->timestamp('started_at');

            $table->timestamp('ended_at')->nullable();

            $table->timestamps();

            $table->index(
                ['establishment_id', 'type', 'status'],
                'demand_alerts_lookup_index'
            );

            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demand_alerts');
    }
};
