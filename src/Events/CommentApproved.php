<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Kurt\Modules\Blog\Models\Comment;

final class CommentApproved
{
    use Dispatchable;

    public function __construct(
        public readonly Comment $comment,
        public readonly Model $approver,
    ) {}
}
