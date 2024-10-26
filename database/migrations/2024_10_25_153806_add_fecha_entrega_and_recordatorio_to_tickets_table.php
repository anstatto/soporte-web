<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dateTime('fecha_entrega')->nullable()->after('estado_id'); // Agregar campo fecha_entrega
            $table->dateTime('recordatorio')->nullable()->after('fecha_entrega'); // Agregar campo recordatorio
        });
    }

    public function down()
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('fecha_entrega'); // Eliminar campo fecha_entrega
            $table->dropColumn('recordatorio'); // Eliminar campo recordatorio
        });
    }
};
