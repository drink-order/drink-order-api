<?php

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
        // For PostgreSQL, we need to create a new type
        DB::statement("CREATE TYPE order_status_enum AS ENUM('preparing', 'ready_for_pickup', 'completed')");
        
        // Then alter the column to use the new type
        // First remove the constraint if it exists
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('order_status');
        });
        
        // Add the column with the new enum type
        DB::statement("ALTER TABLE orders ADD COLUMN order_status order_status_enum DEFAULT 'preparing'");
        
        // Update existing records if needed
        DB::statement("UPDATE orders SET order_status = 'preparing' WHERE order_status IS NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // For reverting, we create a temporary column, copy data, then replace
        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_status_temp')->default('pending')->after('order_status');
        });
        
        // Copy data
        DB::statement("UPDATE orders SET order_status_temp = order_status::text");
        
        // Drop the enum column
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('order_status');
        });
        
        // Rename the temp column
        Schema::table('orders', function (Blueprint $table) {
            $table->renameColumn('order_status_temp', 'order_status');
        });
        
        // Drop the custom enum type
        DB::statement("DROP TYPE IF EXISTS order_status_enum");
    }
};