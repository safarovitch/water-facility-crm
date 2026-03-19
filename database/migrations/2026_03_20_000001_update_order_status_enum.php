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
        // For MySQL, we need to update the enum column.
        if (DB::getDriverName() === 'mysql') {
            $values = "'" . implode("','", OrderStatus::getValues()) . "'";
            DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM($values) NOT NULL DEFAULT 'pending'");
        }
        // For SQLite, it's already a string/enum without hard constraints in many cases,
        // but if it has a check constraint, we'd need to recreate the table.
        // Usually Laravel's sqlite enum is just a string.
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
