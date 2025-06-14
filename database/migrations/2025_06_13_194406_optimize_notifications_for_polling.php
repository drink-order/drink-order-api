
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
    public function up()
    {
        // Add regular indexes first (these can run in transactions)
        Schema::table('notifications', function (Blueprint $table) {
            // Composite index for unread count queries (most important for polling)
            $table->index(['user_id', 'read'], 'idx_notifications_user_read');
            
            // Index for latest notifications with timestamp
            $table->index(['user_id', 'created_at'], 'idx_notifications_user_created');
            
            // Index for duplicate prevention
            $table->index(['user_id', 'title', 'created_at'], 'idx_notifications_duplicate_check');
        });
        
        // PostgreSQL specific optimizations (run outside transaction)
        if (DB::getDriverName() === 'pgsql') {
            // We need to run this outside the transaction, so we'll do it differently
            // Option 1: Create regular index instead of concurrent (for development)
            try {
                DB::statement('CREATE INDEX IF NOT EXISTS idx_notifications_unread_only 
                              ON notifications (user_id, created_at) 
                              WHERE read = false');
                              
                // Update table statistics for better query planning
                DB::statement('ANALYZE notifications');
            } catch (\Exception $e) {
                // If this fails, it's not critical - the regular indexes above will still help
                // Log::warning('Failed to create PostgreSQL partial index: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('idx_notifications_user_read');
            $table->dropIndex('idx_notifications_user_created');
            $table->dropIndex('idx_notifications_duplicate_check');
        });
        
        if (DB::getDriverName() === 'pgsql') {
            try {
                DB::statement('DROP INDEX IF EXISTS idx_notifications_unread_only');
            } catch (\Exception $e) {
                // \Log::warning('Failed to drop PostgreSQL partial index: ' . $e->getMessage());
            }
        }
    }
};