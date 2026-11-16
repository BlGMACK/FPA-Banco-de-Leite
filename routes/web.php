<?php

use App\Http\Controllers\AgendaController;
use App\Http\Controllers\AtendimentoSolicitacaoController;
use App\Http\Controllers\AvaliacaoController;
use App\Http\Controllers\DoadoraController;
use App\Http\Controllers\ExameController;
use App\Http\Controllers\FuncionarioController;
use App\Http\Controllers\PermissaoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RelatorioVisitaController;
use App\Http\Controllers\RetencaoController;
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
    Route::post('/{solicitacao}/correcao', [AtendimentoSolicitacaoController::class, 'correcao'])->whereNumber('solicitacao')->name('correcao.store');
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

Route::middleware(['auth', 'can:gerenciar-permissoes'])->prefix('interno/permissoes')->name('interno.permissoes.')->group(function (): void {
    Route::get('/', [PermissaoController::class, 'index'])->name('index');
    Route::get('/{funcionario}', [PermissaoController::class, 'show'])->whereNumber('funcionario')->name('show');
    foreach (['concessoes', 'revogacoes'] as $acao) {
        Route::post('/{funcionario}/'.$acao, [PermissaoController::class, 'alterar'])->whereNumber('funcionario')->name($acao.'.store');
    }
    foreach (['desativacao', 'reativacao'] as $acao) {
        Route::post('/{funcionario}/'.$acao, [PermissaoController::class, 'situacao'])->whereNumber('funcionario')->name($acao.'.store');
    }
});

Route::middleware(['auth', 'can:gerenciar-retencao'])->prefix('interno/retencao')->name('interno.retencao.')->group(function (): void {
    Route::get('/', [RetencaoController::class, 'index'])->name('index');
    Route::post('/descarte', [RetencaoController::class, 'descartar'])->name('descarte.store');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Prévia visual da coleta: a coleta ainda não existe no backend e esta tela não grava dados.
Route::get('/interno/coletas', function () {
    return view('coletas.registrar');
})->middleware(['auth', 'verified'])->name('interno.coletas.registrar');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');
});

require __DIR__.'/auth.php';

Route::middleware(['auth', 'can:consultar-relatorios-visita'])->prefix('interno/relatorios')->name('interno.relatorios.')->group(function (): void {
    Route::get('/', [RelatorioVisitaController::class, 'index'])->name('index');
    Route::get('/{relatorio}', [RelatorioVisitaController::class, 'show'])->whereNumber('relatorio')->name('show');
    Route::middleware('can:registrar-relatorios-visita')->group(function (): void {
        Route::get('/visitas/{agendamento}', [RelatorioVisitaController::class, 'create'])->whereNumber('agendamento')->name('visitas.create');
        Route::post('/visitas/{agendamento}', [RelatorioVisitaController::class, 'store'])->whereNumber('agendamento')->name('visitas.store');
        foreach (['rascunho', 'finalizar', 'correcao'] as $acao) {
            Route::post('/{relatorio}/'.$acao, [RelatorioVisitaController::class, 'update'])->whereNumber('relatorio')->name($acao);
        }
    });
});

Route::middleware(['auth', 'can:consultar-avaliacoes'])->prefix('interno/avaliacoes')->name('interno.avaliacoes.')->group(function (): void {
    Route::get('/', [AvaliacaoController::class, 'index'])->name('index');
    Route::get('/{avaliacao}', [AvaliacaoController::class, 'show'])->whereNumber('avaliacao')->name('show');
    Route::post('/{avaliacao}/analise', [AvaliacaoController::class, 'iniciarAnalise'])->middleware('can:avaliar-doadoras')->whereNumber('avaliacao')->name('analise.iniciar');
    Route::post('/{avaliacao}/analise/encerrar', [AvaliacaoController::class, 'encerrarAnalise'])->middleware('can:avaliar-doadoras')->whereNumber('avaliacao')->name('analise.encerrar');
    Route::post('/{avaliacao}/decisoes', [AvaliacaoController::class, 'store'])->middleware('can:avaliar-doadoras')->whereNumber('avaliacao')->name('decisoes.store');
    Route::post('/{avaliacao}/habilitacao', [AvaliacaoController::class, 'habilitar'])->middleware('can:avaliar-doadoras')->whereNumber('avaliacao')->name('habilitacao.store');
});

Route::middleware(['auth', 'can:consultar-avaliacoes'])->prefix('interno/doadoras')->name('interno.doadoras.')->group(function (): void {
    Route::get('/', [DoadoraController::class, 'index'])->name('index');
    Route::get('/{doadora}', [DoadoraController::class, 'show'])->whereNumber('doadora')->name('show');
    Route::post('/{doadora}/eventos', [DoadoraController::class, 'registrar'])->middleware('can:avaliar-doadoras')->whereNumber('doadora')->name('eventos.store');
});

Route::middleware(['auth', 'can:consultar-exames'])->prefix('interno/exames')->name('interno.exames.')->group(function (): void {
    Route::get('/', [ExameController::class, 'index'])->name('index');
    Route::get('/{avaliacao}', [ExameController::class, 'show'])->whereNumber('avaliacao')->name('show');
    Route::post('/{avaliacao}/itens', [ExameController::class, 'incluir'])->middleware('can:registrar-exames')->whereNumber('avaliacao')->name('itens.incluir');
    Route::post('/itens/{item}/eventos', [ExameController::class, 'registrar'])->middleware('can:registrar-exames')->whereNumber('item')->name('itens.registrar');
    Route::post('/itens/{item}/anexos', [ExameController::class, 'anexar'])->middleware('can:registrar-exames')->whereNumber('item')->name('itens.anexar');
    Route::get('/anexos/{anexo}', [ExameController::class, 'arquivo'])->whereNumber('anexo')->name('anexos.arquivo');
});
