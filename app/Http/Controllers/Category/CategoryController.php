<?php

namespace App\Http\Controllers\Category;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $categories = Category::withCount('courses')
            ->orderBy('name')
            ->paginate($request->get('per_page', 15));

        return CategoryResource::collection($categories);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'description' => 'nullable|string|max:1000',
        ]);

        $category = Category::create($validated);

        return response()->json([
            'category' => new CategoryResource($category),
            'message' => 'Category created successfully.',
        ], 201);
    }

    public function show(Category $category): CategoryResource
    {
        $category->loadCount('courses');

        return new CategoryResource($category);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:categories,name,' . $category->id,
            'description' => 'nullable|string|max:1000',
        ]);

        $category->update($validated);

        return response()->json([
            'category' => new CategoryResource($category->fresh()),
            'message' => 'Category updated successfully.',
        ]);
    }

    public function destroy(Category $category): JsonResponse
    {
        if ($category->courses()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete category with associated courses.',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully.',
        ]);
    }
}
