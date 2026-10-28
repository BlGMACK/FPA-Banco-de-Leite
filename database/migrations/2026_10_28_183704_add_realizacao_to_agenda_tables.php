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
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->enum('status', ['confirmado', 'substituido', 'cancelado', 'nao_realizado', 'realizado'])->change();
        });
        Schema::table('eventos_agendamento', function (Blueprint $table) {
            $table->enum('acao', ['confirmacao', 'reagendamento', 'cancelamento', 'nao_realizacao', 'realizacao'])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('agendamentos')->where('status', 'realizado')->exists()
            || DB::table('eventos_agendamento')->where('acao', 'realizacao')->exists()) {
            throw new RuntimeException('Rollback da agenda recusado: existem registros de realização. Preserve o histórico e use uma migration corretiva.');
        }

        Schema::table('eventos_agendamento', function (Blueprint $table) {
            $table->enum('acao', ['confirmacao', 'reagendamento', 'cancelamento', 'nao_realizacao'])->change();
        });
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->enum('status', ['confirmado', 'substituido', 'cancelado', 'nao_realizado'])->change();
        });
    }
};
