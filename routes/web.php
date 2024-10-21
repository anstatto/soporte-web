<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\DepartamentoController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\EstadoController;
use App\Http\Controllers\ComentarioController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Auth;

Auth::routes();

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::middleware(['auth'])->group(function () {
    // Rutas de Tickets
    Route::resource('tickets', TicketController::class);

    // Rutas de Departamentos
    Route::resource('departamentos', DepartamentoController::class);

    // Rutas de Estados
    Route::resource('estados', EstadoController::class);

    // Rutas de Reportes
    Route::prefix('reportes')->name('reportes.')->group(function () {
        Route::get('/', [ReporteController::class, 'index'])->name('index');
        Route::post('/exportar', [ReporteController::class, 'exportar'])->name('exportar');
        Route::post('/imprimir', [ReporteController::class, 'imprimir'])->name('imprimir'); // Cambiado a POST
    });

    // Rutas de Comentarios
    Route::post('tickets/{ticket}/comentarios', [ComentarioController::class, 'store'])->name('comentarios.store');
    Route::delete('comentarios/{comentario}', [ComentarioController::class, 'destroy'])->name('comentarios.destroy');
});

Route::get('/reportes/pdf', [ReporteController::class, 'generarPDF'])->name('reportes.pdf');
