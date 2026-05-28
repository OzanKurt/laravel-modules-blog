<?php

declare(strict_types=1);

use Kurt\Modules\Blog\Models\Category;
use Kurt\Modules\Blog\Models\Comment;
use Kurt\Modules\Blog\Models\Post;
use Kurt\Modules\Blog\Models\Tag;

return [
    'preapproved_comments' => false,

    'allow_threaded_comments' => true,
    'comment_max_depth' => 1,

    'scheduler' => [
        'enabled' => true,
        'cron' => '* * * * *',
    ],

    'media' => [
        'disk' => env('BLOG_MEDIA_DISK', 'public'),
        'conversions' => [
            'thumb' => [320, 320],
            'cover' => [1200, 630],
        ],
    ],

    'video' => [
        'thumbnail_quality' => [
            'youtube' => 'maxresdefault',
            'vimeo' => 'thumbnail_large',
        ],
    ],

    'models' => [
        'post' => Post::class,
        'category' => Category::class,
        'tag' => Tag::class,
        'comment' => Comment::class,
    ],

    'route_prefix' => 'blog',
];
