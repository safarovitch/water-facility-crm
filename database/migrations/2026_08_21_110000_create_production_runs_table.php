<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ledger of bottles actually filled, and of physical stock counts.
 *
 * The app had no production step at all: `products.quantity` only ever went
 * down (OrderController decrements it when an order is placed) and on the
 * 19L product `manage_stock` is off, so that column is stale. Rather than
 * rewire the order-time stock logic, production gets its own small ledger and
 * "how many are ready" is derived from it.
 *
 * Two row types, which is what makes the balance self-correcting:
 *   production - staff filled this many bottles on this date (adds to stock);
 *   count      - staff physically counted this many on this date, which sets
 *                the balance outright and supersedes everything before it.
 *
 * A warehouse balance always drifts. Letting staff re-count whenever they like
 * and having that simply override the arithmetic is far kinder than asking a
 * non-technical user to hunt for the entry that made the number wrong.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_runs', function (Blueprint $table) {
            $table->id();
            $table->date('production_date');
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('production'); // production|count
            $table->integer('units');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One production total and one count per product per day: staff
            // correct the day's figure rather than appending to it.
            $table->unique(['production_date', 'product_id', 'type']);
            $table->index(['product_id', 'production_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_runs');
    }
};
