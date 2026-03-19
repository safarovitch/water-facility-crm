<?php

use App\Enums\OrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For MySQL/PostgreSQL, we need to update the enum column.
        // Since Laravel's $table->enum() doesn't support easy 'change()',
        // we use a raw statement for MySQL if applicable, or just re-define it.
        // Note: DB::statement is more reliable for ENUM updates.
        
        $values = "'" . implode("','", OrderStatus::getValues()) . "'";
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM($values) NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original statuses if needed, but usually we don't want to lose data.
        // For safety in this task, we'll just keep them.
    }
};
