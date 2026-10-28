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
        Schema::create('versoes_relatorio_visita', function (Blueprint $table) {
            $table->id();
            $table->foreignId('relatorio_visita_id')->constrained('relatorios_visita')->restrictOnDelete();
            $table->unsignedInteger('numero');
            $table->mediumText('texto');
            $table->foreignId('versao_base_id')->nullable()->constrained('versoes_relatorio_visita')->restrictOnDelete();
            $table->text('motivo_correcao')->nullable();
            $table->foreignId('finalizado_por_user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('finalizado_em');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['relatorio_visita_id', 'numero'], 'versoes_relatorio_numero_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('versoes_relatorio_visita', function (Blueprint $table) {
            $table->dropForeign(['versao_base_id']);
        });
        Schema::dropIfExists('versoes_relatorio_visita');
    }
};
