<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Kurt\Modules\Blog\Models\Post;

final class PostPublished
{
    use Dispatchable;

    public function __construct(public readonly Post $post) {}
}
