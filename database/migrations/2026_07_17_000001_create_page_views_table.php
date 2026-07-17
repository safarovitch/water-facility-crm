<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-URL page-view counters, aggregated by day (one row per path+date).
 * Written by the TrackPageView middleware, read by the admin dashboard.
 * Aggregation keeps the table tiny regardless of traffic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->string('path');
            $table->date('date');
            $table->unsignedBigInteger('views')->default(0);
            $table->timestamps();

            $table->unique(['path', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
