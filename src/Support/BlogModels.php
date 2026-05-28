<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Support;

final class BlogModels
{
    public static function post(): string
    {
        return (string) config('blog.models.post');
    }

    public static function category(): string
    {
        return (string) config('blog.models.category');
    }

    public static function tag(): string
    {
        return (string) config('blog.models.tag');
    }

    public static function comment(): string
    {
        return (string) config('blog.models.comment');
    }
}
