<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;

class CategoryController extends Controller implements HasMiddleware
{

    public static function middleware()
    {
        return [
            new Middleware('auth:sanctum'),
            new Middleware('role:admin', except: ['index', 'show'])
        ];
    }
    /**
     * Display a listing of categories.
     */
    public function index()
    {
        return response()->json(['categories' => Category::all()]);
    }

    /**
     * Store a newly created category.
     */
    public function store(Request $request)
    {
        $fields = $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $category = Category::create([
            'name' => $fields['name'],
            'user_id' => $request->user()->id
        ]);

        return response()->json(['category' => $category], 201);
    }

    /**
     * Display the specified category.
     */
    public function show(Category $category)
    {
        return response()->json(['category' => $category]);
    }

    /**
     * Update the specified category.
     */
    public function update(Request $request, Category $category)
    {
        $fields = $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $category->update($fields);

        return response()->json(['category' => $category]);
    }

    /**
     * Remove the specified category.
     */
    public function destroy(Category $category)
    {
        // Check if category has any products
        $hasProducts = $category->products()->exists();
        
        if ($hasProducts) {
            return response()->json([
                'message' => 'This category cannot be deleted as it contains products.'
            ], 422);
        }
        
        $category->delete();

        return response()->json(['message' => 'Category deleted successfully']);
    }
}
