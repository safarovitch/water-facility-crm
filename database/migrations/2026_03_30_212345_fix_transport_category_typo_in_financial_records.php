<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('financial_records')
            ->where('category', 'Transport')
            ->update(['category' => 'transport']);
    }

    public function down(): void
    {
        DB::table('financial_records')
            ->where('category', 'transport')
            ->update(['category' => 'Transport']);
    }
};
