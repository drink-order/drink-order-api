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
            new Middleware('auth:sanctum', except: ['index', 'show'])
        ];
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Category::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $fields = $request->validate([
            'name' => 'required|max:255'
        ]);

        $category = $request->user()->categories()->create($fields);

        return ['category' => $category];
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category)
    {
        return ['category' => $category];
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category)
    {
        Gate::authorize('modify', $category);
        $fields = $request->validate([
            'name' => 'required|max:255'
        ]);

        $category->update($fields);

        return ['category' => $category];
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        Gate::authorize('modify', $category);
        $category->delete();

        return ['message' => 'The category was deleted'];
    }
}
