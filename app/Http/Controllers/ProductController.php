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
        $query = Product::with(['category', 'sizes']);
        
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
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'is_available' => 'boolean',
            'image' => 'nullable|image|max:2048', // Max 2MB
            'sizes' => 'required|array|min:1',
            'sizes.*.size' => 'required|in:small,medium,large',
            'sizes.*.price' => 'required|numeric|min:0',
            'toppings' => 'nullable|array',
            'toppings.*.topping_id' => 'required|exists:toppings,id',
            'toppings.*.price' => 'required|numeric|min:0',
        ]);

        // Handle image upload
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
        }

        // Create product
        $product = Product::create([
            'name' => $validated['name'],
            'category_id' => $validated['category_id'],
            'is_available' => $validated['is_available'] ?? true,
            'image_url' => $imagePath ? Storage::url($imagePath) : null,
        ]);

        // Add sizes
        foreach ($validated['sizes'] as $sizeData) {
            $product->sizes()->create([
                'size' => $sizeData['size'],
                'price' => $sizeData['price'],
            ]);
        }

        // Add toppings if provided
        if (isset($validated['toppings'])) {
            foreach ($validated['toppings'] as $toppingData) {
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
        $validated = $request->validate([
            'name' => 'string|max:255',
            'category_id' => 'exists:categories,id',
            'is_available' => 'boolean',
            'image' => 'nullable|image|max:2048',
            'sizes' => 'array',
            'sizes.*.id' => 'nullable|exists:product_sizes,id',
            'sizes.*.size' => 'required|in:small,medium,large',
            'sizes.*.price' => 'required|numeric|min:0',
            'toppings' => 'array',
            'toppings.*.id' => 'nullable|exists:product_toppings,id',
            'toppings.*.topping_id' => 'required|exists:toppings,id',
            'toppings.*.price' => 'required|numeric|min:0',
        ]);

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($product->image_url && Storage::exists('public/' . str_replace('/storage/', '', $product->image_url))) {
                Storage::delete('public/' . str_replace('/storage/', '', $product->image_url));
            }
            
            $imagePath = $request->file('image')->store('products', 'public');
            $validated['image_url'] = Storage::url($imagePath);
        }

        // Update product
        $product->update($validated);

        // Update sizes if provided
        if (isset($validated['sizes'])) {
            $currentSizeIds = [];
            
            foreach ($validated['sizes'] as $sizeData) {
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
        }

        // Update toppings if provided
        if (isset($validated['toppings'])) {
            $currentToppingIds = [];
            
            foreach ($validated['toppings'] as $toppingData) {
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