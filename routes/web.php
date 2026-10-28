<?php

use App\Http\Controllers\AgendaController;
use App\Http\Controllers\AtendimentoSolicitacaoController;
use App\Http\Controllers\FuncionarioController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SolicitacaoController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('inicio');
});

Route::get('/doar', [SolicitacaoController::class, 'create'])->name('solicitacoes.create');
Route::post('/pedidos-de-cadastro', [SolicitacaoController::class, 'store'])
    ->middleware('throttle:receber-solicitacoes')->name('solicitacoes.store');
Route::get('/pedido-recebido', [SolicitacaoController::class, 'recebida'])->name('solicitacoes.recebida');

Route::middleware(['auth', 'can:atender-solicitacoes'])->prefix('interno/solicitacoes')->name('interno.solicitacoes.')->group(function (): void {
    Route::get('/', [SolicitacaoController::class, 'index'])->name('index');
    Route::get('/nova', [SolicitacaoController::class, 'create'])->name('create');
    Route::post('/', [SolicitacaoController::class, 'store'])->middleware('throttle:receber-solicitacoes')->name('store');
    Route::get('/{solicitacao}', [SolicitacaoController::class, 'show'])->whereNumber('solicitacao')->name('show');
    Route::post('/{solicitacao}/analise', [AtendimentoSolicitacaoController::class, 'analise'])->whereNumber('solicitacao')->name('analise.store');
    Route::post('/{solicitacao}/contatos', [AtendimentoSolicitacaoController::class, 'contato'])->whereNumber('solicitacao')->name('contatos.store');
    foreach (['conferencias', 'resolucoes', 'admissao'] as $acao) {
        Route::post('/{solicitacao}/'.$acao, [AtendimentoSolicitacaoController::class, 'decisao'])->whereNumber('solicitacao')->name($acao.'.store');
    }
});

Route::middleware(['auth', 'can:atender-solicitacoes'])->prefix('interno/acompanhamentos')->name('interno.acompanhamentos.')->group(function (): void {
    Route::get('/', [AtendimentoSolicitacaoController::class, 'index'])->name('index');
    Route::get('/{solicitacao}', [AtendimentoSolicitacaoController::class, 'show'])->whereNumber('solicitacao')->name('show');
});

Route::middleware(['auth', 'can:gerenciar-agenda'])->prefix('interno/agenda')->name('interno.agenda.')->group(function (): void {
    Route::get('/', [AgendaController::class, 'index'])->name('index');
    Route::get('/acompanhamentos/{solicitacao}', [AgendaController::class, 'show'])->whereNumber('solicitacao')->name('acompanhamentos.show');
    Route::post('/acompanhamentos/{solicitacao}/contatos', [AgendaController::class, 'contato'])->whereNumber('solicitacao')->name('contatos.store');
    Route::post('/acompanhamentos/{solicitacao}/agendamentos', [AgendaController::class, 'store'])->whereNumber('solicitacao')->name('store');
    foreach (['reagendamentos', 'cancelamento', 'nao-realizacao'] as $acao) {
        Route::post('/{agendamento}/'.$acao, [AgendaController::class, 'alterar'])->whereNumber('agendamento')->name($acao.'.store');
    }
});

Route::middleware(['auth', 'can:criar-funcionarios'])->prefix('interno/funcionarios')->name('interno.funcionarios.')->group(function (): void {
    Route::get('/novo', [FuncionarioController::class, 'create'])->name('create');
    Route::post('/', [FuncionarioController::class, 'store'])->name('store');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');
});

require __DIR__.'/auth.php';
