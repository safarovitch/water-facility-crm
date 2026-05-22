<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Amount added to the order at delivery for unreturned reusable
            // containers (e.g. the 19L bottle deposit). Computed on delivery
            // from (expected reusable units in the BOM) − (returned units).
            $table->decimal('deposit_charge', 15, 2)->default(0)->after('discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('deposit_charge');
        });
    }
};
