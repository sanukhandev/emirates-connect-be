<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reporter_user_id')->constrained('users');
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');
            $table->string('reason');
            $table->text('details')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('resolution')->nullable();
            $table->string('moderation_action')->nullable();
            $table->timestamps();
            $table->index(['target_type', 'target_id', 'status']);
            $table->index(['status', 'created_at', 'id']);
            $table->index(['reason', 'status']);
            $table->index(['reporter_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
