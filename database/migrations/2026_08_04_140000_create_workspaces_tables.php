<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('workspace_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_admin')->default(false);
            $table->timestamps();
            $table->unique(['workspace_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_workspace_id')->nullable()->after('departamento_id')
                ->constrained('workspaces')->nullOnDelete();
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('workspace_id')->nullable()->after('id')
                ->constrained('workspaces')->nullOnDelete();
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('is_agent')->default(false)->after('guard_name');
        });

        // Workspace por defecto + backfill
        $id = DB::table('workspaces')->insertGetId([
            'name' => 'Principal',
            'slug' => 'principal',
            'description' => 'Área de trabajo por defecto',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userIds = DB::table('users')->pluck('id');
        foreach ($userIds as $uid) {
            DB::table('workspace_user')->insert([
                'workspace_id' => $id,
                'user_id' => $uid,
                'is_admin' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('users')->update(['current_workspace_id' => $id]);
        DB::table('tickets')->update(['workspace_id' => $id]);

        DB::table('roles')->whereIn('name', ['admin', 'soporte'])->update(['is_agent' => true]);
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workspace_id');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_workspace_id');
        });
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('is_agent');
        });
        Schema::dropIfExists('workspace_user');
        Schema::dropIfExists('workspaces');
    }
};
