<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Kurt\Modules\Blog\Http\Requests\StoreTagRequest;
use Kurt\Modules\Blog\Http\Resources\TagResource;
use Kurt\Modules\Blog\Models\Tag;
use Kurt\Modules\Core\Http\Concerns\HandlesApiQuery;
use Kurt\Modules\Core\Http\Controllers\ApiController;

final class TagController extends ApiController
{
    use HandlesApiQuery;

    public function index(Request $request): JsonResponse
    {
        $query = Tag::query();

        $query = $this->applyApiFilters($query, $request, ['name' => 'like']);

        $sort = $request->query('sort');
        $query = $this->applyApiSorts($query, $request, ['name', 'created_at']);

        if (! is_string($sort) || $sort === '') {
            $query->orderBy('name')->orderBy('id');
        }

        return $this->respondPaginated($this->apiPaginate($query, $request), TagResource::class);
    }

    public function show(Tag $tag): JsonResponse
    {
        $this->authorize('view', $tag);

        return $this->respond(TagResource::make($tag));
    }

    public function store(StoreTagRequest $request): JsonResponse
    {
        $this->authorize('create', Tag::class);

        $tag = Tag::create($request->validated());

        return $this->respondCreated(TagResource::make($tag));
    }

    public function destroy(Tag $tag): JsonResponse
    {
        $this->authorize('delete', $tag);

        $tag->delete();

        return $this->respondNoContent();
    }
}
