<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Observers;

use Kurt\Modules\Blog\Enums\CommentApproval;
use Kurt\Modules\Blog\Events\CommentCreated;
use Kurt\Modules\Blog\Models\Comment;

final class CommentObserver
{
    public function creating(Comment $comment): void
    {
        if ($comment->approval === null) {
            $comment->approval = config('blog.preapproved_comments')
                ? CommentApproval::Approved
                : CommentApproval::Pending;
        }
    }

    public function created(Comment $comment): void
    {
        CommentCreated::dispatch($comment);
    }
}
