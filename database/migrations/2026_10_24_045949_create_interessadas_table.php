<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interessadas', function (Blueprint $table) {
            $table->id();
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
            $table->foreignId('confirmada_por_user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('confirmada_em');
            $table->unsignedInteger('versao')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interessadas');
    }
};
