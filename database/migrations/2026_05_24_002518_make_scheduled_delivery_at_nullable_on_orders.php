<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The earlier rename migration (delivery_date → scheduled_delivery_at)
     * also called change() to switch to dateTime, which on MySQL strips
     * any previously-implicit nullability. Validation and UI both treat
     * the field as optional already, so make the column match.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dateTime('scheduled_delivery_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dateTime('scheduled_delivery_at')->nullable(false)->change();
        });
    }
};
