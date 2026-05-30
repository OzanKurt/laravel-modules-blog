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

## Filament admin

The package ships parallel admin resource sets for Filament **v3, v4, and v5** —
`PostResource`, `CategoryResource`, `TagResource`, and `CommentResource`. The
correct set is chosen at runtime from the installed Filament major, so you
register a single version-dispatching plugin on your panel:

```php
use Filament\Panel;
use Kurt\Modules\Blog\Filament\BlogPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(BlogPlugin::make());
}
```

`BlogPlugin::make()` resolves to the matching `V3`/`V4`/`V5` plugin via
`Kurt\Modules\Core\Support\FilamentVersion`. Install whichever Filament major
your app uses — the resources require nothing beyond what the module already
depends on:

```bash
# whichever your app runs
composer require filament/filament:"^3.0|^4.0|^5.0"
composer require filament/spatie-laravel-media-library-plugin:"^3.0|^4.0|^5.0"
```

What the resources give you:

- **Posts** — per-locale (en/tr) translatable title/excerpt/body and SEO meta;
  status and type enum selects; a `scheduled_for` picker shown when the status
  is *Scheduled* and a `video_url` field shown when the type is *Video*;
  category and tag relationship selects; a Spatie media-library cover upload;
  a table with status/type filters, badges, author, category, publish date and
  view count.
- **Categories** — translatable name/description, parent (tree) select, slug
  read-only on edit, post counts.
- **Tags** — translatable name/description with a colour picker and a colour
  swatch column.
- **Comments** — a moderation queue defaulting to pending, with approve/reject
  row actions and approve/reject/delete bulk actions.

## License

MIT (c) Ozan Kurt
