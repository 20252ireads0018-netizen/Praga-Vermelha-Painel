<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PersonagemController;

Route::get('/', [PersonagemController::class, 'home']);
Route::get('/fichas', [PersonagemController::class, 'fichas'])->name('fichas.create');
Route::post('/fichafeita', [PersonagemController::class, 'fichafeita'])->name('fichafeita.store');
Route::get('/personagens', [PersonagemController::class, 'index'])->name('personagens.index');
Route::get('/personagens/{id}', [PersonagemController::class, 'show'])->name('personagens.show');
Route::put('/personagens/{id}', [PersonagemController::class, 'update'])->name('personagens.update');
Route::delete('/personagens/{id}', [PersonagemController::class, 'destroy'])->name('personagens.destroy');