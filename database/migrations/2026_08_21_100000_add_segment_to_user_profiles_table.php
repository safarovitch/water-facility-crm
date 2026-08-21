<?php

use App\Enums\ClientSegment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demand segmentation for clients.
 *
 * `segment_source` exists so re-running the classifier can never overwrite a
 * human decision: the rules and AI passes only ever touch rows they own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('segment')->default(ClientSegment::Unknown->value)->after('type')->index();
            $table->string('segment_source')->default('default')->after('segment'); // default|rules|ai|manual
            $table->float('segment_confidence')->nullable()->after('segment_source');
            $table->timestamp('segment_classified_at')->nullable()->after('segment_confidence');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropIndex(['segment']);
            $table->dropColumn(['segment', 'segment_source', 'segment_confidence', 'segment_classified_at']);
        });
    }
};
