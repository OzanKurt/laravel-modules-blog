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
    public function saving(Post $post): void
    {
        // Backfill the publish timestamp when a post is set Published without
        // an explicit published_at (e.g. the manual "Published" toggle in the
        // Filament editor). A saving hook covers every Filament version and
        // keeps scopePublished()/PostPolicy::view() treating it as live at
        // once instead of leaving it invisible behind a null published_at.
        if ($post->status === PostStatus::Published && $post->published_at === null) {
            $post->published_at = now();
        }
    }

    public function created(Post $post): void
    {
        PostCreated::dispatch($post);
    }

    public function updated(Post $post): void
    {
        // Skip PostUpdated when the only mutation is a recorded view, so
        // analytics bumps do not masquerade as content edits.
        $changed = array_keys($post->getChanges());
        $viewOnly = $changed !== [] && array_diff($changed, ['view_count', 'last_viewer_ip', 'updated_at']) === [];

        if (! $viewOnly) {
            PostUpdated::dispatch($post);
        }

        if ($post->wasChanged('status')) {
            match ($post->status) {
                PostStatus::Published => PostPublished::dispatch($post),
                PostStatus::Archived => PostArchived::dispatch($post),
                default => null,
            };
        }
    }
}
