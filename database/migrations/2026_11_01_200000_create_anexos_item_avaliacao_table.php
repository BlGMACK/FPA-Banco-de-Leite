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
        Schema::create('anexos_item_avaliacao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_avaliacao_id')->constrained('itens_avaliacao')->restrictOnDelete();
            $table->string('caminho', 255)->unique();
            $table->string('nome_original', 255);
            $table->string('tipo_conteudo', 100);
            $table->unsignedInteger('tamanho');
            $table->char('sha256', 64);
            $table->foreignId('enviado_por_user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('enviado_em');
            $table->uuid('operacao_id')->unique();
            $table->char('conteudo_hash', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['item_avaliacao_id', 'enviado_em', 'id'], 'anexos_item_avaliacao_item_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('anexos_item_avaliacao')->exists()) {
            throw new RuntimeException('Há anexos de exames registrados; reverter apagaria a referência aos arquivos.');
        }
        Schema::dropIfExists('anexos_item_avaliacao');
    }
};
