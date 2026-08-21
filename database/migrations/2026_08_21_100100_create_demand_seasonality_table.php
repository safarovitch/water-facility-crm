<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-segment monthly demand index, one row per (segment, month).
 *
 * The table is the forecaster's single source of truth for seasonality, and
 * it holds three kinds of row distinguished by `source`:
 *   prior    - the hard-coded belief from ClientSegment, used until there is
 *              enough history to say otherwise;
 *   blended  - a prior shrunk toward observed data (the normal steady state);
 *   learned  - effectively all data, once a segment/month is well observed;
 *   manual   - a staff override, which recomputation must never clobber.
 *
 * `index` is multiplicative around 1.0: 1.45 means "45% above this segment's
 * own yearly average", never "45% of the business". `sample_size` is the
 * number of segment-months of history behind the figure and drives shrinkage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demand_seasonality', function (Blueprint $table) {
            $table->id();
            $table->string('segment')->index();
            $table->unsignedTinyInteger('month'); // 1-12
            $table->decimal('index', 8, 4)->default(1);
            $table->string('source')->default('prior'); // prior|blended|learned|manual
            $table->unsignedInteger('sample_size')->default(0);
            $table->decimal('observed_index', 8, 4)->nullable(); // raw, pre-shrinkage
            $table->timestamps();

            $table->unique(['segment', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demand_seasonality');
    }
};
