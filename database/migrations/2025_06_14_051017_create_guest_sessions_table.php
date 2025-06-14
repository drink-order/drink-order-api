<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Parent account
            $table->string('table_number', 10)->index();
            $table->foreignId('token_id')->nullable()->constrained('personal_access_tokens')->onDelete('cascade');
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            
            $table->index(['table_number', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_sessions');
    }
};
