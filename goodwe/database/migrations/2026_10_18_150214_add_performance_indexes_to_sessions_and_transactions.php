<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Índices para as consultas mais quentes do painel do gestor e do portal
     * do usuário. Todas filtram por dono (establishment_id / user_id) e
     * ordenam/agrupam por data, por isso os índices compostos.
     */
    public function up(): void
    {
        Schema::table('charging_sessions', function (Blueprint $table) {
            $table->index(['establishment_id', 'created_at'], 'charging_sessions_establishment_created_idx');
            $table->index(['establishment_id', 'status'], 'charging_sessions_establishment_status_idx');
            $table->index(['user_id', 'created_at'], 'charging_sessions_user_created_idx');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'transactions_user_created_idx');
            $table->index(['user_id', 'status'], 'transactions_user_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('charging_sessions', function (Blueprint $table) {
            $table->dropIndex('charging_sessions_establishment_created_idx');
            $table->dropIndex('charging_sessions_establishment_status_idx');
            $table->dropIndex('charging_sessions_user_created_idx');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_user_created_idx');
            $table->dropIndex('transactions_user_status_idx');
        });
    }
};
