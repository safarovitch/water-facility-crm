<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manual delivery sequence — the order in which the courier drops the
 * packages off.
 *
 * Nullable on purpose: a brand new order has not been sequenced yet, and
 * "unsequenced" has to be distinguishable from "first". Every list that
 * orders by this column pushes NULLs to the end rather than to the front.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('delivery_sort_order')->nullable()->after('scheduled_delivery_at');
            $table->index('delivery_sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['delivery_sort_order']);
            $table->dropColumn('delivery_sort_order');
        });
    }
};
