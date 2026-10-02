<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('status', 30)->index();
            $table->foreignId('submitted_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('submitted_at')->useCurrent();
            $table->text('rejection_reason')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id', 'status']);
            $table->index(['submitted_at', 'id']);
        });

        Schema::create('verification_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('verification_request_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 80);
            $table->string('storage_disk', 80);
            $table->string('storage_path');
            $table->string('original_filename')->nullable();
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->foreignId('uploaded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index('verification_request_id');
        });

        Schema::create('verification_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('verification_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['verification_request_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_audit_logs');
        Schema::dropIfExists('verification_documents');
        Schema::dropIfExists('verification_requests');
    }
};
