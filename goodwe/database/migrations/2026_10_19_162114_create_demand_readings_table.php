<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leituras de demanda elétrica ativa reportadas pelos totens (IoT/Firestore).
 *
 * Cada registro representa o consumo instantâneo (potência ativa em kW e
 * corrente ativa em A) medido por um carregador/total em um instante. A partir
 * dessas leituras o sistema agrega a demanda total dos totens em andamento e
 * evita sobrecarregar a infraestrutura elétrica local.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demand_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->restrictOnDelete();
            $table->foreignId('charger_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('external_id', 128);
            $table->decimal('active_power_kw', 12, 3)->default(0);
            $table->decimal('current_a', 12, 3)->default(0);
            $table->decimal('voltage_v', 8, 2)->nullable();
            $table->timestamp('recorded_at');
            $table->string('source', 32)->default('iot');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['establishment_id', 'external_id']);
            $table->index(['establishment_id', 'recorded_at']);
            $table->index(['charger_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demand_readings');
    }
};
