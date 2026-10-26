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
        Schema::create('agendamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitacao_id')->constrained('solicitacoes')->restrictOnDelete();
            $table->foreignId('contato_confirmacao_id')->constrained('contatos_solicitacao')->restrictOnDelete();
            $table->foreignId('responsavel_user_id')->constrained('users')->restrictOnDelete();
            $table->enum('modalidade', ['domicilio', 'banco']);
            $table->string('local_atendimento', 500);
            $table->dateTime('inicio_previsto_em');
            $table->dateTime('fim_previsto_em');
            $table->enum('status', ['confirmado', 'substituido', 'cancelado', 'nao_realizado']);
            $table->foreignId('substitui_agendamento_id')->nullable()->unique()->constrained('agendamentos')->restrictOnDelete();
            $table->foreignId('confirmado_por_user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('confirmado_em');
            $table->unsignedInteger('versao')->default(1);
            $table->timestamps();
            $table->index(['solicitacao_id', 'created_at', 'id'], 'agendamentos_solicitacao_index');
            $table->index(['responsavel_user_id', 'status', 'inicio_previsto_em'], 'agendamentos_responsavel_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->dropForeign(['substitui_agendamento_id']);
        });
        Schema::dropIfExists('agendamentos');
    }
};
