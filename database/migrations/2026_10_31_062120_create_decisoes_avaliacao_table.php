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
        Schema::create('decisoes_avaliacao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('avaliacao_id')->constrained('avaliacoes')->restrictOnDelete();
            $table->enum('etapa', ['analise_relatorio']);
            $table->enum('resultado', ['liberada_exames', 'em_aguardo', 'nao_apta']);
            $table->text('motivo')->nullable();
            $table->foreignId('versao_relatorio_id')->constrained('versoes_relatorio_visita')->restrictOnDelete();
            $table->foreignId('autor_user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('decidido_em');
            $table->uuid('operacao_id')->unique();
            $table->char('conteudo_hash', 64);
            $table->unsignedInteger('versao_analisada');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['avaliacao_id', 'decidido_em', 'id'], 'decisoes_avaliacao_historico_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('decisoes_avaliacao')->exists()) {
            throw new RuntimeException('Há decisões de avaliação registradas; reverter apagaria o histórico.');
        }
        Schema::dropIfExists('decisoes_avaliacao');
    }
};
