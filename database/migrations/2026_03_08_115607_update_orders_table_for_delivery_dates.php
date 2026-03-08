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
    Schema::table('orders', function (Blueprint $table) {
      $table->renameColumn('delivery_date', 'scheduled_delivery_at');
    });

    Schema::table('orders', function (Blueprint $table) {
      $table->dateTime('scheduled_delivery_at')->change();
      $table->dateTime('actual_delivery_at')->after('scheduled_delivery_at')->nullable();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('orders', function (Blueprint $table) {
      $table->date('scheduled_delivery_at')->change();
      $table->renameColumn('scheduled_delivery_at', 'delivery_date');
      $table->dropColumn('actual_delivery_at');
    });
  }
};
