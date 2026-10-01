<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('display_name')->nullable();
            $table->string('headline', 160)->nullable();
            $table->text('bio')->nullable();
            $table->string('job_title', 120)->nullable();
            $table->string('company_name')->nullable();
            $table->string('industry', 50)->nullable()->index();
            $table->string('emirate', 30)->nullable()->index();
            $table->string('website_url', 2048)->nullable();
            $table->string('linkedin_url', 2048)->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('cover_image_path')->nullable();
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
