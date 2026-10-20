<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Limites da infraestrutura elétrica local do estabelecimento.
 *
 * Quando configurados, servem como teto para a demanda agregada (potência ativa
 * em kW e corrente ativa em A) dos carregadores em andamento. Valores nulos
 * significam que não há limite definido e, portanto, nenhuma imposição.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->decimal('max_power_kw', 8, 2)->nullable()->after('peak_tariff');
            $table->decimal('max_current_a', 8, 2)->nullable()->after('max_power_kw');
        });
    }

    public function down(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->dropColumn(['max_power_kw', 'max_current_a']);
        });
    }
};
