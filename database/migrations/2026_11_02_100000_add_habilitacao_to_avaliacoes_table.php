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
        Schema::table('avaliacoes', function (Blueprint $table) {
            $table->enum('estado', ['aguardando_analise', 'em_aguardo', 'liberada_exames', 'nao_apta', 'habilitada', 'habilitada_temporariamente', 'nao_habilitada'])
                ->default('aguardando_analise')->change();
            $table->boolean('possui_plano_saude')->nullable()->after('estado');
        });

        Schema::table('decisoes_avaliacao', function (Blueprint $table) {
            $table->enum('etapa', ['analise_relatorio', 'habilitacao'])->change();
            $table->enum('resultado', ['liberada_exames', 'em_aguardo', 'nao_apta', 'habilitada', 'habilitada_temporariamente', 'nao_habilitada'])->change();
            $table->boolean('possui_plano_saude')->nullable()->after('motivo');
            $table->text('condicao')->nullable()->after('possui_plano_saude');
            $table->date('valida_ate')->nullable()->after('condicao');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('decisoes_avaliacao')->where('etapa', 'habilitacao')->exists()) {
            throw new RuntimeException('Há decisões de habilitação registradas; reverter apagaria o histórico.');
        }

        Schema::table('decisoes_avaliacao', function (Blueprint $table) {
            $table->dropColumn(['possui_plano_saude', 'condicao', 'valida_ate']);
        });
        Schema::table('decisoes_avaliacao', function (Blueprint $table) {
            $table->enum('etapa', ['analise_relatorio'])->change();
            $table->enum('resultado', ['liberada_exames', 'em_aguardo', 'nao_apta'])->change();
        });
        Schema::table('avaliacoes', function (Blueprint $table) {
            $table->dropColumn('possui_plano_saude');
        });
        Schema::table('avaliacoes', function (Blueprint $table) {
            $table->enum('estado', ['aguardando_analise', 'em_aguardo', 'liberada_exames', 'nao_apta'])->default('aguardando_analise')->change();
        });
    }
};
