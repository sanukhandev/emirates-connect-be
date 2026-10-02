<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recipient_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type')->index();
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('data')->nullable();
            $table->string('dedupe_key')->nullable()->unique();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['recipient_user_id', 'created_at', 'id']);
            $table->index(['recipient_user_id', 'read_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
