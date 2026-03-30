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
        Schema::table('financial_records', function (Blueprint $table) {
            $table->unsignedBigInteger('recordable_id')->nullable()->after('id');
            $table->string('recordable_type')->nullable()->after('recordable_id');
            $table->index(['recordable_id', 'recordable_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financial_records', function (Blueprint $table) {
            $table->dropIndex(['recordable_id', 'recordable_type']);
            $table->dropColumn(['recordable_id', 'recordable_type']);
        });
    }
};
