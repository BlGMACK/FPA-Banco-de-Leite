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
        Schema::create('atendimentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agendamento_id')->unique()->constrained('agendamentos')->restrictOnDelete();
            $table->dateTime('inicio_efetivo_em');
            $table->dateTime('fim_efetivo_em');
            $table->foreignId('responsavel_user_id')->constrained('users')->restrictOnDelete();
            $table->enum('modalidade', ['domicilio', 'banco']);
            $table->string('local_atendimento', 500);
            $table->text('motivo_divergencia')->nullable();
            $table->foreignId('registrado_por_user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('registrado_em');
            $table->timestamps();
            $table->index(['responsavel_user_id', 'inicio_efetivo_em'], 'atendimentos_responsavel_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('atendimentos');
    }
};
