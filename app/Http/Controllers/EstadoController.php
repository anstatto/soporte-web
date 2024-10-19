<?php

namespace App\Http\Controllers;

use App\Models\Estado;
use Illuminate\Http\Request;

class EstadoController extends Controller
{
    public function index()
    {
        $estados = Estado::all();
        return view('estados.index_estado', compact('estados'));
    }

    public function create()
    {
        return view('estados.create_estado');
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255|unique:estados',
            'color' => 'required|string|max:7',
        ]);

        Estado::create($request->all());
        return redirect()->route('estados.index')->with('success', 'Estado creado exitosamente.');
    }

    public function show(Estado $estado)
    {
        $contrastColor = $this->getContrastColor($estado->color);
        return view('estados.show_estado', compact('estado', 'contrastColor'));
    }

    public function edit(Estado $estado)
    {
        return view('estados.edit_estado', compact('estado'));
    }

    public function update(Request $request, Estado $estado)
    {
        $request->validate([
            'nombre' => 'required|string|max:255|unique:estados,nombre,' . $estado->id,
            'color' => 'required|string|max:7',
        ]);

        $estado->update($request->all());
        return redirect()->route('estados.index')->with('success', 'Estado actualizado exitosamente.');
    }

    public function destroy(Estado $estado)
    {
        $estado->delete();
        return redirect()->route('estados.index')->with('success', 'Estado eliminado exitosamente.');
    }

    private function getContrastColor($hexcolor)
    {
        $r = hexdec(substr($hexcolor, 1, 2));
        $g = hexdec(substr($hexcolor, 3, 2));
        $b = hexdec(substr($hexcolor, 5, 2));

        $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;

        return ($yiq >= 128) ? '#000000' : '#FFFFFF';
    }
}
