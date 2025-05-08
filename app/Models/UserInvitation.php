<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'token',
        'role',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    /**
     * Check if the invitation is still valid
     */
    public function isValid(): bool
    {
        return $this->used_at === null && 
               (is_null($this->expires_at) || now()->lt($this->expires_at));
    }

    /**
     * Generate a unique invitation token
     */
    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32)); // 64 characters long
    }

    /**
     * Get the user associated with this invitation
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}