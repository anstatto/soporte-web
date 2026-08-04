<?php

namespace Database\Seeders;

use App\Models\Departamento;
use App\Models\Estado;
use App\Models\Etiqueta;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $departamentos = [
            'Sistemas',
            'Contabilidad',
            'Recursos Humanos',
            'Operaciones',
            'Gerencia',
        ];

        foreach ($departamentos as $nombre) {
            Departamento::firstOrCreate(['nombre' => $nombre]);
        }

        $estados = [
            ['nombre' => 'Recibido', 'color' => '#2F6FAD'],
            ['nombre' => 'Abierto', 'color' => '#B7791F'],
            ['nombre' => 'En progreso', 'color' => '#1E4E79'],
            ['nombre' => 'Cerrado', 'color' => '#1F7A4D'],
        ];

        foreach ($estados as $estado) {
            Estado::firstOrCreate(
                ['nombre' => $estado['nombre']],
                ['color' => $estado['color']]
            );
        }

        $etiquetas = [
            ['nombre' => 'Incidente', 'emoji' => '', 'color' => '#C4554D'],
            ['nombre' => 'Solicitud', 'emoji' => '', 'color' => '#2F6FAD'],
            ['nombre' => 'Mejora', 'emoji' => '', 'color' => '#3D7A5F'],
            ['nombre' => 'Bloqueado', 'emoji' => '', 'color' => '#B7791F'],
            ['nombre' => 'Infraestructura', 'emoji' => '', 'color' => '#5B6B7C'],
            ['nombre' => 'Accesos', 'emoji' => '', 'color' => '#1E4E79'],
            ['nombre' => 'Hardware', 'emoji' => '', 'color' => '#6B5B7A'],
            ['nombre' => 'Software', 'emoji' => '', 'color' => '#3D6B8A'],
        ];

        foreach ($etiquetas as $etiqueta) {
            Etiqueta::updateOrCreate(
                ['nombre' => $etiqueta['nombre']],
                ['emoji' => $etiqueta['emoji'], 'color' => $etiqueta['color']]
            );
        }

        // Suaviza etiquetas ya sembradas con nombres/estilo consumer
        $legacy = [
            'Urgente' => ['emoji' => '', 'color' => '#C4554D'],
            'Alta' => ['emoji' => '', 'color' => '#B7791F'],
            'En Proceso' => ['emoji' => '', 'color' => '#2F6FAD'],
            'Listo' => ['emoji' => '', 'color' => '#3D7A5F'],
            'Cerrado' => ['emoji' => '', 'color' => '#5B6B7C'],
            'Bug' => ['emoji' => '', 'color' => '#C4554D'],
        ];
        foreach ($legacy as $nombre => $meta) {
            Etiqueta::where('nombre', $nombre)->update($meta);
        }

        $this->command?->info('Catálogos base (departamentos, estados y etiquetas) listos.');
    }
}
