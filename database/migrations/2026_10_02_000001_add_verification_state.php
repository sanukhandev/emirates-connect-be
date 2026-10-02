<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_system_admin')->default(false)->index();
        });

        Schema::table('profiles', function (Blueprint $table): void {
            $table->string('verification_status', 30)->default('not_submitted')->index();
        });

        Schema::table('businesses', function (Blueprint $table): void {
            $table->string('verification_status', 30)->default('not_submitted')->index();
        });
    }

    public function down(): void
    {
        Schema::table('businesses', fn (Blueprint $table) => $table->dropColumn('verification_status'));
        Schema::table('profiles', fn (Blueprint $table) => $table->dropColumn('verification_status'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_system_admin'));
    }
};
