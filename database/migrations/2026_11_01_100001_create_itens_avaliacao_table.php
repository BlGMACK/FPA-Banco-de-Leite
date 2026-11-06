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
        Schema::create('itens_avaliacao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('avaliacao_id')->constrained('avaliacoes')->restrictOnDelete();
            $table->foreignId('tipo_exame_id')->nullable()->constrained('tipos_exame')->restrictOnDelete();
            $table->string('nome_outro', 150)->nullable();
            $table->text('detalhes_outro')->nullable();
            $table->enum('estado', ['nao_solicitado', 'solicitado', 'recebido', 'aceito', 'precisa_complementar'])->default('nao_solicitado');
            $table->string('numero_documento', 15)->nullable();
            $table->date('realizado_em')->nullable();
            $table->date('valido_ate')->nullable();
            $table->unsignedTinyInteger('prorrogacoes')->default(0);
            $table->unsignedInteger('versao')->default(1);
            $table->timestamps();
            // "Outro exame" tem tipo nulo e pode se repetir; tipos do catálogo aparecem uma vez por avaliação.
            $table->unique(['avaliacao_id', 'tipo_exame_id'], 'itens_avaliacao_tipo_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('itens_avaliacao')->where('estado', '!=', 'nao_solicitado')->exists()) {
            throw new RuntimeException('Há itens de exame em andamento; reverter apagaria o acompanhamento.');
        }
        Schema::dropIfExists('itens_avaliacao');
    }
};
