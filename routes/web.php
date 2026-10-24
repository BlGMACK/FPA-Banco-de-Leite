<?php

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

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin-teste', function () {
        return view('teste-papel', ['papel' => 'Administração']);
    });
});

Route::middleware(['auth', 'role:admin,funcionario'])->group(function () {
    Route::get('/interno-teste', function () {
        return view('teste-papel', [
            'papel' => 'Administração ou Funcionário',
        ]);
    });
});

require __DIR__.'/auth.php';
