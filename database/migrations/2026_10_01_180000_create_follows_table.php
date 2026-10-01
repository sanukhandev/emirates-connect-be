<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('follower_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('followable_type');
            $table->unsignedBigInteger('followable_id');
            $table->timestamps();
            $table->unique(['follower_user_id', 'followable_type', 'followable_id']);
            $table->index(['followable_type', 'followable_id']);
            $table->index(['follower_user_id', 'created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follows');
    }
};
