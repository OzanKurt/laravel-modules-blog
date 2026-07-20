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

- `Kurt\Modules\Blog\Models\Post` — with translatable title/excerpt/body/meta_*, status enum (Draft/Scheduled/Published/Archived), type enum (Text/Image/Video/Carousel), scopes (`published`, `scheduled`, `drafts`, `popular`, `inCategory`, `withTags`, `authoredBy`), a `relatedTo` scope, and a `related()` helper.
- `Category`, `Tag`, `Comment` models with their relations and scopes.
- `BlogAuthor` contract + `IsBlogAuthor` trait for your User model.
- `Kurt\Modules\Blog\Support\VideoUrl::parse()` for YouTube / Vimeo / DailyMotion URL parsing.
- `Kurt\Modules\Blog\Support\SeoMetadata::forPost()` for SEO meta resolution.
- `Kurt\Modules\Blog\Support\FeedBuilder` for RSS 2.0 / feed data, and `SitemapBuilder` for sitemap entries — see [Headless helpers](#headless-helpers).
- Console commands: `blog:publish-due`, `blog:upgrade-translations`, `blog:demo`.
- Domain events: `PostCreated`, `PostUpdated`, `PostPublished`, `PostArchived`, `CommentCreated`, `CommentApproved`, `CommentRejected`, ...

## Related posts

`$post->related(int $limit = 5)` returns published, non-self posts ranked by
relatedness: those sharing the most tags come first, then a shared category acts
as a fallback so lightly-tagged posts still surface neighbours. It runs as a
single query (no N+1) — only the current post's tag ids are loaded up front, and
the overlap count is a correlated subquery over the pivot.

```php
$related = $post->related();      // Collection<Post>, up to 5
$related = $post->related(10);    // widen the limit

// Or compose from the underlying scope (adds `shared_tags` /
// `shared_category` ordering columns), e.g. with eager loads:
Post::relatedTo($post)->with('category')->limit(3)->get();
```

A post with neither tags nor a category has no neighbours and yields an empty
collection.

## Headless helpers

The module ships no routes or views. Feed and sitemap generation are provided as
support classes that return a string or a data structure; your app decides where
to expose them.

### RSS / feed — `FeedBuilder`

`Kurt\Modules\Blog\Support\FeedBuilder` builds the latest published posts into an
RSS 2.0 XML string (`toRss()`) or a plain data structure (`toArray()`, for
JSON Feed / Atom / a Blade view). Count is configurable, and the feed can be
scoped to a category. All dynamic text is XML-escaped, so `toRss()` is always
well-formed.

```php
use Kurt\Modules\Blog\Support\FeedBuilder;

// In the consuming app's routes:
Route::get('feed', fn () => response(
    FeedBuilder::make()->limit(20)->toRss(),
    200,
    ['Content-Type' => 'application/rss+xml; charset=UTF-8'],
));

// Per-category feed, custom item URLs and channel metadata:
FeedBuilder::make()
    ->forCategory($category)
    ->title('My Blog')
    ->link(url('/'))
    ->linkUsing(fn ($post) => route('posts.show', $post->slug))
    ->toRss();
```

Defaults (feed title/description/limit) live under the `feed` key in
`config/blog.php`.

### Sitemap — `SitemapBuilder`

`Kurt\Modules\Blog\Support\SitemapBuilder` returns `SitemapEntry` objects
(`loc` / `lastmod` / `changefreq` / `priority`) for public content: every
published post, every category holding at least one published post, and
(opt-in) every tag that does. Draft, scheduled and future-dated posts — and
categories/tags whose only posts are non-public — are excluded.

```php
use Kurt\Modules\Blog\Support\SitemapBuilder;

$entries = SitemapBuilder::make()
    ->includeTags()                                   // optional
    ->postLinkUsing(fn ($post) => route('posts.show', $post->slug))
    ->entries();                                      // Collection<SitemapEntry>

$rows = SitemapBuilder::make()->toArray();            // array of loc/lastmod/... rows
```

Feed the entries into whichever sitemap package or response your app already
uses. Per-type change frequencies live under the `sitemap` key in
`config/blog.php`.

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
