<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('establishments')->select('user_id')->groupBy('user_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Há gestores com múltiplos estabelecimentos. Revise os vínculos antes de executar esta migration; nenhum registro foi excluído.');
        }
        Schema::table('establishments', function (Blueprint $table) {
            $table->unique('user_id');
            $table->string('cnpj', 14)->nullable()->unique();
            $table->string('pricing_mode', 16)->nullable();
            $table->decimal('surge_percentage', 7, 2)->nullable();
            $table->decimal('peak_tariff', 8, 4)->nullable();
        });
        Schema::table('chargers', function (Blueprint $table) {
            $table->string('activation_status', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('chargers', fn (Blueprint $table) => $table->dropColumn('activation_status'));
        Schema::table('establishments', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
            $table->dropUnique(['cnpj']);
            $table->dropColumn(['cnpj', 'pricing_mode', 'surge_percentage', 'peak_tariff']);
        });
    }
};
