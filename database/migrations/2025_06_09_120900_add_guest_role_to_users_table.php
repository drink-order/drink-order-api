<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // First, let's check the current constraint name
        $constraintName = DB::select("
            SELECT conname 
            FROM pg_constraint 
            WHERE conrelid = 'users'::regclass 
            AND conname LIKE '%role%'
        ");

        if (!empty($constraintName)) {
            $constraintName = $constraintName[0]->conname;
            
            // Drop the existing constraint
            DB::statement("ALTER TABLE users DROP CONSTRAINT {$constraintName}");
        }

        // Add new constraint with 'guest' included
        DB::statement("
            ALTER TABLE users 
            ADD CONSTRAINT users_role_check 
            CHECK (role IN ('admin', 'shop_owner', 'staff', 'user', 'guest'))
        ");
    }

    public function down(): void
    {
        // Drop the constraint with guest
        DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check");
        
        // Add back the original constraint without guest
        DB::statement("
            ALTER TABLE users 
            ADD CONSTRAINT users_role_check 
            CHECK (role IN ('admin', 'shop_owner', 'staff', 'user'))
        ");
    }
};