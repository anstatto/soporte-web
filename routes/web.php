<?php

use App\Http\Controllers\ChatStateController;
use App\Http\Controllers\ComentarioController;
use App\Http\Controllers\ConversacionController;
use App\Http\Controllers\DepartamentoController;
use App\Http\Controllers\EstadoController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\RoleMiddleware;

Auth::routes(['register' => false]);

Route::middleware(['auth'])->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/portal', [PortalController::class, 'index'])->name('portal');

    Route::get('tickets/board', [TicketController::class, 'board'])->name('tickets.board');
    Route::post('tickets/quick', [TicketController::class, 'quickStore'])->name('tickets.quick');
    Route::patch('tickets/board/reorder', [TicketController::class, 'reorder'])->name('tickets.board.reorder');
    Route::get('tickets/{ticket}/card', [TicketController::class, 'card'])->name('tickets.card');
    Route::patch('tickets/{ticket}/card', [TicketController::class, 'updateCard'])->name('tickets.card.update');
    Route::patch('tickets/{ticket}/estado', [TicketController::class, 'updateEstado'])->name('tickets.estado');
    Route::get('tickets/{ticket}/comentarios/poll', [TicketController::class, 'pollComentarios'])->name('tickets.comentarios.poll');
    Route::post('tickets/{ticket}/typing', [TicketController::class, 'typing'])->name('tickets.typing');
    Route::resource('tickets', TicketController::class);

    Route::resource('departamentos', DepartamentoController::class)->except(['create', 'edit', 'show']);
    Route::resource('estados', EstadoController::class)->except(['create', 'edit', 'show']);

    Route::prefix('reportes')->name('reportes.')->group(function () {
        Route::get('/', [ReporteController::class, 'index'])->name('index');
        Route::post('/exportar', [ReporteController::class, 'exportar'])->name('exportar');
    });

    Route::post('tickets/{ticket}/comentarios', [ComentarioController::class, 'store'])->name('comentarios.store');
    Route::delete('comentarios/{comentario}', [ComentarioController::class, 'destroy'])->name('comentarios.destroy');
    Route::get('comentarios/{comentario}/edit', [ComentarioController::class, 'edit'])->name('comentarios.edit');
    Route::put('comentarios/{comentario}', [ComentarioController::class, 'update'])->name('comentarios.update');

    Route::post('/chats/open', [ConversacionController::class, 'open'])->name('chats.open');
    Route::get('/chats/{conversacion}', [ConversacionController::class, 'show'])->name('chats.show');
    Route::post('/chats/{conversacion}/mensajes', [ConversacionController::class, 'storeMensaje'])->name('chats.mensajes');
    Route::get('/chats/{conversacion}/poll', [ConversacionController::class, 'poll'])->name('chats.poll');
    Route::post('/chats/{conversacion}/typing', [ConversacionController::class, 'typing'])->name('chats.typing');
    Route::post('/chat-state', [ChatStateController::class, 'update'])->name('chat-state.update');

    Route::get('/perfil', [UserController::class, 'show'])->name('perfil.show');
    Route::put('/perfil', [UserController::class, 'update'])->name('perfil.update');

    Route::post('/workspaces/switch', [WorkspaceController::class, 'switch'])->name('workspaces.switch');

    Route::middleware([RoleMiddleware::class.':admin'])->group(function () {
        Route::post('/users/assign-roles', [UserController::class, 'assignRoles'])->name('users.assignRoles');
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::put('roles/{id}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{id}', [RoleController::class, 'destroy'])->name('roles.destroy');
        Route::get('workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
        Route::post('workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
        Route::put('workspaces/{workspace}', [WorkspaceController::class, 'update'])->name('workspaces.update');
        Route::delete('workspaces/{workspace}', [WorkspaceController::class, 'destroy'])->name('workspaces.destroy');
    });

    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'updateUser'])->name('users.update');

    Route::get('/notificaciones', [NotificationController::class, 'page'])->name('notifications.page');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/mark-as-read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/mark-ticket-read', [NotificationController::class, 'markTicketRead'])->name('notifications.ticket-read');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
});
