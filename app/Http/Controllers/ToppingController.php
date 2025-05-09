<?php

namespace App\Http\Controllers;

use App\Models\Topping;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ToppingController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware('auth:sanctum'),
            new Middleware('role:shop_owner', except: ['index', 'show'])
        ];
    }

    /**
     * Display a listing of toppings.
     */
    public function index(Request $request)
    {
        $query = Topping::query();
        
        // Filter by availability
        if ($request->has('available')) {
            $query->where('is_available', $request->boolean('available'));
        }
        
        $toppings = $query->get();
        
        return response()->json(['toppings' => $toppings]);
    }

    /**
     * Store a newly created topping.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'is_available' => 'boolean',
        ]);
        
        $topping = Topping::create($validated);
        
        return response()->json(['topping' => $topping], 201);
    }

    /**
     * Display the specified topping.
     */
    public function show(Topping $topping)
    {
        return response()->json(['topping' => $topping]);
    }

    /**
     * Update the specified topping.
     */
    public function update(Request $request, Topping $topping)
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'is_available' => 'boolean',
        ]);
        
        $topping->update($validated);
        
        return response()->json(['topping' => $topping]);
    }

    /**
     * Remove the specified topping.
     */
    public function destroy(Topping $topping)
    {
        // Check if topping is used in any products
        $usedInProducts = $topping->productToppings()->exists();
        
        if ($usedInProducts) {
            return response()->json([
                'message' => 'This topping cannot be deleted as it is used in products. You can mark it as unavailable instead.'
            ], 422);
        }
        
        $topping->delete();
        
        return response()->json(['message' => 'Topping deleted successfully']);
    }
}