<?php

namespace App\Http\Controllers;

use App\Models\Etiqueta;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EtiquetaController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('view etiqueta'), 403);

        $etiquetas = Etiqueta::query()
            ->withCount('tickets')
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->whereRaw('LOWER(nombre) LIKE ?', ['%'.strtolower($request->search).'%']);
            })
            ->orderBy('nombre')
            ->paginate(24)
            ->withQueryString();

        return Inertia::render('Etiquetas/Index', [
            'etiquetas' => $etiquetas,
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->can('create etiqueta'), 403);

        $data = $request->validate([
            'nombre' => 'required|string|max:80|unique:etiquetas,nombre',
            'color' => 'required|string|max:7',
            'emoji' => 'nullable|string|max:8',
        ]);

        Etiqueta::create([
            'nombre' => $data['nombre'],
            'color' => $data['color'],
            'emoji' => $data['emoji'] ?? '',
        ]);

        return redirect()->route('etiquetas.index')->with('success', 'Etiqueta creada.');
    }

    public function update(Request $request, Etiqueta $etiqueta)
    {
        abort_unless(auth()->user()->can('edit etiqueta'), 403);

        $data = $request->validate([
            'nombre' => 'required|string|max:80|unique:etiquetas,nombre,'.$etiqueta->id,
            'color' => 'required|string|max:7',
            'emoji' => 'nullable|string|max:8',
        ]);

        $etiqueta->update([
            'nombre' => $data['nombre'],
            'color' => $data['color'],
            'emoji' => $data['emoji'] ?? '',
        ]);

        return redirect()->route('etiquetas.index')->with('success', 'Etiqueta actualizada.');
    }

    public function destroy(Etiqueta $etiqueta)
    {
        abort_unless(auth()->user()->can('delete etiqueta'), 403);
        $etiqueta->tickets()->detach();
        $etiqueta->delete();

        return redirect()->route('etiquetas.index')->with('success', 'Etiqueta eliminada.');
    }
}
