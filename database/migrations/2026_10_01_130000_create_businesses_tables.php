<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('slug')->unique();
            $table->string('tagline', 180)->nullable();
            $table->text('description')->nullable();
            $table->string('industry')->nullable()->index();
            $table->string('emirate')->nullable()->index();
            $table->string('website_url', 2048)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('cover_image_path')->nullable();
            $table->string('status')->default('active')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('business_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('role')->default('editor');
            $table->timestamps();
            $table->unique(['business_id', 'user_id']);
            $table->index(['business_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_members');
        Schema::dropIfExists('businesses');
    }
};
