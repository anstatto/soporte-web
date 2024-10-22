<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\DepartamentoController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\EstadoController;
use App\Http\Controllers\ComentarioController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\UserController; // Asegúrate de importar el controlador de usuario
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
    Route::get('comentarios/{comentario}/edit', [ComentarioController::class, 'edit'])->name('comentarios.edit');
    Route::put('comentarios/{comentario}', [ComentarioController::class, 'update'])->name('comentarios.update');

    // Ruta para el perfil del usuario
    Route::get('/perfil', [UserController::class, 'show'])->name('perfil.show');
    Route::put('/perfil', [UserController::class, 'update'])->name('perfil.update');
});

Route::get('/reportes/pdf', [ReporteController::class, 'generarPDF'])->name('reportes.pdf');

Route::get('/notifications', function () {
    return Auth::user()->unreadNotifications;
})->middleware('auth');
Route::post('/notifications/{id}/mark-as-read', function ($id) {
    $user = Auth::user();
    $notification = $user->unreadNotifications->find($id);
    if ($notification) {
        $notification->markAsRead();
    }
    return response()->noContent();
});
