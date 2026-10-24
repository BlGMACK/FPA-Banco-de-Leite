<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contatos_solicitacao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registrado_por_user_id')->constrained('users')->restrictOnDelete();
            $table->enum('canal', ['whatsapp', 'telefone', 'email']);
            $table->enum('resultado', ['tentado', 'realizado', 'sem_resposta', 'numero_incorreto']);
            $table->string('destinatario', 255);
            $table->string('finalidade', 150);
            $table->text('resumo');
            $table->text('proxima_acao')->nullable();
            $table->boolean('identidade_conferida')->default(false);
            $table->boolean('duvida_esclarecida')->default(false);
            $table->dateTime('ocorrido_em');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('contato_pedidos', function (Blueprint $table) {
            $table->foreignId('contato_id')->constrained('contatos_solicitacao')->restrictOnDelete();
            $table->foreignId('solicitacao_id')->constrained('solicitacoes')->restrictOnDelete();
            $table->primary(['contato_id', 'solicitacao_id']);
            $table->index('solicitacao_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contato_pedidos');
        Schema::dropIfExists('contatos_solicitacao');
    }
};
