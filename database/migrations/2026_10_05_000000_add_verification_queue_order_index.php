<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verification_requests', function (Blueprint $table): void {
            $table->dropIndex('verification_requests_status_index');
            $table->index(['status', 'submitted_at', 'id'], 'verification_queue_order_index');
        });
    }

    public function down(): void
    {
        Schema::table('verification_requests', function (Blueprint $table): void {
            $table->dropIndex('verification_queue_order_index');
            $table->index('status');
        });
    }
};
