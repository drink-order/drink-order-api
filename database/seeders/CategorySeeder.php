<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first();
        
        $categories = [
            'Coffee',
            'Tea',
            'Smoothies',
            'Juices',
            'Soft Drinks',
            'Desserts'
        ];

        foreach ($categories as $categoryName) {
            Category::create([
                'name' => $categoryName,
                'user_id' => $admin->id,
            ]);
        }
    }
}
