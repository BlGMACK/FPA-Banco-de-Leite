<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('eventos_agendamento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agendamento_id')->constrained('agendamentos')->restrictOnDelete();
            $table->foreignId('agendamento_resultante_id')->nullable()->constrained('agendamentos')->restrictOnDelete();
            $table->foreignId('autor_user_id')->constrained('users')->restrictOnDelete();
            $table->enum('acao', ['confirmacao', 'reagendamento', 'cancelamento', 'nao_realizacao']);
            $table->uuid('operacao_id')->unique();
            $table->char('conteudo_hash', 64);
            $table->unsignedInteger('versao_analisada')->nullable();
            $table->text('motivo');
            $table->json('dados_anteriores')->nullable();
            $table->json('dados_novos')->nullable();
            $table->dateTime('ocorrido_em');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['agendamento_id', 'ocorrido_em', 'id'], 'eventos_agendamento_historico_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eventos_agendamento');
    }
};
