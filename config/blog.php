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

    // REST API surface (Core API kit). Safe-by-default: `mode` stays `headless`
    // so no routes are registered until a consumer opts in with
    // BLOG_HTTP_MODE=api (or `ui`). Read routes (index/show) are public and
    // respect the published scope; write routes additionally require the
    // `auth_middleware`. Every route is throttled by the `blog-api` limiter.
    'http' => [
        'mode' => env('BLOG_HTTP_MODE', 'headless'),
        'prefix' => 'api/blog',
        'middleware' => ['api'],
        'auth_middleware' => ['auth'],
        'rate_limit' => '60,1',
    ],

    // FeedBuilder defaults. `title`/`description` fall back to the app name and
    // a generic label when null; `limit` caps how many latest posts a feed
    // carries. All are overridable per feed via the builder's fluent setters.
    'feed' => [
        'title' => null,
        'description' => 'Latest posts',
        'limit' => 20,
    ],

    // SitemapBuilder per-type change frequencies.
    'sitemap' => [
        'changefreq' => [
            'posts' => 'weekly',
            'categories' => 'daily',
            'tags' => 'weekly',
        ],
    ],
];
