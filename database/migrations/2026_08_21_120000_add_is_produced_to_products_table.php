<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Marks which products are actually manufactured in house.
 *
 * The catalogue holds more than the things this business makes: reusable
 * containers and resold side products live there too. Only one product is
 * filled on the line, so the production plan needs to know which — otherwise
 * it renders a card per product and asks staff to fill things nobody makes.
 *
 * Backfilled from the bill of materials, because a product that consumes raw
 * materials is by definition manufactured and one that does not is resold.
 * That makes the flag correct on day one with nothing to configure, while
 * still leaving it editable when the two ever diverge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_produced')->default(false)->after('manage_stock')->index();
        });

        DB::table('products')
            ->whereIn('id', fn ($query) => $query
                ->select('product_id')
                ->from('product_raw_material')
                ->distinct())
            ->update(['is_produced' => true]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['is_produced']);
            $table->dropColumn('is_produced');
        });
    }
};
