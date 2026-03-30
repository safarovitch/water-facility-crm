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
        \Illuminate\Support\Facades\DB::table('financial_records')
            ->where('category', 'Utility')
            ->update(['category' => 'utilities']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reversed version needed for a data fix
    }
};
