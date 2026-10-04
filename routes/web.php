<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('inicio');
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

require __DIR__ . '/auth.php';
