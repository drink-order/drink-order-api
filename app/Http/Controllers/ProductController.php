<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

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
        // Handle boolean conversion for is_available
        if ($request->has('is_available')) {
            $request->merge(['is_available' => $request->input('is_available') === '1']);
        }

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

        Log::info('Store request data:', $request->all());
        Log::info('Store validated data:', $validatedData);

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

        Log::info('Created product:', $product->toArray());

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
        // Handle boolean conversion for is_available
        if ($request->has('is_available')) {
            $request->merge(['is_available' => $request->input('is_available') === '1']);
        }

        $validatedData = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'category_id' => 'sometimes|required|exists:categories,id',
            'is_available' => 'sometimes|boolean',
            'image' => 'nullable|image|max:2048',
            'sizes' => 'nullable|array',
            'sizes.*.id' => 'nullable|integer|exists:product_sizes,id',
            'sizes.*.size' => 'required_with:sizes|in:none,small,medium,large',
            'sizes.*.price' => 'required_with:sizes|numeric|min:0',
            'toppings' => 'nullable|array',
            'toppings.*.id' => 'nullable|integer|exists:product_toppings,id',
            'toppings.*.topping_id' => 'required_with:toppings|exists:toppings,id',
            'toppings.*.price' => 'required_with:toppings|numeric|min:0',
            'price' => 'nullable|numeric|min:0',
        ]);

        Log::info('Update request data:', $request->all());
        Log::info('Update validated data:', $validatedData);

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($product->image_url) {
                $oldImagePath = str_replace('/storage/', '', $product->image_url);
                if (Storage::disk('public')->exists($oldImagePath)) {
                    Storage::disk('public')->delete($oldImagePath);
                    Log::info('Deleted old image:', ['path' => $oldImagePath]);
                }
            }
            
            $imagePath = $request->file('image')->store('products', 'public');
            $validatedData['image_url'] = Storage::url($imagePath);
            Log::info('Uploaded new image:', ['path' => $imagePath]);
        }

        // Update basic product information
        $updateData = array_filter([
            'name' => $validatedData['name'] ?? null,
            'category_id' => $validatedData['category_id'] ?? null,
            'is_available' => array_key_exists('is_available', $validatedData) ? $validatedData['is_available'] : null,
            'image_url' => $validatedData['image_url'] ?? null,
        ], function($value) {
            return $value !== null;
        });

        if (!empty($updateData)) {
            $product->update($updateData);
            Log::info('Updated product basic info:', $updateData);
        }

        // Handle sizes update
        if (array_key_exists('sizes', $validatedData)) {
            if (empty($validatedData['sizes'])) {
                // If sizes array is empty, delete all sizes and create a single "none" size with price
                $product->sizes()->delete();
                Log::info('Deleted all sizes for product');
                
                if (isset($validatedData['price'])) {
                    $product->sizes()->create([
                        'size' => 'none',
                        'price' => $validatedData['price'],
                    ]);
                    Log::info('Created none size with price:', ['price' => $validatedData['price']]);
                }
            } else {
                // Update/create sizes
                $currentSizeIds = [];
                
                foreach ($validatedData['sizes'] as $sizeData) {
                    if (isset($sizeData['id']) && $sizeData['id']) {
                        // Update existing size
                        $size = $product->sizes()->find($sizeData['id']);
                        if ($size) {
                            $size->update([
                                'size' => $sizeData['size'],
                                'price' => $sizeData['price'],
                            ]);
                            $currentSizeIds[] = $size->id;
                            Log::info('Updated existing size:', ['id' => $size->id, 'size' => $sizeData['size'], 'price' => $sizeData['price']]);
                        }
                    } else {
                        // Create new size
                        $size = $product->sizes()->create([
                            'size' => $sizeData['size'],
                            'price' => $sizeData['price'],
                        ]);
                        $currentSizeIds[] = $size->id;
                        Log::info('Created new size:', ['id' => $size->id, 'size' => $sizeData['size'], 'price' => $sizeData['price']]);
                    }
                }
                
                // Delete sizes not in the update
                $deletedSizes = $product->sizes()->whereNotIn('id', $currentSizeIds)->get();
                if ($deletedSizes->count() > 0) {
                    Log::info('Deleting sizes not in update:', ['ids' => $deletedSizes->pluck('id')->toArray()]);
                    $product->sizes()->whereNotIn('id', $currentSizeIds)->delete();
                }
            }
        } elseif (isset($validatedData['price'])) {
            // Update price for single size product
            $singleSize = $product->sizes()->where('size', 'none')->first();
            if ($singleSize) {
                $singleSize->update(['price' => $validatedData['price']]);
                Log::info('Updated single size price:', ['price' => $validatedData['price']]);
            } else {
                // Create new "none" size if it doesn't exist
                $product->sizes()->create([
                    'size' => 'none',
                    'price' => $validatedData['price'],
                ]);
                Log::info('Created new none size with price:', ['price' => $validatedData['price']]);
            }
        }

        // Handle toppings update
        if (array_key_exists('toppings', $validatedData)) {
            if (empty($validatedData['toppings'])) {
                // If toppings array is empty, delete all toppings
                $deletedToppings = $product->toppings()->get();
                if ($deletedToppings->count() > 0) {
                    Log::info('Deleting all toppings for product:', ['ids' => $deletedToppings->pluck('id')->toArray()]);
                    $product->toppings()->delete();
                }
            } else {
                $currentToppingIds = [];
                
                foreach ($validatedData['toppings'] as $toppingData) {
                    if (isset($toppingData['id']) && $toppingData['id']) {
                        // Update existing topping
                        $topping = $product->toppings()->find($toppingData['id']);
                        if ($topping) {
                            $topping->update([
                                'topping_id' => $toppingData['topping_id'],
                                'price' => $toppingData['price'],
                            ]);
                            $currentToppingIds[] = $topping->id;
                            Log::info('Updated existing topping:', ['id' => $topping->id, 'topping_id' => $toppingData['topping_id'], 'price' => $toppingData['price']]);
                        }
                    } else {
                        // Create new topping
                        $topping = $product->toppings()->create([
                            'topping_id' => $toppingData['topping_id'],
                            'price' => $toppingData['price'],
                        ]);
                        $currentToppingIds[] = $topping->id;
                        Log::info('Created new topping:', ['id' => $topping->id, 'topping_id' => $toppingData['topping_id'], 'price' => $toppingData['price']]);
                    }
                }
                
                // Delete toppings not in the update
                $deletedToppings = $product->toppings()->whereNotIn('id', $currentToppingIds)->get();
                if ($deletedToppings->count() > 0) {
                    Log::info('Deleting toppings not in update:', ['ids' => $deletedToppings->pluck('id')->toArray()]);
                    $product->toppings()->whereNotIn('id', $currentToppingIds)->delete();
                }
            }
        }

        // Load relationships and return
        $product->load(['category', 'sizes', 'toppings.topping']);

        Log::info('Updated product final result:', $product->toArray());

        return response()->json(['product' => $product]);
    }

    /**
     * Remove the specified product.
     */
    public function destroy(Product $product)
    {
        Log::info('Deleting product:', ['id' => $product->id, 'name' => $product->name]);

        // Delete associated image if exists
        if ($product->image_url) {
            $imagePath = str_replace('/storage/', '', $product->image_url);
            if (Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->delete($imagePath);
                Log::info('Deleted product image:', ['path' => $imagePath]);
            }
        }
        
        $product->delete();
        
        Log::info('Product deleted successfully:', ['id' => $product->id]);
        
        return response()->json(['message' => 'Product deleted successfully']);
    }
}