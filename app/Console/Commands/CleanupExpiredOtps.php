<?php

namespace App\Console\Commands;

use App\Models\PhoneOtp;
use Illuminate\Console\Command;

class CleanupExpiredOtps extends Command
{
    protected $signature = 'otp:cleanup';
    protected $description = 'Clean up expired OTPs from the database';

    public function handle()
    {
        $count = PhoneOtp::where('expires_at', '<', now())->delete();
        $this->info("Deleted {$count} expired OTPs.");
        
        return 0;
    }
}