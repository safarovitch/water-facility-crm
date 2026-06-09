<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Empties the courier chose to collect on a later visit instead of charging
 * the client a deposit for them. These count against neither the deposit
 * charge nor current stock until they're actually collected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_returned_materials', function (Blueprint $table) {
            $table->integer('deferred_quantity')->default(0)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('order_returned_materials', function (Blueprint $table) {
            $table->dropColumn('deferred_quantity');
        });
    }
};
