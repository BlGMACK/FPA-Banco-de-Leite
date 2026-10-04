<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('inicio');
});

// --- Rotas de teste do middleware de permissão (role) -----------------
// Objetivo: provar, antes de construir qualquer tela de verdade, que o
// bloqueio por papel funciona no servidor. Depois que o grupo validar
// isso manualmente (ver checklist abaixo), estas duas rotas somem e
// dão lugar aos módulos reais (solicitação, triagem, etc.).
//
// 'auth' primeiro: exige estar logado. 'role:...' depois: exige o papel
// certo. A ORDEM dos middlewares importa — sem 'auth' antes, $request
// ->user() dentro do EnsureUserHasRole viria vazio para um visitante.

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin-teste', function () {
        return view('teste-papel', ['papel' => 'Administração']);
    });
});

Route::middleware(['auth', 'role:admin,funcionario'])->group(function () {
    Route::get('/interno-teste', function () {
        return view('teste-papel', ['papel' => 'Administração ou Funcionário']);
    });
});