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
        Schema::create('financial_records', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->decimal('amount', 12, 2);
            $blueprint->string('type'); // income, expense
            $blueprint->string('category'); // wages, rent, partner_payoff, etc.
            $blueprint->text('description')->nullable();
            $blueprint->dateTime('transaction_date');
            $blueprint->foreignId('recorded_by_id')->constrained('users')->cascadeOnDelete();
            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_records');
    }
};
