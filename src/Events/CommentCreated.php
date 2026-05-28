<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Kurt\Modules\Blog\Models\Comment;

final class CommentCreated
{
    use Dispatchable;

    public function __construct(public readonly Comment $comment) {}
}
