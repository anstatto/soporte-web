<?php

namespace App\Http\Controllers;

use App\Models\Departamento;
use Illuminate\Http\Request;

class DepartamentoController extends Controller
{
    public function index(Request $request)
    {
        $query = Departamento::query();

        if ($request->has('search')) {
            $query->whereRaw('LOWER(nombre) LIKE ?', ['%' . strtolower($request->search) . '%']);
        }

        $departamentos = $query->paginate(10);

        return view('departamentos.index_departamento', compact('departamentos'));
    }

    public function create()
    {
        return view('departamentos.create_departamento');
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255|unique:departamentos',
        ]);

        Departamento::create($request->all());
        return redirect()->route('departamentos.index')->with('success', 'Departamento creado exitosamente.');
    }

    public function show(Departamento $departamento)
    {
        $ticketsAbiertos = $departamento->getTicketsAbiertos();
        return view('departamentos.show_departamento', compact('departamento', 'ticketsAbiertos'));
    }

    public function edit(Departamento $departamento)
    {
        return view('departamentos.edit_departamento', compact('departamento'));
    }

    public function update(Request $request, Departamento $departamento)
    {
        $request->validate([
            'nombre' => 'required|string|max:255|unique:departamentos,nombre,' . $departamento->id,
        ]);

        $departamento->update($request->all());
        return redirect()->route('departamentos.index');
    }

    public function destroy(Departamento $departamento)
    {
        $departamento->delete();
        return redirect()->route('departamentos.index')->with('success', 'Departamento eliminado exitosamente.');
    }
}
