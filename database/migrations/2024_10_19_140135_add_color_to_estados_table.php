<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('estados', function (Blueprint $table) {
            $table->string('color')->default('#000000');
        });
    }

    public function down()
    {
        Schema::table('estados', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
