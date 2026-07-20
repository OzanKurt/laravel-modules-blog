<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Policies;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Kurt\Modules\Blog\Enums\PostStatus;
use Kurt\Modules\Blog\Models\Post;

final class PostPolicy
{
    public function view(?Authenticatable $user, Post $post): bool
    {
        if ($post->status === PostStatus::Published && $post->published_at?->isPast()) {
            return true;
        }

        return $user !== null && ($post->user_id === $user->getAuthIdentifier() || $this->isStaff($user));
    }

    public function create(Authenticatable $user): bool
    {
        return true;
    }

    public function update(Authenticatable $user, Post $post): bool
    {
        return $post->user_id === $user->getAuthIdentifier() || $this->isStaff($user);
    }

    public function delete(Authenticatable $user, Post $post): bool
    {
        return $this->update($user, $post);
    }

    private function isStaff(Authenticatable $user): bool
    {
        return app(Gate::class)->allows('canManageBlog', $user);
    }
}
