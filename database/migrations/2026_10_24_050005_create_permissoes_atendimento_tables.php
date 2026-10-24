<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissoes', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 100)->unique();
            $table->string('nome', 150);
            $table->timestamps();
        });

        Schema::create('permissao_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('permissao_id')->constrained('permissoes')->restrictOnDelete();
            $table->foreignId('concedida_por_user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('concedida_em');
            $table->foreignId('revogada_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('revogada_em')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'permissao_id']);
        });

        Schema::create('eventos_permissao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('permissao_id')->constrained('permissoes')->restrictOnDelete();
            $table->foreignId('autor_user_id')->constrained('users')->restrictOnDelete();
            $table->enum('acao', ['concessao', 'revogacao']);
            $table->text('motivo');
            $table->dateTime('ocorrido_em');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'ocorrido_em', 'id'], 'eventos_permissao_historico_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_permissao');
        Schema::dropIfExists('permissao_user');
        Schema::dropIfExists('permissoes');
    }
};
