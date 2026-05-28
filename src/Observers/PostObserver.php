<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Observers;

use Kurt\Modules\Blog\Enums\PostStatus;
use Kurt\Modules\Blog\Events\PostArchived;
use Kurt\Modules\Blog\Events\PostCreated;
use Kurt\Modules\Blog\Events\PostPublished;
use Kurt\Modules\Blog\Events\PostUpdated;
use Kurt\Modules\Blog\Models\Post;

final class PostObserver
{
    public function created(Post $post): void
    {
        PostCreated::dispatch($post);
    }

    public function updated(Post $post): void
    {
        PostUpdated::dispatch($post);

        if ($post->wasChanged('status')) {
            match ($post->status) {
                PostStatus::Published => PostPublished::dispatch($post),
                PostStatus::Archived => PostArchived::dispatch($post),
                default => null,
            };
        }
    }
}
