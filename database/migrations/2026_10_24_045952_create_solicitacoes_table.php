<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitacoes', function (Blueprint $table) {
            $table->id();
            $table->uuid('protocolo')->unique();
            $table->uuid('operacao_id')->unique();
            $table->char('conteudo_hash', 64);
            $table->foreignId('interessada_id')->nullable()->constrained('interessadas')->restrictOnDelete();
            $table->string('nome_completo', 150);
            $table->string('nome_normalizado', 150)->index();
            $table->char('cpf', 11)->index();
            $table->string('telefone_fixo', 20)->nullable()->index();
            $table->string('telefone_celular', 20)->nullable()->index();
            $table->string('email_contato', 255)->index();
            $table->string('municipio', 150);
            $table->string('bairro', 150);
            $table->char('uf', 2);
            $table->char('cep', 8);
            $table->string('logradouro', 255);
            $table->string('numero', 20);
            $table->string('complemento', 150)->nullable();
            $table->enum('whatsapp_telefone', ['fixo', 'celular']);
            $table->boolean('whatsapp_disponivel');
            $table->dateTime('whatsapp_declarado_em');
            $table->text('outros_detalhes')->nullable();
            $table->json('dados_originais');
            $table->enum('canal_origem', ['web', 'telefone', 'presencial']);
            $table->enum('status', ['enviada', 'em_analise', 'admitida', 'arquivada'])->default('enviada');
            $table->foreignId('registrada_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('admitida_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('enviada_em');
            $table->dateTime('admitida_em')->nullable();
            $table->dateTime('arquivada_em')->nullable();
            $table->unsignedInteger('versao')->default(1);
            $table->timestamps();
            $table->index(['status', 'created_at', 'id'], 'solicitacoes_fila_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitacoes');
    }
};
