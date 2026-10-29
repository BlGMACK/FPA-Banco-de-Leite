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
        Schema::table('users', function (Blueprint $table): void {
            $table->dateTime('desativado_em')->nullable();
            $table->foreignId('desativado_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
        });
        Schema::create('eventos_conta', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('autor_user_id')->constrained('users')->restrictOnDelete();
            $table->enum('acao', ['desativacao', 'reativacao']);
            $table->text('motivo');
            $table->dateTime('ocorrido_em');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'ocorrido_em', 'id'], 'eventos_conta_historico_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('users')->whereNotNull('desativado_em')->exists()) {
            throw new RuntimeException('Há contas desativadas; reverter liberaria o acesso delas.');
        }
        Schema::dropIfExists('eventos_conta');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['desativado_por_user_id']);
            $table->dropColumn(['desativado_em', 'desativado_por_user_id']);
        });
    }
};
