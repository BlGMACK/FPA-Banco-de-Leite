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
        Schema::create('avaliacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('atendimento_id')->unique()->constrained('atendimentos')->restrictOnDelete();
            $table->enum('estado', ['aguardando_analise', 'em_aguardo', 'liberada_exames', 'nao_apta'])->default('aguardando_analise');
            $table->unsignedInteger('versao')->default(1);
            $table->timestamps();
            $table->index(['estado', 'updated_at'], 'avaliacoes_estado_index');
        });

        $agora = now()->utc();
        DB::table('relatorios_visita')->where('numero_versao_vigente', '>', 0)->orderBy('id')->pluck('atendimento_id')
            ->each(fn (int $atendimentoId): bool => DB::table('avaliacoes')->insert(['atendimento_id' => $atendimentoId,
                'estado' => 'aguardando_analise', 'versao' => 1, 'created_at' => $agora, 'updated_at' => $agora]));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('avaliacoes');
    }
};
