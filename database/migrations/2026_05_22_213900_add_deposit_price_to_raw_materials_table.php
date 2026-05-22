<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raw_materials', function (Blueprint $table) {
            // Charged to the client when a reusable material (e.g. a 19L
            // bottle) isn't returned. Different from cost_per_unit, which
            // is what the business pays to acquire the raw material — this
            // is the replacement price quoted to the customer (cost + fee).
            $table->decimal('deposit_price', 15, 2)->default(0)->after('cost_per_unit');
        });
    }

    public function down(): void
    {
        Schema::table('raw_materials', function (Blueprint $table) {
            $table->dropColumn('deposit_price');
        });
    }
};
