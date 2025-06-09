<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Category;
use App\Models\Topping;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::all();
        $toppings = Topping::all();
        
        $products = [
            // Coffee products
            [
                'name' => 'Espresso',
                'category' => 'Coffee',
                'sizes' => [
                    ['size' => 'small', 'price' => 2.50],
                    ['size' => 'medium', 'price' => 3.00],
                    ['size' => 'large', 'price' => 3.50]
                ],
                'toppings' => ['Extra Shot', 'Whipped Cream', 'Vanilla Syrup']
            ],
            [
                'name' => 'Americano',
                'category' => 'Coffee',
                'sizes' => [
                    ['size' => 'small', 'price' => 3.00],
                    ['size' => 'medium', 'price' => 3.50],
                    ['size' => 'large', 'price' => 4.00]
                ],
                'toppings' => ['Extra Shot', 'Vanilla Syrup', 'Caramel Syrup']
            ],
            [
                'name' => 'Cappuccino',
                'category' => 'Coffee',
                'sizes' => [
                    ['size' => 'small', 'price' => 4.00],
                    ['size' => 'medium', 'price' => 4.50],
                    ['size' => 'large', 'price' => 5.00]
                ],
                'toppings' => ['Extra Shot', 'Whipped Cream', 'Cinnamon', 'Chocolate Syrup']
            ],
            [
                'name' => 'Latte',
                'category' => 'Coffee',
                'sizes' => [
                    ['size' => 'small', 'price' => 4.50],
                    ['size' => 'medium', 'price' => 5.00],
                    ['size' => 'large', 'price' => 5.50]
                ],
                'toppings' => ['Extra Shot', 'Vanilla Syrup', 'Caramel Syrup', 'Coconut Milk', 'Soy Milk']
            ],
            
            // Tea products
            [
                'name' => 'Green Tea',
                'category' => 'Tea',
                'sizes' => [
                    ['size' => 'small', 'price' => 2.00],
                    ['size' => 'medium', 'price' => 2.50],
                    ['size' => 'large', 'price' => 3.00]
                ],
                'toppings' => ['Honey', 'Vanilla Syrup']
            ],
            [
                'name' => 'Black Tea',
                'category' => 'Tea',
                'sizes' => [
                    ['size' => 'small', 'price' => 2.00],
                    ['size' => 'medium', 'price' => 2.50],
                    ['size' => 'large', 'price' => 3.00]
                ],
                'toppings' => ['Honey', 'Vanilla Syrup']
            ],
            [
                'name' => 'Chai Latte',
                'category' => 'Tea',
                'sizes' => [
                    ['size' => 'small', 'price' => 3.50],
                    ['size' => 'medium', 'price' => 4.00],
                    ['size' => 'large', 'price' => 4.50]
                ],
                'toppings' => ['Whipped Cream', 'Cinnamon', 'Nutmeg', 'Coconut Milk']
            ],
            
            // Smoothies
            [
                'name' => 'Berry Smoothie',
                'category' => 'Smoothies',
                'sizes' => [
                    ['size' => 'small', 'price' => 5.00],
                    ['size' => 'medium', 'price' => 6.00],
                    ['size' => 'large', 'price' => 7.00]
                ],
                'toppings' => ['Whipped Cream', 'Honey']
            ],
            [
                'name' => 'Mango Smoothie',
                'category' => 'Smoothies',
                'sizes' => [
                    ['size' => 'small', 'price' => 5.50],
                    ['size' => 'medium', 'price' => 6.50],
                    ['size' => 'large', 'price' => 7.50]
                ],
                'toppings' => ['Whipped Cream', 'Coconut Milk']
            ],
            
            // Juices
            [
                'name' => 'Orange Juice',
                'category' => 'Juices',
                'sizes' => [
                    ['size' => 'small', 'price' => 3.00],
                    ['size' => 'medium', 'price' => 4.00],
                    ['size' => 'large', 'price' => 5.00]
                ],
                'toppings' => []
            ],
            [
                'name' => 'Apple Juice',
                'category' => 'Juices',
                'sizes' => [
                    ['size' => 'small', 'price' => 3.00],
                    ['size' => 'medium', 'price' => 4.00],
                    ['size' => 'large', 'price' => 5.00]
                ],
                'toppings' => []
            ],
            
            // Soft Drinks
            [
                'name' => 'Coca Cola',
                'category' => 'Soft Drinks',
                'price' => 2.50, // Single price product
                'toppings' => []
            ],
            [
                'name' => 'Sprite',
                'category' => 'Soft Drinks',
                'price' => 2.50,
                'toppings' => []
            ],
            
            // Desserts
            [
                'name' => 'Chocolate Cake',
                'category' => 'Desserts',
                'price' => 4.50,
                'toppings' => ['Whipped Cream', 'Chocolate Syrup']
            ],
            [
                'name' => 'Cheesecake',
                'category' => 'Desserts',
                'price' => 5.00,
                'toppings' => ['Whipped Cream', 'Caramel Syrup']
            ]
        ];

        foreach ($products as $productData) {
            $category = $categories->where('name', $productData['category'])->first();
            
            if (!$category) continue;

            // Create product
            $product = Product::create([
                'name' => $productData['name'],
                'category_id' => $category->id,
                'is_available' => true,
                'image_url' => null, // No images for seeded data
            ]);

            // Add sizes or single price
            if (isset($productData['sizes'])) {
                foreach ($productData['sizes'] as $sizeData) {
                    $product->sizes()->create([
                        'size' => $sizeData['size'],
                        'price' => $sizeData['price'],
                    ]);
                }
            } else {
                // Single price product (no sizes)
                $product->sizes()->create([
                    'size' => 'none',
                    'price' => $productData['price'],
                ]);
            }

            // Add toppings
            if (!empty($productData['toppings'])) {
                foreach ($productData['toppings'] as $toppingName) {
                    $topping = $toppings->where('name', $toppingName)->first();
                    if ($topping) {
                        $product->toppings()->create([
                            'topping_id' => $topping->id,
                            'price' => rand(50, 150) / 100, // Random price between $0.50 and $1.50
                        ]);
                    }
                }
            }
        }
    }
}