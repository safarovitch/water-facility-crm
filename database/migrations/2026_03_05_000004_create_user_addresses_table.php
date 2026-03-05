<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('user_addresses', function (Blueprint $table) {
      $table->id();
      $table->foreignId('user_id')->constrained()->cascadeOnDelete();
      $table->string('label')->default('Main');        // e.g. Home, Office, Warehouse
      $table->string('address_line');                  // street address
      $table->string('city')->nullable();
      $table->string('region')->nullable();
      $table->decimal('lat', 10, 7)->nullable();       // latitude from map pin
      $table->decimal('lng', 10, 7)->nullable();       // longitude from map pin
      $table->boolean('is_default')->default(false);
      $table->timestamps();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('user_addresses');
  }
};
