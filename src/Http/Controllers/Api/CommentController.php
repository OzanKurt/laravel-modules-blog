<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Http\Controllers\Api;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Kurt\Modules\Blog\Http\Requests\StoreCommentRequest;
use Kurt\Modules\Blog\Http\Requests\UpdateCommentRequest;
use Kurt\Modules\Blog\Http\Resources\CommentResource;
use Kurt\Modules\Blog\Models\Comment;
use Kurt\Modules\Blog\Models\Post;
use Kurt\Modules\Core\Http\Concerns\HandlesApiQuery;
use Kurt\Modules\Core\Http\Controllers\ApiController;
use Kurt\Modules\Interactions\Comments\Enums\CommentStatus;

final class CommentController extends ApiController
{
    use HandlesApiQuery;

    /**
     * Approved comments for a post. Staff additionally see pending/rejected
     * comments so they can moderate through the API.
     */
    public function index(Request $request, string $post): JsonResponse
    {
        $model = $this->resolvePost($post);

        $this->authorize('view', $model);

        $query = $model->comments()->getQuery();

        $user = $request->user();

        if (! ($user !== null && Gate::allows('canManageBlog', $user))) {
            $query->where('status', CommentStatus::Published->value);
        }

        $query->orderByDesc('id');

        return $this->respondPaginated($this->apiPaginate($query, $request), CommentResource::class);
    }

    public function store(StoreCommentRequest $request, string $post): JsonResponse
    {
        $model = $this->resolvePost($post);

        $this->authorize('create', Comment::class);

        /** @var Authenticatable $user */
        $user = $request->user();

        $comment = Comment::create([
            'post_id' => $model->getKey(),
            'user_id' => $user->getAuthIdentifier(),
            'parent_id' => $request->validated('parent_id'),
            'body' => $request->validated('body'),
        ]);

        return $this->respondCreated(CommentResource::make($comment));
    }

    public function update(UpdateCommentRequest $request, Comment $comment): JsonResponse
    {
        $this->authorize('update', $comment);

        $comment->update(['body' => $request->validated('body')]);

        return $this->respond(CommentResource::make($comment));
    }

    public function destroy(Comment $comment): JsonResponse
    {
        $this->authorize('delete', $comment);

        $comment->delete();

        return $this->respondNoContent();
    }

    private function resolvePost(string $key): Post
    {
        return Post::query()
            ->where(fn (Builder $q) => $q->where('id', $key)->orWhere('slug', $key))
            ->firstOrFail();
    }
}
