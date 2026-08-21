<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable record of what was forecast, so error can be measured later.
 *
 * A forecast that is never scored cannot be trusted or improved, and the whole
 * accuracy loop hangs off this table: `forecast:snapshot` writes rows for
 * future days, `forecast:reconcile` fills the actual_* columns once those days
 * are in the past, and ForecastAccuracyService turns the difference into the
 * bias correction that the next forecast applies to itself.
 *
 * A row is keyed by (generated_on, horizon_date, scope, scope_key): the same
 * day gets re-forecast every morning, and keeping each vintage is what makes
 * "how good are we 14 days out vs 2 days out" answerable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecast_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('generated_on');
            $table->date('horizon_date');
            $table->unsignedSmallInteger('lead_days'); // horizon_date - generated_on
            $table->string('scope');                   // total|segment|product
            $table->string('scope_key')->nullable();   // segment value or product id

            $table->decimal('predicted_orders', 12, 3)->default(0);
            $table->decimal('predicted_units', 12, 3)->default(0);
            $table->decimal('predicted_revenue', 14, 2)->default(0);
            $table->decimal('units_p10', 12, 3)->default(0);
            $table->decimal('units_p90', 12, 3)->default(0);

            $table->decimal('actual_orders', 12, 3)->nullable();
            $table->decimal('actual_units', 12, 3)->nullable();
            $table->decimal('actual_revenue', 14, 2)->nullable();
            $table->timestamp('reconciled_at')->nullable();

            $table->timestamps();

            $table->unique(['generated_on', 'horizon_date', 'scope', 'scope_key'], 'forecast_snapshots_vintage_unique');
            $table->index(['horizon_date', 'scope']);
            $table->index(['scope', 'scope_key', 'reconciled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecast_snapshots');
    }
};
