<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resolucoes_solicitacao', function (Blueprint $table) {
            $table->id();
            $table->uuid('operacao_id')->unique();
            $table->char('conteudo_hash', 64);
            $table->enum('tipo', [
                'aguardando_esclarecimento',
                'pessoas_distintas',
                'arquivar_selecionados',
                'admitir_principal',
                'admitir',
            ]);
            $table->foreignId('contato_id')->nullable()->constrained('contatos_solicitacao')->restrictOnDelete();
            $table->foreignId('responsavel_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('principal_id')->nullable()->constrained('solicitacoes')->restrictOnDelete();
            $table->text('motivo');
            $table->dateTime('ocorrido_em');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('resolucao_pedidos', function (Blueprint $table) {
            $table->foreignId('resolucao_id')->constrained('resolucoes_solicitacao')->restrictOnDelete();
            $table->foreignId('solicitacao_id')->constrained('solicitacoes')->restrictOnDelete();
            $table->enum('papel', ['analisado', 'principal', 'arquivado']);
            $table->unsignedInteger('versao_analisada');
            $table->json('evidencias')->nullable();
            $table->primary(['resolucao_id', 'solicitacao_id']);
            $table->index('solicitacao_id');
        });

        Schema::create('resolucao_cadastros', function (Blueprint $table) {
            $table->foreignId('resolucao_id')->constrained('resolucoes_solicitacao')->restrictOnDelete();
            $table->foreignId('interessada_id')->constrained('interessadas')->restrictOnDelete();
            $table->unsignedInteger('versao_analisada');
            $table->json('evidencias')->nullable();
            $table->primary(['resolucao_id', 'interessada_id']);
            $table->index('interessada_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resolucao_cadastros');
        Schema::dropIfExists('resolucao_pedidos');
        Schema::dropIfExists('resolucoes_solicitacao');
    }
};
