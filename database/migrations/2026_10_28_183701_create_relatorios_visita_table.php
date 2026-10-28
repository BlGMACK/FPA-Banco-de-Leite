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
        Schema::create('relatorios_visita', function (Blueprint $table) {
            $table->id();
            $table->foreignId('atendimento_id')->unique()->constrained('atendimentos')->restrictOnDelete();
            $table->enum('estado', ['rascunho', 'finalizado', 'em_correcao'])->default('rascunho');
            $table->mediumText('texto_rascunho')->nullable();
            $table->unsignedInteger('numero_versao_vigente')->default(0);
            $table->unsignedInteger('base_correcao_numero')->nullable();
            $table->text('motivo_correcao')->nullable();
            $table->foreignId('criado_por_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('ultimo_editor_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('revisao')->default(1);
            $table->timestamps();
            $table->index(['estado', 'updated_at'], 'relatorios_visita_estado_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('relatorios_visita');
    }
};
