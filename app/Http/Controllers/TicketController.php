<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\Departamento;
use App\Models\Estado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TicketController extends Controller
{
    public function index()
    {
        $tickets = Ticket::with(['user', 'departamento', 'estado'])->paginate(10);
        $contrastColors = [];
        foreach ($tickets as $ticket) {
            $contrastColors[$ticket->id] = $this->getContrastColor($ticket->estado->color);
        }
        return view('tickets.index_ticket', compact('tickets', 'contrastColors'));
    }

    public function create()
    {
        $departamentos = Departamento::all();
        $estados = Estado::all();
        return view('tickets.create_ticket', compact('departamentos', 'estados'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'required|string',
            'departamento_id' => 'required|exists:departamentos,id',
            'estado_id' => 'required|exists:estados,id',
        ]);

        $ticket = Ticket::create($request->all() + ['user_id' => Auth::id()]);
        return redirect()->route('tickets.index', $ticket)->with('success', 'Ticket creado exitosamente.');
    }

    public function show(Ticket $ticket)
    {
        $ticket->load(['user', 'departamento', 'estado', 'comentarios.user']);
        $contrastColor = $this->getContrastColor($ticket->estado->color);
        return view('tickets.show_ticket', compact('ticket', 'contrastColor'));
    }

    public function edit(Ticket $ticket)
    {
        $departamentos = Departamento::all();
        $estados = Estado::all();
        return view('tickets.edit_ticket', compact('ticket', 'departamentos', 'estados'));
    }

    public function update(Request $request, Ticket $ticket)
    {
        $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'required|string',
            'departamento_id' => 'required|exists:departamentos,id',
            'estado_id' => 'required|exists:estados,id',
        ]);

        $ticket->update($request->all());
        return redirect()->route('tickets.index', $ticket)->with('success', 'Ticket actualizado exitosamente.');
    }

    public function destroy(Ticket $ticket)
    {
        $ticket->delete();
        return redirect()->route('tickets.index')->with('success', 'Ticket eliminado exitosamente.');
    }

    private function getContrastColor($hexcolor)
    {
        // Si el color es #RRGGBB
        $r = hexdec(substr($hexcolor, 1, 2));
        $g = hexdec(substr($hexcolor, 3, 2));
        $b = hexdec(substr($hexcolor, 5, 2));

        // Calcula la luminancia
        $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;

        // Retorna negro o blanco dependiendo de la luminancia
        return ($yiq >= 128) ? '#000000' : '#FFFFFF';
    }
}
