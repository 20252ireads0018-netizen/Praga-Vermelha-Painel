<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PersonagemController;
use App\Http\Controllers\LivroController;
use App\Http\Controllers\HistoriaController;
use App\Http\Controllers\MaldicaoController;
use App\Http\Controllers\TabletopController;

Route::get('/', [PersonagemController::class, 'home']);

// ---------- Autenticação ----------
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1')
    ->name('login.attempt');

Route::get('/cadastro', [AuthController::class, 'showRegister'])->name('register');
Route::post('/cadastro', [AuthController::class, 'register'])
    ->middleware('throttle:10,1')
    ->name('register.store');

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// ---------- Área da conta (exige login) ----------
Route::middleware('auth')->group(function () {

    // Fichas
    Route::get('/fichas', [PersonagemController::class, 'fichas'])->name('fichas.create');
    Route::post('/fichafeita', [PersonagemController::class, 'fichafeita'])->name('fichafeita.store');
    Route::get('/personagens', [PersonagemController::class, 'index'])->name('personagens.index');
    Route::get('/personagens/{id}', [PersonagemController::class, 'show'])->name('personagens.show');
    Route::put('/personagens/{id}', [PersonagemController::class, 'update'])->name('personagens.update');
    Route::delete('/personagens/{id}', [PersonagemController::class, 'destroy'])->name('personagens.destroy');

    // Tabletop (mesa virtual) -> resources/views/tabletop/{index,show}.blade.php
    Route::get('/tabletop', [TabletopController::class, 'index'])->name('tabletop.index');
    Route::post('/tabletop', [TabletopController::class, 'store'])->name('tabletop.store');
    Route::get('/tabletop/{mesa}', [TabletopController::class, 'show'])->name('tabletop.show');
    Route::put('/tabletop/{mesa}', [TabletopController::class, 'update'])->name('tabletop.update'); // autosave (JSON)
    Route::delete('/tabletop/{mesa}', [TabletopController::class, 'destroy'])->name('tabletop.destroy');
});

// ---------- Conteúdo público ----------

// Maldições (catálogo em config/maldicoes.php)
Route::get('/maldicoes', [MaldicaoController::class, 'index'])->name('maldicoes.index');
Route::get('/maldicoes/{slug}', [MaldicaoController::class, 'show'])->name('maldicoes.show');

// Livro do sistema (leitor de PDF com chave de acesso própria)
Route::get('/livro', [LivroController::class, 'index'])->name('livro.index');
Route::get('/livro/acesso', [LivroController::class, 'acesso'])->name('livro.acesso');
Route::post('/livro/acesso', [LivroController::class, 'verificar'])
    ->middleware('throttle:10,1')
    ->name('livro.verificar');
Route::get('/livro/players', [LivroController::class, 'players'])->name('livro.players');
Route::get('/livro/pdf/{versao}', [LivroController::class, 'pdf'])
    ->whereIn('versao', ['completo', 'players'])
    ->name('livro.pdf');
Route::post('/livro/sair', [LivroController::class, 'sair'])->name('livro.sair');

Route::get('/historias/{slug}', [HistoriaController::class, 'ler'])->name('historias.ler');