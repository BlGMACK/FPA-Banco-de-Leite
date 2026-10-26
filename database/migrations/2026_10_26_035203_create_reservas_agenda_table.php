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
        Schema::create('reservas_agenda', function (Blueprint $table) {
            $table->foreignId('solicitacao_id')->primary()->constrained('solicitacoes')->restrictOnDelete();
            $table->foreignId('agendamento_id')->unique()->constrained('agendamentos')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservas_agenda');
    }
};
