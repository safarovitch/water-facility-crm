<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::table('users', function (Blueprint $table) {
      // Phone is now the primary identifier; email is optional. The existing
      // unique index on email stays in place, but the column is nullable so
      // phone-only signups (and walk-in shell clients) don't need a fake
      // address.
      $table->string('email')->nullable()->change();
    });
  }

  public function down(): void
  {
    Schema::table('users', function (Blueprint $table) {
      $table->string('email')->nullable(false)->change();
    });
  }
};
