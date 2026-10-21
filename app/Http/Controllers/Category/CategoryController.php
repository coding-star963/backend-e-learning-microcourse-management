<?php

namespace App\Http\Controllers\Category;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        // Count only the courses this user can see: learners published ones.
        $user = $request->user();
        $query = Category::withCount(['courses' => function ($q) use ($user) {
            if ($user->isStudent()) {
                $q->where('status', Course::STATUS_PUBLISHED);
            }
        }]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) ($request->get('per_page', 10)), 100);
        $categories = $query->orderBy('name')->paginate($perPage > 0 ? $perPage : 10);

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
        // Courses in this category are kept and become uncategorized.
        $category->courses()->update(['category_id' => null]);

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully.',
        ]);
    }
}
