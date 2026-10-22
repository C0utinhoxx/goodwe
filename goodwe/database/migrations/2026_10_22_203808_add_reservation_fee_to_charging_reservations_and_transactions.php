<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charging_reservations', function (Blueprint $table) {
            // Taxa de reserva cobrada do saldo no ato da confirmação.
            // Calculada como tarifa × duração média das recargas × potência,
            // com acréscimo de 10% (seg–qui) ou 20% (sex–dom). Nunca é devolvida.
            $table->decimal('reservation_fee', 10, 2)->default('0.00')->after('tariff_per_kwh');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('type', ['deposit', 'cashback', 'charge_payment', 'points_redeem', 'reservation_fee'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('type', ['deposit', 'cashback', 'charge_payment', 'points_redeem'])->change();
        });

        Schema::table('charging_reservations', function (Blueprint $table) {
            $table->dropColumn('reservation_fee');
        });
    }
};
