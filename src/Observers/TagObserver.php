<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Observers;

use Kurt\Modules\Blog\Events\TagCreated;
use Kurt\Modules\Blog\Events\TagDeleted;
use Kurt\Modules\Blog\Events\TagUpdated;
use Kurt\Modules\Blog\Models\Tag;

final class TagObserver
{
    public function created(Tag $tag): void
    {
        TagCreated::dispatch($tag);
    }

    public function updated(Tag $tag): void
    {
        TagUpdated::dispatch($tag);
    }

    public function deleted(Tag $tag): void
    {
        TagDeleted::dispatch($tag);
    }
}
