<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\DepartamentoController;
use App\Http\Controllers\ReporteController;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\EstadoController;
use App\Http\Controllers\ComentarioController;

Auth::routes();

Route::get('/', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Route::middleware(['auth'])->group(function () {
    Route::resource('tickets', TicketController::class);
    Route::resource('departamentos', DepartamentoController::class);
    Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes.index');
    Route::post('/reportes/exportar', [ReporteController::class, 'exportar'])->name('reportes.exportar');
    Route::get('/reportes/imprimir', [ReporteController::class, 'imprimir'])->name('reportes.imprimir');
    Route::resource('estados', EstadoController::class);
});

Route::post('tickets/{ticket}/comentarios', [ComentarioController::class, 'store'])->name('comentarios.store');
Route::delete('comentarios/{comentario}', [ComentarioController::class, 'destroy'])->name('comentarios.destroy');

Route::get('/reportes/pdf', [ReporteController::class, 'generarPDF'])->name('reportes.pdf');
