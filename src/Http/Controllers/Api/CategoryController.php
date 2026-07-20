<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Kurt\Modules\Blog\Http\Requests\StoreCategoryRequest;
use Kurt\Modules\Blog\Http\Requests\UpdateCategoryRequest;
use Kurt\Modules\Blog\Http\Resources\CategoryResource;
use Kurt\Modules\Blog\Models\Category;
use Kurt\Modules\Core\Http\Concerns\HandlesApiQuery;
use Kurt\Modules\Core\Http\Controllers\ApiController;

final class CategoryController extends ApiController
{
    use HandlesApiQuery;

    public function index(Request $request): JsonResponse
    {
        $query = Category::query();

        $query = $this->applyApiFilters($query, $request, ['parent_id' => 'exact', 'name' => 'like']);

        $sort = $request->query('sort');
        $query = $this->applyApiSorts($query, $request, ['position', 'name', 'created_at']);

        if (! is_string($sort) || $sort === '') {
            $query->orderBy('position')->orderBy('id');
        }

        return $this->respondPaginated($this->apiPaginate($query, $request), CategoryResource::class);
    }

    public function show(Category $category): JsonResponse
    {
        $this->authorize('view', $category);

        return $this->respond(CategoryResource::make($category));
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', Category::class);

        $category = Category::create($request->validated());

        return $this->respondCreated(CategoryResource::make($category));
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $this->authorize('update', $category);

        $category->update($request->validated());

        return $this->respond(CategoryResource::make($category));
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->authorize('delete', $category);

        $category->delete();

        return $this->respondNoContent();
    }
}
