<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Kurt\Modules\Blog\Models\Category;

final class CategoryPolicy
{
    public function view(?Authenticatable $user, Category $category): bool
    {
        return true;
    }

    public function create(Authenticatable $user): bool
    {
        return $this->isStaff($user);
    }

    public function update(Authenticatable $user, Category $category): bool
    {
        return $this->isStaff($user);
    }

    public function delete(Authenticatable $user, Category $category): bool
    {
        return $this->isStaff($user);
    }

    private function isStaff(Authenticatable $user): bool
    {
        return app('gate')->allows('canManageBlog', $user);
    }
}
