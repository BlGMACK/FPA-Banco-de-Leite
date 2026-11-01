<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('eventos_item_avaliacao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_avaliacao_id')->constrained('itens_avaliacao')->restrictOnDelete();
            $table->enum('acao', ['inclusao', 'solicitacao', 'cancelamento_solicitacao', 'recebimento', 'aceite', 'complementacao', 'prorrogacao', 'novo_pedido']);
            $table->enum('estado_anterior', ['nao_solicitado', 'solicitado', 'recebido', 'aceito', 'precisa_complementar'])->nullable();
            $table->enum('estado_novo', ['nao_solicitado', 'solicitado', 'recebido', 'aceito', 'precisa_complementar']);
            $table->text('motivo')->nullable();
            $table->date('realizado_em')->nullable();
            $table->date('valido_ate')->nullable();
            $table->foreignId('autor_user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('ocorrido_em');
            $table->uuid('operacao_id')->unique();
            $table->char('conteudo_hash', 64);
            $table->unsignedInteger('versao_analisada')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['item_avaliacao_id', 'ocorrido_em', 'id'], 'eventos_item_avaliacao_historico_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('eventos_item_avaliacao')->exists()) {
            throw new RuntimeException('Há eventos de exames registrados; reverter apagaria o histórico.');
        }
        Schema::dropIfExists('eventos_item_avaliacao');
    }
};
