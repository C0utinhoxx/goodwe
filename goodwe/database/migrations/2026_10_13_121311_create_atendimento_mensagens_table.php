
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atendimento_mensagens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('atendimento_humano_id')
                ->constrained('atendimento_humanos')
                ->cascadeOnDelete();

            $table->foreignId('remetente_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->text('mensagem');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atendimento_mensagens');
    }
};
