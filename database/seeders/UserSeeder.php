<?php
// database/seeders/UserSeeder.php
namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        // Create shop owner
        User::create([
            'name' => 'Shop Owner',
            'email' => 'owner@example.com',
            'password' => Hash::make('password'),
            'role' => 'shop_owner',
            'email_verified_at' => now(),
        ]);

        // Create staff members
        User::create([
            'name' => 'Staff Member 1',
            'email' => 'staff1@example.com',
            'password' => Hash::make('password'),
            'role' => 'staff',
            'email_verified_at' => now(),
        ]);

        User::create([
            'name' => 'Staff Member 2',
            'email' => 'staff2@example.com',
            'password' => Hash::make('password'),
            'role' => 'staff',
            'email_verified_at' => now(),
        ]);

        // Create regular users
        for ($i = 1; $i <= 5; $i++) {
            User::create([
                'name' => "Customer $i",
                'email' => "customer$i@example.com",
                'password' => Hash::make('password'),
                'role' => 'user',
                'phone' => '+855' . rand(10000000, 99999999),
                'email_verified_at' => now(),
            ]);
        }
    }
}
