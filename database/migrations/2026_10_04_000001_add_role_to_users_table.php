<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Migration SEPARADA da criação original da tabela "users" (nunca editamos uma
// migration que já existe — cada mudança de estrutura ganha seu próprio arquivo,
// assim o histórico de alterações fica registrado e versionado no Git).
//
// Adiciona o papel do usuário: é essa coluna que o middleware de permissão
// (EnsureUserHasRole) vai consultar antes de liberar o acesso a cada rota.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // ENUM no banco: só aceita um desses três valores, igual fizemos
            // com status_conformidade e outros campos de status no schema.sql
            // original — o próprio banco impede um papel inválido ser salvo.
            $table->enum('role', ['admin', 'funcionario', 'doadora'])
                ->default('doadora')
                ->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
