<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('chat_type', 20); // ticket|dm
            $table->unsignedBigInteger('chat_id');
            $table->timestamp('last_read_at')->nullable();
            $table->boolean('marked_unread')->default(false);
            $table->timestamp('pinned_at')->nullable();
            $table->timestamp('muted_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('starred_at')->nullable(); // leer más tarde
            $table->timestamps();

            $table->unique(['user_id', 'chat_type', 'chat_id']);
            $table->index(['user_id', 'chat_type', 'starred_at']);
            $table->index(['user_id', 'chat_type', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_states');
    }
};
