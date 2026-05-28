# Upgrade Guide — v0.x -> v2.0

The 2.0 line is a clean rewrite. Backwards compatibility is **not** preserved.

## Composer rename

```diff
-"ozankurt/modules-blog": "^0.4"
+"ozankurt/laravel-modules-blog": "^2.0"
```

## Custom Model base removed

Subclass `Illuminate\Database\Eloquent\Model` directly. The `getModel()` indirection is replaced by `config('blog.models.*')` and `Kurt\Modules\Blog\Support\BlogModels::post()/category()/tag()/comment()` helpers.

## Repositories removed

Replace `app(PostsRepositoryInterface::class)->all()` with `Post::query()->get()` (or use the new scopes).

## Media column -> medialibrary

The `media` column on `blog_posts` is dropped. Migrate:
- Single image / carousel rows -> import into `spatie-medialibrary` `cover` collection.
- Video rows -> copy the URL into the new `video_url` column.

## Translatable columns

`blog_posts.title|excerpt|body|meta_title|meta_description` and `blog_categories.name|description`, `blog_tags.name|description` are now JSON. Run the included artisan command:

```bash
php artisan blog:upgrade-translations --locale=en
```

It wraps existing scalar values in `{"en":"..."}`.

## Status + scheduled_for

New columns. Auto-derive:
- `published_at <= now()` -> `status='published'`
- Future `published_at` -> `status='scheduled'`, `scheduled_for = published_at`

## Trait rename

`Kurt\Modules\Blog\Traits\BlogUser` -> `Kurt\Modules\Blog\Concerns\IsBlogAuthor`.

## Routes + controller removed

Build your own routes; the module is headless.

## Requirements bump

PHP 8.4+. Laravel 12 or 13. Filament v3/v4/v5 admin coming in v2.1.
