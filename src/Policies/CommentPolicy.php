<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Kurt\Modules\Blog\Models\Comment;

final class CommentPolicy
{
    public function view(?Authenticatable $user, Comment $comment): bool
    {
        return true;
    }

    public function create(Authenticatable $user): bool
    {
        return true;
    }

    public function update(Authenticatable $user, Comment $comment): bool
    {
        return $comment->user_id === $user->getAuthIdentifier() || $this->isStaff($user);
    }

    public function delete(Authenticatable $user, Comment $comment): bool
    {
        return $this->update($user, $comment);
    }

    public function approve(Authenticatable $user, Comment $comment): bool
    {
        return $this->isStaff($user);
    }

    public function reject(Authenticatable $user, Comment $comment): bool
    {
        return $this->isStaff($user);
    }

    private function isStaff(Authenticatable $user): bool
    {
        return app('gate')->allows('canManageBlog', $user);
    }
}
