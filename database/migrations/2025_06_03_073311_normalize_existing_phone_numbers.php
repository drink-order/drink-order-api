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
        // Update phone numbers that start with '0' but don't already have '+855'
        DB::statement("
            UPDATE users 
            SET phone = CONCAT('+855', SUBSTRING(phone, 2)) 
            WHERE phone LIKE '0%' 
            AND phone NOT LIKE '+855%'
            AND phone IS NOT NULL
        ");

        // Also update phone_otps table if it exists
        if (Schema::hasTable('phone_otps')) {
            DB::statement("
                UPDATE phone_otps 
                SET phone = CONCAT('+855', SUBSTRING(phone, 2)) 
                WHERE phone LIKE '0%' 
                AND phone NOT LIKE '+855%'
                AND phone IS NOT NULL
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert phone numbers back to local format (remove +855 and add 0)
        DB::statement("
            UPDATE users 
            SET phone = CONCAT('0', SUBSTRING(phone, 5)) 
            WHERE phone LIKE '+855%'
            AND phone IS NOT NULL
        ");

        // Also revert phone_otps table if it exists
        if (Schema::hasTable('phone_otps')) {
            DB::statement("
                UPDATE phone_otps 
                SET phone = CONCAT('0', SUBSTRING(phone, 5)) 
                WHERE phone LIKE '+855%'
                AND phone IS NOT NULL
            ");
        }
    }
};