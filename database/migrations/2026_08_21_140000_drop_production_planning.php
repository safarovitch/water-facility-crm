<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the production-planning feature.
 *
 * The two migrations that created these were deleted rather than reversed, so
 * a fresh database never builds them and this migration is a no-op there. It
 * exists only to clean up databases that already ran them; the guards make it
 * safe either way. The ledger was never used in anger — nothing read it but
 * the page that has been removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('production_runs');

        if (! Schema::hasColumn('products', 'is_produced')) {
            return;
        }

        // SQLite refuses to drop a column an index still references, so the
        // index goes first. Guarded rather than assumed: this has to run
        // against databases that may or may not have reached the migration
        // that created it.
        if (Schema::hasIndex('products', 'products_is_produced_index')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex('products_is_produced_index');
            });
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_produced');
        });
    }

    public function down(): void
    {
        // Deliberately irreversible: the feature is gone, not paused.
    }
};
