<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Observers;

use Kurt\Modules\Blog\Events\CategoryCreated;
use Kurt\Modules\Blog\Events\CategoryDeleted;
use Kurt\Modules\Blog\Events\CategoryUpdated;
use Kurt\Modules\Blog\Models\Category;

final class CategoryObserver
{
    public function created(Category $category): void
    {
        CategoryCreated::dispatch($category);
    }

    public function updated(Category $category): void
    {
        CategoryUpdated::dispatch($category);
    }

    public function deleted(Category $category): void
    {
        CategoryDeleted::dispatch($category);
    }
}
