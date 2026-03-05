<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('orders', function (Blueprint $table) {
      $table->id();
      $table->string('order_number')->unique();
      $table->foreignId('user_id')->constrained()->restrictOnDelete();
      $table->enum('status', OrderStatus::getValues())->default(OrderStatus::Pending);
      $table->date('delivery_date')->nullable();
      $table->text('delivery_address')->nullable();
      $table->decimal('total_amount', 12, 2)->default(0);
      $table->decimal('paid_amount', 12, 2)->default(0);
      $table->enum('payment_status', PaymentStatus::getValues())->default(PaymentStatus::Unpaid);
      $table->text('notes')->nullable();
      $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
      $table->timestamps();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('orders');
  }
};
