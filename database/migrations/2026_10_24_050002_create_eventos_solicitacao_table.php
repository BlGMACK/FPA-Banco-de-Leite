<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos_solicitacao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitacao_id')->constrained('solicitacoes')->restrictOnDelete();
            $table->foreignId('autor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('resolucao_id')->nullable()->constrained('resolucoes_solicitacao')->restrictOnDelete();
            $table->enum('tipo', ['recebimento', 'inicio_analise', 'correcao', 'conferencia', 'admissao', 'arquivamento']);
            $table->string('estado_anterior', 30)->nullable();
            $table->string('estado_novo', 30)->nullable();
            $table->json('dados_anteriores')->nullable();
            $table->json('dados_novos')->nullable();
            $table->text('motivo')->nullable();
            $table->dateTime('ocorrido_em');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['solicitacao_id', 'ocorrido_em', 'id'], 'eventos_solicitacao_historico_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_solicitacao');
    }
};
