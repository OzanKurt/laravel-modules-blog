<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Observers;

use Kurt\Modules\Blog\Events\CommentCreated;
use Kurt\Modules\Blog\Models\Comment;
use Kurt\Modules\Interactions\Comments\Enums\CommentStatus;

final class CommentObserver
{
    public function creating(Comment $comment): void
    {
        if ($comment->getAttribute('status') === null) {
            $comment->status = config('blog.preapproved_comments')
                ? CommentStatus::Published
                : CommentStatus::Pending;
        }
    }

    public function created(Comment $comment): void
    {
        CommentCreated::dispatch($comment);
    }
}
