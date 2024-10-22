<?php

namespace App\Http\Controllers;

use App\Models\Comentario;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ComentarioController extends Controller
{
    public function store(Request $request, Ticket $ticket)
    {
        $request->validate([
            'contenido' => 'required|string',
        ]);

        $comentario = new Comentario([
            'contenido' => $request->contenido,
            'user_id' => Auth::id(),
        ]);

        $ticket->comentarios()->save($comentario);

        return redirect()->route('tickets.show', $ticket)->with('success', 'Comentario agregado exitosamente.');
    }

    public function destroy(Comentario $comentario)
    {
        $ticket = $comentario->ticket;
        $comentario->delete();
        return redirect()->route('tickets.show', $ticket)->with('success', 'Comentario eliminado exitosamente.');
    }

    public function edit(Comentario $comentario)
    {
        if (Auth::id() !== $comentario->user_id) {
            return redirect()->back()->with('error', 'No tienes permiso para editar este comentario.');
        }

        return view('comentarios.edit', compact('comentario'));
    }

    public function update(Request $request, Comentario $comentario)
    {
        if (Auth::id() !== $comentario->user_id) {
            return redirect()->back()->with('error', 'No tienes permiso para actualizar este comentario.');
        }

        $request->validate([
            'contenido' => 'required|string',
        ]);

        $comentario->update($request->only('contenido'));

        return redirect()->route('tickets.show', $comentario->ticket_id)->with('success', 'Comentario actualizado exitosamente.');
    }
}
