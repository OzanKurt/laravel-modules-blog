# laravel-modules-blog

Headless blog module for Laravel: posts, categories, tags, comments, scheduled publishing, SEO meta, translatable content, Spatie medialibrary.

## Requirements

- PHP 8.4+
- Laravel 12.x or 13.x
- `ozankurt/laravel-modules-core` v2.x

## Installation

```bash
composer require ozankurt/laravel-modules-blog
```

Publish config and migrations:

```bash
php artisan vendor:publish --tag=blog-config
php artisan vendor:publish --tag=blog-migrations
php artisan migrate
```

## What it provides

- `Kurt\Modules\Blog\Models\Post` — with translatable title/excerpt/body/meta_*, status enum (Draft/Scheduled/Published/Archived), type enum (Text/Image/Video/Carousel), scopes (`published`, `scheduled`, `drafts`, `popular`, `inCategory`, `withTags`, `authoredBy`).
- `Category`, `Tag`, `Comment` models with their relations and scopes.
- `BlogAuthor` contract + `IsBlogAuthor` trait for your User model.
- `Kurt\Modules\Blog\Support\VideoUrl::parse()` for YouTube / Vimeo / DailyMotion URL parsing.
- `Kurt\Modules\Blog\Support\SeoMetadata::forPost()` for SEO meta resolution.
- Console commands: `blog:publish-due`, `blog:upgrade-translations`, `blog:demo`.
- Domain events: `PostCreated`, `PostUpdated`, `PostPublished`, `PostArchived`, `CommentCreated`, `CommentApproved`, `CommentRejected`, ...

## Filament

Filament v3/v4/v5 admin resources are planned for v2.1. The package is headless in v2.0.

## License

MIT (c) Ozan Kurt
