<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Actual quantity handed to the client at delivery time. NULL while
            // the order is in flight (treat as "= quantity" for display); set
            // explicitly on delivery confirmation so partial deliveries can be
            // billed and restocked correctly.
            $table->integer('delivered_quantity')->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('delivered_quantity');
        });
    }
};
