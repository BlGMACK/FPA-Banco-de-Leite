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
        Schema::create('doadoras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interessada_id')->constrained('interessadas')->restrictOnDelete();
            $table->foreignId('avaliacao_id')->unique()->constrained('avaliacoes')->restrictOnDelete();
            $table->foreignId('decisao_avaliacao_id')->unique()->constrained('decisoes_avaliacao')->restrictOnDelete();
            $table->enum('tipo', ['definitiva', 'temporaria']);
            $table->text('condicao')->nullable();
            $table->date('valida_ate')->nullable();
            $table->enum('estado', ['ativa', 'suspensa', 'encerrada', 'revogada'])->default('ativa');
            $table->unsignedInteger('versao')->default(1);
            $table->timestamps();
            $table->index(['interessada_id', 'estado'], 'doadoras_interessada_index');
            $table->index(['estado', 'updated_at'], 'doadoras_estado_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('doadoras')->exists()) {
            throw new RuntimeException('Há doadoras habilitadas; reverter apagaria o registro.');
        }
        Schema::dropIfExists('doadoras');
    }
};
