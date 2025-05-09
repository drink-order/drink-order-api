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
        // For PostgreSQL, create a new enum type
        DB::statement("CREATE TYPE product_size_enum AS ENUM('none', 'small', 'medium', 'large')");
        
        // Alter the column to use the new type
        // First, create a temporary column to hold existing values
        Schema::table('product_sizes', function (Blueprint $table) {
            $table->string('size_temp')->nullable()->after('size');
        });
        
        // Copy data
        DB::statement("UPDATE product_sizes SET size_temp = size::text");
        
        // Drop the original column
        Schema::table('product_sizes', function (Blueprint $table) {
            $table->dropColumn('size');
        });
        
        // Add the column with the new enum type
        DB::statement("ALTER TABLE product_sizes ADD COLUMN size product_size_enum DEFAULT 'none'");
        
        // Copy back the data with mapping
        DB::statement("
            UPDATE product_sizes 
            SET size = CASE
                WHEN size_temp = 'small' THEN 'small'::product_size_enum
                WHEN size_temp = 'medium' THEN 'medium'::product_size_enum
                WHEN size_temp = 'large' THEN 'large'::product_size_enum
                ELSE 'none'::product_size_enum
            END
        ");
        
        // Drop the temporary column
        Schema::table('product_sizes', function (Blueprint $table) {
            $table->dropColumn('size_temp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // For reverting, create a temporary column
        Schema::table('product_sizes', function (Blueprint $table) {
            $table->string('size_temp')->nullable()->after('size');
        });
        
        // Copy data
        DB::statement("UPDATE product_sizes SET size_temp = size::text");
        
        // Drop the enum column
        Schema::table('product_sizes', function (Blueprint $table) {
            $table->dropColumn('size');
        });
        
        // Add back the original column
        Schema::table('product_sizes', function (Blueprint $table) {
            $table->string('size')->default('small')->after('size_temp');
        });
        
        // Copy back data, excluding 'none'
        DB::statement("
            UPDATE product_sizes 
            SET size = CASE
                WHEN size_temp = 'small' THEN 'small'
                WHEN size_temp = 'medium' THEN 'medium'
                WHEN size_temp = 'large' THEN 'large'
                ELSE 'small'
            END
        ");
        
        // Drop the temporary column
        Schema::table('product_sizes', function (Blueprint $table) {
            $table->dropColumn('size_temp');
        });
        
        // Drop the custom enum type
        DB::statement("DROP TYPE IF EXISTS product_size_enum");
    }
};