<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\Departamento;
use App\Models\Estado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $query = Ticket::with(['user', 'departamento', 'estado']);

        // Filtros
        if ($request->filled('titulo')) {
            $query->where('titulo', 'like', '%' . $request->titulo . '%');
        }

        if ($request->filled('departamento_id')) {
            $query->where('departamento_id', $request->departamento_id);
        }

        if ($request->filled('estado_id')) {
            $query->where('estado_id', $request->estado_id);
        }

        // Filtro por rango de fechas
        if ($request->filled('fecha_inicio') && $request->filled('fecha_fin')) {
            $fechaInicio = Carbon::createFromFormat('Y-m-d\TH:i', $request->fecha_inicio)->startOfDay();
            $fechaFin = Carbon::createFromFormat('Y-m-d\TH:i', $request->fecha_fin)->endOfDay();
            $query->whereBetween('created_at', [$fechaInicio, $fechaFin]);
        }

        // Ordenar
        $orderBy = $request->get('order_by', 'created_at');
        $orderDirection = $request->get('order_direction', 'desc');
        $query->orderBy($orderBy, $orderDirection);

        $tickets = $query->paginate(10)->appends($request->query());

        $departamentos = Departamento::all();
        $estados = Estado::all();

        // Calcula los colores de contraste para cada ticket
        $contrastColors = [];
        foreach ($tickets as $ticket) {
            $contrastColors[$ticket->id] = $this->getContrastColor($ticket->estado->color);
        }

        return view('tickets.index_ticket', compact('tickets', 'departamentos', 'estados', 'contrastColors'));
    }

    private function calculateContrastColors($tickets)
    {
        $contrastColors = [];
        foreach ($tickets as $ticket) {
            $backgroundColor = $ticket->estado->color;
            $contrastColors[$ticket->id] = $this->getContrastColor($backgroundColor);
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
        $departamentos = Departamento::all();
        $estados = Estado::all();
        return view('tickets.create_ticket', compact('departamentos', 'estados'));
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'required|string',
            'departamento_id' => 'required|exists:departamentos,id',
            'estado_id' => 'required|exists:estados,id',
        ]);

        // Asegúrate de que el usuario esté autenticado
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Debes iniciar sesión para crear un ticket.');
        }

        // Agrega el user_id al array de datos validados
        $validatedData['user_id'] = Auth::id();

        $ticket = Ticket::create($validatedData);

        return redirect()->route('tickets.show', $ticket)->with('success', 'Ticket creado exitosamente.');
    }

    public function show(Ticket $ticket)
    {
        $ticket->load(['user', 'departamento', 'estado', 'comentarios.user']);
        return view('tickets.show_ticket', compact('ticket'));
    }

    public function edit(Ticket $ticket)
    {
        $departamentos = Departamento::all();
        $estados = Estado::all();
        return view('tickets.edit_ticket', compact('ticket', 'departamentos', 'estados'));
    }

    public function update(Request $request, Ticket $ticket)
    {
        $validatedData = $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'required|string',
            'departamento_id' => 'required|exists:departamentos,id',
            'estado_id' => 'required|exists:estados,id',
        ]);

        $ticket->update($validatedData);
        return redirect()->route('tickets.show', $ticket)->with('success', 'Ticket actualizado exitosamente.');
    }

    public function destroy(Ticket $ticket)
    {
        $ticket->delete();
        return redirect()->route('tickets.index')->with('success', 'Ticket eliminado exitosamente.');
    }
}
