<?php

namespace App\Http\Controllers;

use App\Models\Departamento;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DepartamentoController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizePermission('view departamento');

        $departamentos = Departamento::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->whereRaw('LOWER(nombre) LIKE ?', ['%'.strtolower($request->search).'%']);
            })
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Departamentos/Index', [
            'departamentos' => $departamentos,
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePermission('create departamento');

        $request->validate(['nombre' => 'required|string|max:255|unique:departamentos']);
        Departamento::create($request->only('nombre'));

        return redirect()->route('departamentos.index')->with('success', 'Departamento creado.');
    }

    public function update(Request $request, Departamento $departamento)
    {
        $this->authorizePermission('edit departamento');

        $request->validate([
            'nombre' => 'required|string|max:255|unique:departamentos,nombre,'.$departamento->id,
        ]);
        $departamento->update($request->only('nombre'));

        return redirect()->route('departamentos.index')->with('success', 'Departamento actualizado.');
    }

    public function destroy(Departamento $departamento)
    {
        $this->authorizePermission('delete departamento');
        $departamento->delete();

        return redirect()->route('departamentos.index')->with('success', 'Departamento eliminado.');
    }

    protected function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()->can($permission), 403);
    }
}
