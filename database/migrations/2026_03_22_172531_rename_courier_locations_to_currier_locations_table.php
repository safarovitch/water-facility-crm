<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('courier_locations')) {
            Schema::rename('courier_locations', 'currier_locations');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('currier_locations')) {
            Schema::rename('currier_locations', 'courier_locations');
        }
    }
};
