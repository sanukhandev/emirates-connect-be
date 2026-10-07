<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table): void {
            $table->foreignId('post_id')->nullable()->change();
            $table->foreignId('reel_id')->nullable()->after('post_id')->constrained()->nullOnDelete();
            $table->index(['reel_id', 'parent_id', 'created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table): void {
            $table->dropForeign(['reel_id']);
            $table->dropIndex(['reel_id', 'parent_id', 'created_at', 'id']);
            $table->dropColumn('reel_id');
            $table->foreignId('post_id')->nullable(false)->change();
        });
    }
};
