<?php

use App\Enums\ClientType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('user_profiles', function (Blueprint $table) {
      $table->id();
      $table->foreignId('user_id')->constrained()->cascadeOnDelete()->unique();
      $table->enum('type', ClientType::getValues())->default(ClientType::Individual);
      $table->string('company_name')->nullable();
      $table->string('region')->nullable();
      $table->text('address')->nullable();
      $table->text('notes')->nullable();
      $table->decimal('credit_limit', 12, 2)->default(0);
      $table->timestamps();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('user_profiles');
  }
};
