<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\Departamento;
use App\Models\Estado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Notifications\TicketAssignedNotification;
use App\Models\User;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $query = Ticket::with(['departamento', 'estado', 'users']); // Cargar relaciones anticipadamente

        // Filtrar por fecha si se proporciona
        if ($request->filled('fecha_inicio')) {
            $fechaInicio = Carbon::parse($request->input('fecha_inicio'));
            $query->whereDate('fecha_entrega', '>=', $fechaInicio);
        }

        if ($request->filled('fecha_fin')) {
            $fechaFin = Carbon::parse($request->input('fecha_fin'));
            $query->whereDate('fecha_entrega', '<=', $fechaFin);
        }

        // Obtener tickets del día actual si no se filtra
        if (!$request->filled('fecha_inicio') && !$request->filled('fecha_fin')) {
            $query->whereDate('fecha_entrega', Carbon::today());
        }

        $tickets = $query->paginate(10);

        // Mensaje si no hay tickets para el día actual
        if ($tickets->isEmpty() && !$request->filled('fecha_inicio') && !$request->filled('fecha_fin')) {
            session()->flash('message', 'No hay tickets registrados o creados el día de hoy.');
        }

        // Obtener todos los departamentos y estados
        $departamentos = Departamento::all();
        $estados = Estado::all();

        return view('tickets.index_ticket', compact('tickets', 'departamentos', 'estados'));
    }

    private function calculateContrastColors($tickets)
    {
        $contrastColors = [];
        foreach ($tickets as $ticket) {
            // Aquí puedes definir la lógica para calcular el color de contraste
            // Por ejemplo, puedes usar un color fijo o calcularlo basado en el estado
            $contrastColors[$ticket->id] = '#FFFFFF'; // Color blanco como ejemplo
        }
        return $contrastColors;
    }

    private function getContrastColor($hexColor)
    {
        $hex = ltrim($hexColor, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
        return $luminance > 0.5 ? '#000000' : '#FFFFFF';
    }

    public function create()
    {
        $usuarios = User::all(); // Obtener todos los usuarios
        $departamentos = Departamento::all(); // Obtener todos los departamentos
        $estados = Estado::all(); // Obtener todos los estados

        return view('tickets.create_ticket', compact('usuarios', 'departamentos', 'estados'));
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'required|string',
            'departamento_id' => 'required|exists:departamentos,id',
            'estado_id' => 'required|exists:estados,id',
            'fecha_entrega' => 'required|date',
            'recordatorio' => 'required|date',
            'user_ids' => 'array' // Asegúrate de que sea un array
        ]);

        $ticket = Ticket::create($validatedData);

        // Asignar usuarios al ticket
        if (isset($request->user_ids)) {
            $ticket->users()->sync($request->user_ids);
        }

        return redirect()->route('tickets.index')->with('success', 'Ticket creado exitosamente.');
    }

    public function show(Ticket $ticket)
    {
        // Marcar la notificación como leída
        Auth::user()->unreadNotifications
            ->where('data.ticket_id', $ticket->id)
            ->markAsRead();

        $ticket->load(['user', 'departamento', 'estado', 'comentarios.user']);
        return view('tickets.show_ticket', compact('ticket'));
    }

    public function edit(Ticket $ticket)
    {
        // Verifica si el usuario autenticado es el creador del ticket
        if (Auth::id() !== $ticket->user_id) {
            return redirect()->route('tickets.index')->with('error', 'No tienes permiso para editar este ticket.');
        }

        $departamentos = Departamento::all();
        $estados = Estado::all();
        $usuarios = User::all(); // Obtener todos los usuarios para cambiar la asignacin
        return view('tickets.edit_ticket', compact('ticket', 'departamentos', 'estados', 'usuarios'));
    }

    public function update(Request $request, Ticket $ticket)
    {
        $validatedData = $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'required|string',
            'departamento_id' => 'required|exists:departamentos,id',
            'estado_id' => 'required|exists:estados,id',
            'fecha_entrega' => 'nullable|date',
            'recordatorio' => 'nullable|date',
            'user_ids' => 'array', // Aceptar múltiples usuarios
            'user_ids.*' => 'exists:users,id', // Validar que cada ID de usuario exista
        ]);

        // Convertir las fechas a instancias de Carbon
        $validatedData['fecha_entrega'] = Carbon::parse($validatedData['fecha_entrega'] ?? now());
        $validatedData['recordatorio'] = Carbon::parse($validatedData['recordatorio'] ?? now());

        $ticket->update($validatedData);
        $ticket->users()->sync($request->input('user_ids')); // Sincronizar usuarios

        // Verificar si las fechas son iguales a la fecha de creación
        if ($ticket->fecha_entrega->isSameDay($ticket->created_at) && $ticket->recordatorio->isSameDay($ticket->created_at)) {
            session()->flash('success', 'Ticket actualizado exitosamente, pero no se envió notificación porque las fechas son iguales a la creación.');
        } else {
            $this->assignTicket($ticket);
            session()->flash('success', 'Ticket actualizado exitosamente y se envió notificación.');
        }

        return redirect()->route('tickets.show', $ticket);
    }

    public function destroy(Ticket $ticket)
    {
        $ticket->delete();

        // Establecer mensaje flash
        session()->flash('success', 'Ticket eliminado exitosamente.');

        return redirect()->route('tickets.index');
    }

    // Cuando se asigna un ticket
    public function assignTicket(Ticket $ticket)
    {
        $user = User::find($ticket->user_id);
        $user->notify(new TicketAssignedNotification($ticket));
    }
}
