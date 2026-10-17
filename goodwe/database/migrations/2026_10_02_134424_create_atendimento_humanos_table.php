<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atendimento_humanos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('mensagem');
            $table->string('motivo')->nullable();
            $table->string('status')->default('aguardando');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atendimento_humanos');
    }
};