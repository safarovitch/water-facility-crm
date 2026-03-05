<?php

use App\Enums\ProductLowStockAction;
use App\Enums\ProductStatus;
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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->string('sku')->unique();
            $table->string('slug')->unique();
            $table->json('short_description')->nullable();
            $table->json('description')->nullable();
            $table->decimal('price', 8, 2);
            $table->decimal('sale_price', 8, 2);
            $table->decimal('cost', 8, 2);
            $table->decimal('weight', 4, 2);
            $table->json('dimensions');
            $table->string('currency')->unique();
            $table->integer('quantity');
            $table->boolean('manage_stock')->default(false);
            $table->integer('low_stock_threshold')->default(0);
            $table->enum('low_stock_action', ProductLowStockAction::getValues())->default(ProductLowStockAction::None);
            $table->enum('status', ProductStatus::getValues())->default(ProductStatus::Active);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
