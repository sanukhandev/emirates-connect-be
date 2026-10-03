<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderation_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_id')->nullable()->constrained('reports')->nullOnDelete();
            $table->foreignId('actor_user_id')->constrained('users');
            $table->string('action');
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['target_type', 'target_id', 'created_at']);
            $table->index(['actor_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_audit_logs');
    }
};
