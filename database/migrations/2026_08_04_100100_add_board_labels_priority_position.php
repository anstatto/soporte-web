<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('tickets', 'prioridad')) {
                $table->string('prioridad')->default('media')->after('estado_id');
            }
            if (! Schema::hasColumn('tickets', 'position')) {
                $table->unsignedInteger('position')->default(0)->after('prioridad');
            }
        });

        if (! Schema::hasTable('etiquetas')) {
            Schema::create('etiquetas', function (Blueprint $table) {
                $table->id();
                $table->string('nombre');
                $table->string('color', 20)->default('#579DFF');
                $table->string('emoji', 16)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('etiqueta_ticket')) {
            Schema::create('etiqueta_ticket', function (Blueprint $table) {
                $table->id();
                $table->foreignId('etiqueta_id')->constrained('etiquetas')->cascadeOnDelete();
                $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['etiqueta_id', 'ticket_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('etiqueta_ticket');
        Schema::dropIfExists('etiquetas');

        Schema::table('tickets', function (Blueprint $table) {
            if (Schema::hasColumn('tickets', 'position')) {
                $table->dropColumn('position');
            }
            if (Schema::hasColumn('tickets', 'prioridad')) {
                $table->dropColumn('prioridad');
            }
        });
    }
};
