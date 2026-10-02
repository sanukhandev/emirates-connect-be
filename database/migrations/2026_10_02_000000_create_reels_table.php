<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reels', function (Blueprint $table): void {
            $table->id();
            $table->string('author_type');
            $table->unsignedBigInteger('author_id');
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('caption', 2200)->nullable();
            $table->string('status', 30)->index();
            $table->unsignedSmallInteger('duration_seconds')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('source_disk', 80)->nullable();
            $table->string('source_path')->nullable();
            $table->string('playback_disk', 80)->nullable();
            $table->string('playback_path')->nullable();
            $table->string('thumbnail_disk', 80)->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->text('processing_error')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['author_type', 'author_id', 'status', 'published_at'], 'reels_author_status_index');
            $table->index(['status', 'published_at', 'id'], 'reels_feed_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reels');
    }
};
