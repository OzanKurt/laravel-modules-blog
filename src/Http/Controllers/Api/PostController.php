<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Http\Controllers\Api;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Kurt\Modules\Blog\Enums\PostStatus;
use Kurt\Modules\Blog\Http\Requests\StorePostRequest;
use Kurt\Modules\Blog\Http\Requests\UpdatePostRequest;
use Kurt\Modules\Blog\Http\Resources\PostResource;
use Kurt\Modules\Blog\Models\Post;
use Kurt\Modules\Core\Http\Concerns\HandlesApiQuery;
use Kurt\Modules\Core\Http\Controllers\ApiController;

final class PostController extends ApiController
{
    use HandlesApiQuery;

    /**
     * Paginated list of posts. Guests and non-staff readers only see published
     * posts (authenticated readers additionally see their own drafts; staff see
     * everything). Supports `?sort=`, `?filter[...]=` and `?per_page=`.
     */
    public function index(Request $request): JsonResponse
    {
        $query = $this->applyVisibility(
            Post::query()->with(['category', 'tags']),
            $request->user(),
        );

        $this->normaliseFilters($request);

        $query = $this->applyApiFilters($query, $request, [
            'category_id' => 'exact',
            'status' => 'exact',
            'user_id' => 'exact',
        ]);

        $sort = $request->query('sort');
        $query = $this->applyApiSorts($query, $request, ['created_at', 'published_at', 'title']);

        if (! is_string($sort) || $sort === '') {
            $query->orderByDesc('published_at')->orderByDesc('id');
        }

        return $this->respondPaginated($this->apiPaginate($query, $request), PostResource::class);
    }

    /**
     * Show a single post resolved by id or slug.
     */
    public function show(Request $request, string $post): JsonResponse
    {
        $model = $this->resolvePost($post);

        $this->authorize('view', $model);

        return $this->respond(PostResource::make($model->load(['category', 'tags'])));
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        $this->authorize('create', Post::class);

        $data = $request->validated();
        $tags = $data['tags'] ?? null;
        unset($data['tags']);

        /** @var Authenticatable $user */
        $user = $request->user();
        $data['user_id'] = $user->getAuthIdentifier();

        $post = Post::create($data);

        if (is_array($tags)) {
            $post->tags()->sync($tags);
        }

        // Reload so DB-side defaults (status, type, view_count) are hydrated on
        // the instance before it is serialised.
        $post->refresh();

        return $this->respondCreated(PostResource::make($post->load(['category', 'tags'])));
    }

    public function update(UpdatePostRequest $request, string $post): JsonResponse
    {
        $model = $this->resolvePost($post);

        $this->authorize('update', $model);

        $data = $request->validated();
        $hasTags = array_key_exists('tags', $data);
        $tags = $data['tags'] ?? null;
        unset($data['tags']);

        $model->update($data);

        if ($hasTags) {
            $model->tags()->sync(is_array($tags) ? $tags : []);
        }

        return $this->respond(PostResource::make($model->load(['category', 'tags'])));
    }

    public function destroy(string $post): JsonResponse
    {
        $model = $this->resolvePost($post);

        $this->authorize('delete', $model);

        $model->delete();

        return $this->respondNoContent();
    }

    /**
     * Publish a post now. Backfills `published_at` when unset so the post is
     * immediately live. The PostObserver fires PostPublished on the transition.
     */
    public function publish(string $post): JsonResponse
    {
        $model = $this->resolvePost($post);

        $this->authorize('update', $model);

        $model->status = PostStatus::Published;

        if ($model->published_at === null) {
            $model->published_at = now();
        }

        $model->save();

        return $this->respond(PostResource::make($model->load(['category', 'tags'])));
    }

    /**
     * Revert a post to draft so it no longer appears in published listings.
     */
    public function unpublish(string $post): JsonResponse
    {
        $model = $this->resolvePost($post);

        $this->authorize('update', $model);

        $model->status = PostStatus::Draft;
        $model->save();

        return $this->respond(PostResource::make($model->load(['category', 'tags'])));
    }

    /**
     * Posts most related to the given one (shared tags, then shared category).
     */
    public function related(Request $request, string $post): JsonResponse
    {
        $model = $this->resolvePost($post);

        $this->authorize('view', $model);

        $limit = min(50, max(1, $request->integer('limit', 5)));

        $related = $model->related($limit)->load(['category', 'tags']);

        return $this->respond(PostResource::collection($related));
    }

    private function resolvePost(string $key): Post
    {
        return Post::query()
            ->where(fn (Builder $q) => $q->where('id', $key)->orWhere('slug', $key))
            ->firstOrFail();
    }

    /**
     * Constrain a post query to what the given user may see.
     *
     * @param  Builder<Post>  $query
     * @return Builder<Post>
     */
    private function applyVisibility(Builder $query, ?Authenticatable $user): Builder
    {
        if ($user !== null && Gate::allows('canManageBlog', $user)) {
            return $query;
        }

        if ($user === null) {
            return $query->published();
        }

        return $query->where(function (Builder $inner) use ($user): void {
            $inner->where('status', PostStatus::Published->value)
                ->where('published_at', '<=', now())
                ->orWhere('user_id', $user->getAuthIdentifier());
        });
    }

    /**
     * Rewrite the friendly `filter[category]` / `filter[author]` params onto the
     * underlying `category_id` / `user_id` columns the allow-list expects.
     */
    private function normaliseFilters(Request $request): void
    {
        $filters = $request->query('filter');

        if (! is_array($filters)) {
            return;
        }

        $map = ['category' => 'category_id', 'author' => 'user_id'];
        $normalised = [];

        foreach ($filters as $key => $value) {
            $column = is_string($key) && array_key_exists($key, $map) ? $map[$key] : $key;
            $normalised[$column] = $value;
        }

        $request->query->set('filter', $normalised);
    }
}
