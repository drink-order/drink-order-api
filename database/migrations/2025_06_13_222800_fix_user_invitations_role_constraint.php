<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Drop the existing constraint if it exists
        DB::statement('ALTER TABLE user_invitations DROP CONSTRAINT IF EXISTS user_invitations_role_check');
        
        // Add the new constraint that includes 'guest'
        DB::statement("ALTER TABLE user_invitations ADD CONSTRAINT user_invitations_role_check CHECK (role IN ('admin', 'shop_owner', 'staff', 'user', 'guest'))");
    }

    public function down()
    {
        // Drop the new constraint
        DB::statement('ALTER TABLE user_invitations DROP CONSTRAINT IF EXISTS user_invitations_role_check');
        
        // Re-add the old constraint (without guest)
        DB::statement("ALTER TABLE user_invitations ADD CONSTRAINT user_invitations_role_check CHECK (role IN ('admin', 'shop_owner', 'staff', 'user'))");
    }
};