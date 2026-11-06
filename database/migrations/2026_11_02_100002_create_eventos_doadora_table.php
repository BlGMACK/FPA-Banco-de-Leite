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
        Schema::create('eventos_doadora', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doadora_id')->constrained('doadoras')->restrictOnDelete();
            $table->enum('acao', ['suspensao', 'retomada', 'renovacao', 'conversao_definitiva', 'encerramento', 'revogacao']);
            $table->enum('estado_anterior', ['ativa', 'suspensa', 'encerrada', 'revogada']);
            $table->enum('estado_novo', ['ativa', 'suspensa', 'encerrada', 'revogada']);
            $table->enum('tipo', ['definitiva', 'temporaria']);
            $table->text('condicao')->nullable();
            $table->date('valida_ate')->nullable();
            $table->text('motivo');
            $table->foreignId('autor_user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('ocorrido_em');
            $table->uuid('operacao_id')->unique();
            $table->char('conteudo_hash', 64);
            $table->unsignedInteger('versao_analisada');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['doadora_id', 'ocorrido_em', 'id'], 'eventos_doadora_historico_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('eventos_doadora')->exists()) {
            throw new RuntimeException('Há eventos de doadoras registrados; reverter apagaria o histórico.');
        }
        Schema::dropIfExists('eventos_doadora');
    }
};
