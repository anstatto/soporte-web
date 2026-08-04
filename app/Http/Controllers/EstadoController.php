<?php

namespace App\Http\Controllers;

use App\Models\Estado;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EstadoController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('view estado'), 403);

        $estados = Estado::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->whereRaw('LOWER(nombre) LIKE ?', ['%'.strtolower($request->search).'%']);
            })
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Estados/Index', [
            'estados' => $estados,
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->can('create estado'), 403);

        $request->validate([
            'nombre' => 'required|string|max:255|unique:estados',
            'color' => 'required|string|max:7',
        ]);
        Estado::create($request->only('nombre', 'color'));

        return redirect()->route('estados.index')->with('success', 'Estado creado.');
    }

    public function update(Request $request, Estado $estado)
    {
        abort_unless(auth()->user()->can('edit estado'), 403);

        $request->validate([
            'nombre' => 'required|string|max:255|unique:estados,nombre,'.$estado->id,
            'color' => 'required|string|max:7',
        ]);
        $estado->update($request->only('nombre', 'color'));

        return redirect()->route('estados.index')->with('success', 'Estado actualizado.');
    }

    public function destroy(Estado $estado)
    {
        abort_unless(auth()->user()->can('delete estado'), 403);
        $estado->delete();

        return redirect()->route('estados.index')->with('success', 'Estado eliminado.');
    }
}
