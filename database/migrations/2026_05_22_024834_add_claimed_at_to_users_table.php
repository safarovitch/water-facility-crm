<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::table('users', function (Blueprint $table) {
      // claimed_at = null  → shell user (auto-created via an order or admin
      //                       walk-in entry; available to be adopted by a
      //                       future self-registration with the same email).
      // claimed_at = set   → fully self-registered or claimed account.
      $table->timestamp('claimed_at')->nullable()->after('last_active_at');
    });

    // Existing users predate this feature — treat them all as claimed.
    DB::table('users')->whereNull('claimed_at')->update(['claimed_at' => now()]);
  }

  public function down(): void
  {
    Schema::table('users', function (Blueprint $table) {
      $table->dropColumn('claimed_at');
    });
  }
};
