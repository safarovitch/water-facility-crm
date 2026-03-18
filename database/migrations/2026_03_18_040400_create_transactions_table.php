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
        Schema::create('transactions', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $blueprint->string('type'); // deposit, withdrawal, payment, refund
            $blueprint->decimal('amount', 12, 2);
            $blueprint->string('status'); // pending, completed, failed
            $blueprint->nullableMorphs('reference'); // For linking to orders, etc.
            $blueprint->json('meta')->nullable();
            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
