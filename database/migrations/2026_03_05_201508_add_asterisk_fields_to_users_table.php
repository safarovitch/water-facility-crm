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
    Schema::table('users', function (Blueprint $table) {
      $table->string('sip_extension')->nullable()->after('status');
      $table->string('sip_password')->nullable()->after('sip_extension');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('users', function (Blueprint $table) {
      $table->dropColumn(['sip_extension', 'sip_password']);
    });
  }
};
