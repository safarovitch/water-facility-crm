<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::table('users', function (Blueprint $table) {
      // Set when the user has proven control of a phone via a Telegram OTP.
      // Legacy email-authed users start NULL → middleware will redirect them
      // to the phone-setup screen on next request.
      $table->timestamp('phone_verified_at')->nullable()->after('claimed_at');
    });
  }

  public function down(): void
  {
    Schema::table('users', function (Blueprint $table) {
      $table->dropColumn('phone_verified_at');
    });
  }
};
