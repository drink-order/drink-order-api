<?php

namespace Database\Seeders;

use App\Models\Topping;
use Illuminate\Database\Seeder;

class ToppingSeeder extends Seeder
{
    public function run(): void
    {
        $toppings = [
            'Extra Shot',
            'Whipped Cream',
            'Vanilla Syrup',
            'Caramel Syrup',
            'Chocolate Syrup',
            'Coconut Milk',
            'Soy Milk',
            'Almond Milk',
            'Extra Foam',
            'Cinnamon',
            'Nutmeg',
            'Honey'
        ];

        foreach ($toppings as $toppingName) {
            Topping::create([
                'name' => $toppingName,
                'is_available' => true,
            ]);
        }
    }
}