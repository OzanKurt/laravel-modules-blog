<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Policies;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Kurt\Modules\Blog\Models\Tag;

final class TagPolicy
{
    public function view(?Authenticatable $user, Tag $tag): bool
    {
        return true;
    }

    public function create(Authenticatable $user): bool
    {
        return $this->isStaff($user);
    }

    public function update(Authenticatable $user, Tag $tag): bool
    {
        return $this->isStaff($user);
    }

    public function delete(Authenticatable $user, Tag $tag): bool
    {
        return $this->isStaff($user);
    }

    private function isStaff(Authenticatable $user): bool
    {
        return app(Gate::class)->allows('canManageBlog', $user);
    }
}
