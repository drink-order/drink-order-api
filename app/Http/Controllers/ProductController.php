<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware('auth:sanctum'),
            new Middleware('role:shop_owner', except: ['index', 'show'])
        ];
    }

    /**
     * Display a listing of the products.
     */
    public function index(Request $request)
    {
        $query = Product::with(['category', 'sizes', 'toppings.topping']);
        
        // Filter by category if provided
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        
        // Filter by availability
        if ($request->has('available')) {
            $query->where('is_available', $request->boolean('available'));
        }
        
        $products = $query->get();
        
        return response()->json(['products' => $products]);
    }

    /**
     * Store a newly created product.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'is_available' => 'boolean',
            'image' => 'nullable|image|max:2048', // Max 2MB
            'sizes' => 'nullable|array',
            'sizes.*.size' => 'required_with:sizes|in:none,small,medium,large',
            'sizes.*.price' => 'required_with:sizes|numeric|min:0',
            'toppings' => 'nullable|array',
            'toppings.*.topping_id' => 'required|exists:toppings,id',
            'toppings.*.price' => 'required|numeric|min:0',
            'price' => 'required_without:sizes|nullable|numeric|min:0', // Price for products without sizes
        ]);

        // Handle image upload
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
        }

        // Create product
        $product = Product::create([
            'name' => $validatedData['name'],
            'category_id' => $validatedData['category_id'],
            'is_available' => $validatedData['is_available'] ?? true,
            'image_url' => $imagePath ? Storage::url($imagePath) : null,
        ]);

        // Add sizes or default size
        if (isset($validatedData['sizes']) && !empty($validatedData['sizes'])) {
            foreach ($validatedData['sizes'] as $sizeData) {
                $product->sizes()->create([
                    'size' => $sizeData['size'],
                    'price' => $sizeData['price'],
                ]);
            }
        } else if (isset($validatedData['price'])) {
            // Create a default "none" size if no sizes are provided
            $product->sizes()->create([
                'size' => 'none',
                'price' => $validatedData['price'],
            ]);
        }

        // Add toppings if provided
        if (isset($validatedData['toppings'])) {
            foreach ($validatedData['toppings'] as $toppingData) {
                $product->toppings()->create([
                    'topping_id' => $toppingData['topping_id'],
                    'price' => $toppingData['price'],
                ]);
            }
        }

        // Load relationships
        $product->load(['category', 'sizes', 'toppings.topping']);

        return response()->json(['product' => $product], 201);
    }

    /**
     * Display the specified product.
     */
    public function show(Product $product)
    {
        $product->load(['category', 'sizes', 'toppings.topping']);
        return response()->json(['product' => $product]);
    }

    /**
     * Update the specified product.
     */
    public function update(Request $request, Product $product)
    {
        $validatedData = $request->validate([
            'name' => 'string|max:255',
            'category_id' => 'exists:categories,id',
            'is_available' => 'boolean',
            'image' => 'nullable|image|max:2048',
            'sizes' => 'nullable|array',
            'sizes.*.id' => 'nullable|exists:product_sizes,id',
            'sizes.*.size' => 'required_with:sizes|in:none,small,medium,large',
            'sizes.*.price' => 'required_with:sizes|numeric|min:0',
            'toppings' => 'nullable|array',
            'toppings.*.id' => 'nullable|exists:product_toppings,id',
            'toppings.*.topping_id' => 'required|exists:toppings,id',
            'toppings.*.price' => 'required|numeric|min:0',
            'price' => 'nullable|numeric|min:0', // For updating a single price
        ]);

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($product->image_url && Storage::exists('public/' . str_replace('/storage/', '', $product->image_url))) {
                Storage::delete('public/' . str_replace('/storage/', '', $product->image_url));
            }
            
            $imagePath = $request->file('image')->store('products', 'public');
            $validatedData['image_url'] = Storage::url($imagePath);
        }

        // Update product
        $product->update(array_filter([
            'name' => $validatedData['name'] ?? null,
            'category_id' => $validatedData['category_id'] ?? null,
            'is_available' => $validatedData['is_available'] ?? null,
            'image_url' => $validatedData['image_url'] ?? null,
        ]));

        // Update sizes if provided
        if (isset($validatedData['sizes'])) {
            $currentSizeIds = [];
            
            foreach ($validatedData['sizes'] as $sizeData) {
                if (isset($sizeData['id'])) {
                    // Update existing size
                    $size = $product->sizes()->find($sizeData['id']);
                    if ($size) {
                        $size->update([
                            'size' => $sizeData['size'],
                            'price' => $sizeData['price'],
                        ]);
                        $currentSizeIds[] = $size->id;
                    }
                } else {
                    // Create new size
                    $size = $product->sizes()->create([
                        'size' => $sizeData['size'],
                        'price' => $sizeData['price'],
                    ]);
                    $currentSizeIds[] = $size->id;
                }
            }
            
            // Delete sizes not in the update
            $product->sizes()->whereNotIn('id', $currentSizeIds)->delete();
        } elseif (isset($validatedData['price']) && $product->sizes()->count() === 1) {
            // Update price for products with a single "none" size
            $singleSize = $product->sizes()->first();
            if ($singleSize && $singleSize->size === 'none') {
                $singleSize->update(['price' => $validatedData['price']]);
            }
        }

        // Update toppings if provided
        if (isset($validatedData['toppings'])) {
            $currentToppingIds = [];
            
            foreach ($validatedData['toppings'] as $toppingData) {
                if (isset($toppingData['id'])) {
                    // Update existing topping
                    $topping = $product->toppings()->find($toppingData['id']);
                    if ($topping) {
                        $topping->update([
                            'topping_id' => $toppingData['topping_id'],
                            'price' => $toppingData['price'],
                        ]);
                        $currentToppingIds[] = $topping->id;
                    }
                } else {
                    // Create new topping
                    $topping = $product->toppings()->create([
                        'topping_id' => $toppingData['topping_id'],
                        'price' => $toppingData['price'],
                    ]);
                    $currentToppingIds[] = $topping->id;
                }
            }
            
            // Delete toppings not in the update
            $product->toppings()->whereNotIn('id', $currentToppingIds)->delete();
        }

        // Load relationships
        $product->load(['category', 'sizes', 'toppings.topping']);

        return response()->json(['product' => $product]);
    }

    /**
     * Remove the specified product.
     */
    public function destroy(Product $product)
    {
        // Delete associated image if exists
        if ($product->image_url && Storage::exists('public/' . str_replace('/storage/', '', $product->image_url))) {
            Storage::delete('public/' . str_replace('/storage/', '', $product->image_url));
        }
        
        $product->delete();
        
        return response()->json(['message' => 'Product deleted successfully']);
    }
}