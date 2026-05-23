<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // When a delivery short-ships and the admin chooses "deliver later",
            // we spawn a child order carrying the shortfall items. This links
            // that child back to the original so the audit trail is intact.
            // nullOnDelete: if the parent is hard-deleted we don't want to
            // cascade-kill its descendants.
            $table->foreignId('parent_order_id')
                ->nullable()
                ->after('user_id')
                ->constrained('orders')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_order_id');
        });
    }
};
