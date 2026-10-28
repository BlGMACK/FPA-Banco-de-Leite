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
        Schema::create('eventos_atendimento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('atendimento_id')->constrained('atendimentos')->restrictOnDelete();
            $table->foreignId('relatorio_visita_id')->nullable()->constrained('relatorios_visita')->restrictOnDelete();
            $table->foreignId('versao_relatorio_id')->nullable()->constrained('versoes_relatorio_visita')->restrictOnDelete();
            $table->enum('acao', ['realizacao', 'salvamento_rascunho', 'abertura_correcao', 'finalizacao']);
            $table->foreignId('autor_user_id')->constrained('users')->restrictOnDelete();
            $table->uuid('operacao_id')->unique();
            $table->char('conteudo_hash', 64);
            $table->unsignedInteger('revisao_analisada');
            $table->dateTime('ocorrido_em');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['atendimento_id', 'ocorrido_em', 'id'], 'eventos_atendimento_historico_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eventos_atendimento');
    }
};
