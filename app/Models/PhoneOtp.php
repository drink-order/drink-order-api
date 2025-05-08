<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhoneOtp extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'phone',
        'otp',
        'expires_at'
    ];
    
    protected $casts = [
        'expires_at' => 'datetime',
    ];
    
    /**
     * Check if the OTP is still valid
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return now()->lt($this->expires_at);
    }
    
    /**
     * Check if the OTP is expired
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return !$this->isValid();
    }
}