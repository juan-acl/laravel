<?php

use App\Http\Controllers\RuletaController;
use Illuminate\Support\Facades\Route;

Route::get('/', [RuletaController::class, 'index'])->name('home');
Route::post('/ruleta/iniciar', [RuletaController::class, 'iniciar'])->name('ruleta.iniciar');
Route::post('/ruleta/girar', [RuletaController::class, 'girar'])->name('ruleta.girar');
Route::post('/ruleta/respuesta/{slot}', [RuletaController::class, 'respuesta'])
    ->whereNumber('slot')
    ->name('ruleta.respuesta');
