# `ozankurt/laravel-modules-blog` v2.0 — Spec

**Repo:** `KurtModules-Blog`
**Date:** 2026-05-28
**Status:** Draft → user review pending
**Umbrella:** [2026-05-28-kurtmodules-v2-design.md](./2026-05-28-kurtmodules-v2-design.md)

---

## 1. Purpose

A headless blog module: posts, categories, tags, comments, with scheduled publishing, SEO metadata, drafts, multilingual content, media uploads via Spatie medialibrary, and a full Filament admin (v3/v4/v5 parallel).

## 2. v1 → v2 delta

| v1 concept | v2 fate |
|---|---|
| `Kurt\Modules\Blog\Models\Model` (custom base with `getModel()` indirection) | **Removed**. Each model extends `Illuminate\Database\Eloquent\Model` directly. Customising classes is done via config + a tiny `BlogModels` resolver. |
| `Repositories/**` (interface + Eloquent + caching decorators) | **Removed**. Eloquent + scopes are sufficient. |
| `Observers/**` registered in `Model::boot()` | **Kept** but registered in service provider via `Model::observe()` instead of inside models. |
| `Http/blogRoutes.php` + `Http/Controllers/BlogController.php` | **Removed**. v2 is headless. No routes, no controllers shipped. |
| `Console/Commands/SeedCommand.php` | **Replaced** by `BlogDemoCommand` that uses factories + a seeder class; opt-in via `--demo` flag. |
| `Traits/BlogUser.php` (adds `blogPosts`, `blogComments`, `blogPostsCount`, …) | **Kept**, modernised: drops `getCountFromRelation` in favour of `withCount` / accessor pattern; uses `ResolvesUser` from Core. |
| `src/video_functions.php` global functions | **Removed**. Replaced with `Kurt\Modules\Blog\Support\VideoUrl::parse()` static class returning a `VideoSource` value object. |
| `cviebrock/eloquent-sluggable` dependency | **Kept** at `^11.0` (Laravel 12-compatible). Used only on `Post::sluggable()` / `Category` / `Tag`. |
| `graham-campbell/markdown` dependency | **Removed**. Body is stored as Markdown text; rendering is the consumer's responsibility (the blog is headless). A `Post::renderedBody()` helper using `league/commonmark` is optional and behind config. |
| `published_at` only | **Extended**: `status` enum (`draft`, `scheduled`, `published`, `archived`) + `published_at` (when scheduled or published). |
| `media` polymorphic string column | **Removed**. Replaced by `spatie/laravel-medialibrary` collections. Video posts store the URL in a dedicated `video_url` column. |

## 3. Composer

```jsonc
{
  "name": "ozankurt/laravel-modules-blog",
  "description": "Headless blog module for Laravel with scheduled publishing, SEO, translations, and Filament admin.",
  "keywords": ["laravel", "filament", "kurtmodules", "blog"],
  "license": "MIT",
  "require": {
    "php": "^8.4",
    "illuminate/contracts": "^12.0 || ^13.0",
    "illuminate/database": "^12.0 || ^13.0",
    "illuminate/support": "^12.0 || ^13.0",
    "ozankurt/laravel-modules-core": "^2.0",
    "cviebrock/eloquent-sluggable": "^11.0 || ^12.0",
    "spatie/laravel-medialibrary": "^11.0",
    "spatie/laravel-package-tools": "^1.92",
    "spatie/laravel-translatable": "^6.11"
  },
  "require-dev": {
    "filament/filament": "^3.0 || ^4.0 || ^5.0",
    "filament/spatie-laravel-media-library-plugin": "^3.0 || ^4.0 || ^5.0",
    "larastan/larastan": "^3.0",
    "laravel/pint": "^1.18",
    "league/commonmark": "^2.5",
    "orchestra/testbench": "^9.0 || ^10.0",
    "pestphp/pest": "^3.0",
    "pestphp/pest-plugin-laravel": "^3.0",
    "rector/rector": "^2.0"
  },
  "suggest": {
    "league/commonmark": "Required if you use Post::renderedBody() for markdown rendering."
  }
}
```

## 4. Data model

### 4.1 Tables

```
blog_categories
  id, slug, name (json — translatable), description (json — translatable, nullable),
  parent_id (nullable, self FK, restrictOnDelete),
  position (unsigned int default 0),
  created_at, updated_at, deleted_at

blog_tags
  id, slug, name (json — translatable), description (json — translatable, nullable),
  color (string nullable),
  created_at, updated_at, deleted_at

blog_posts
  id, slug, title (json — translatable), excerpt (json — translatable, nullable),
  body (json — translatable, nullable), -- markdown source
  status (string — enum cast), -- draft|scheduled|published|archived
  type (string — enum cast), -- text|image|video|carousel
  video_url (string nullable), -- only when type=video
  user_id (FK users.id, cascadeOnDelete),
  category_id (FK blog_categories.id, restrictOnDelete, nullable),
  view_count (unsigned int default 0),
  last_viewer_ip (string nullable),
  published_at (timestamp nullable),
  scheduled_for (timestamp nullable), -- present when status=scheduled
  meta_title (json — translatable, nullable),
  meta_description (json — translatable, nullable),
  meta_og_image (string nullable), -- single medialibrary fallback path
  created_at, updated_at, deleted_at

blog_post_tag (pivot)
  post_id, tag_id, created_at, updated_at, primary(post_id, tag_id)

blog_comments
  id, post_id (FK blog_posts.id, cascadeOnDelete),
  user_id (FK users.id, cascadeOnDelete),
  parent_id (nullable, self FK, cascadeOnDelete), -- threaded replies (one level deep enforced in policy)
  body (text),
  approval (string — enum cast), -- pending|approved|rejected
  approved_at (timestamp nullable),
  rejected_at (timestamp nullable),
  approved_by (FK users.id nullable),
  rejected_by (FK users.id nullable),
  created_at, updated_at, deleted_at
```

All tables: `softDeletes`. `created_at`/`updated_at` standard.

### 4.2 Enums

```php
namespace Kurt\Modules\Blog\Enums;

enum PostStatus: string { case Draft='draft'; case Scheduled='scheduled'; case Published='published'; case Archived='archived'; }
enum PostType: string { case Text='text'; case Image='image'; case Video='video'; case Carousel='carousel'; }
enum CommentApproval: string { case Pending='pending'; case Approved='approved'; case Rejected='rejected'; }
enum VideoProvider: string { case YouTube='youtube'; case Vimeo='vimeo'; case DailyMotion='dailymotion'; }
```

### 4.3 Models

```
Kurt\Modules\Blog\Models\
  Category
  Tag
  Post  (Spatie\MediaLibrary\HasMedia)
  Comment
```

- `Post::$translatable = ['title', 'excerpt', 'body', 'meta_title', 'meta_description']`.
- `Category::$translatable = ['name', 'description']`.
- `Tag::$translatable = ['name', 'description']`.
- `Comment` is not translatable.
- All four use `Cviebrock\EloquentSluggable\Sluggable` (except `Comment`).
- Casts use the new enum casts (`'status' => PostStatus::class`).

### 4.4 Scopes (`Post`)

```php
scopePublished(Builder $q)                  // status=published AND published_at<=now()
scopeScheduled(Builder $q)                  // status=scheduled
scopeDrafts(Builder $q)
scopeArchived(Builder $q)
scopePopular(Builder $q, bool $desc = true) // order by view_count
scopeInCategory(Builder $q, Category|int $category)
scopeWithTags(Builder $q, array|int $tagIds, bool $matchAll = false) // replaces v1 scope
scopeAuthoredBy(Builder $q, $user)
```

### 4.5 Relationships

```
Post
  category()           BelongsTo Category
  user() / author()    BelongsTo (user model via UserResolver)
  comments()           HasMany Comment
  approvedComments()   HasMany Comment where approval=approved
  tags()               BelongsToMany Tag (table blog_post_tag)
Category
  posts()              HasMany Post
  parent()             BelongsTo self
  children()           HasMany self
Tag
  posts()              BelongsToMany Post
Comment
  post()               BelongsTo Post
  user() / author()    BelongsTo (user model)
  parent()             BelongsTo self
  replies()            HasMany self
```

Counts use Laravel's `withCount()` / `loadCount()` — no custom `*Count()` relations.

### 4.6 Comment approval logic

`Comment::create()` no longer overrides the parent; defaults are wired in an observer:

- `creating` event sets `approval = Approval::Pending` unless config `blog.preapproved_comments` is true.
- `Comment::approve(User $approver)` and `Comment::reject(User $rejector)` methods mutate the row and dispatch `CommentApproved` / `CommentRejected` events.

### 4.7 Video URL parsing

```php
namespace Kurt\Modules\Blog\Support;

final readonly class VideoSource {
    public function __construct(public VideoProvider $provider, public string $id, public string $url) {}
    public function embedUrl(): string;
    public function thumbnailUrl(string $quality = 'maxres'): string;
}

final class VideoUrl {
    public static function parse(string $url): ?VideoSource;
}
```

YouTube, Vimeo, DailyMotion supported. The accessor `Post::videoSource()` returns a `?VideoSource`. Old global `getYoutubeId`/`getVimeoId`/`getDailyMotionId` functions are gone.

### 4.8 SEO

- Per-post `meta_title`, `meta_description`, `meta_og_image` columns.
- `Post::seo()` returns a `SeoMetadata` value object with sensible fallbacks (title → post title; description → strip-tags(excerpt) or first 160 chars of body).
- `meta_og_image` resolution order: column override → medialibrary `social` collection → medialibrary `cover` first image → null.

### 4.9 Scheduled publishing

- `php artisan blog:publish-due` (registered command) flips `status` from `scheduled` to `published` when `scheduled_for <= now()`, sets `published_at`, dispatches `PostPublished`.
- The provider schedules this command every minute when `config('blog.scheduler.enabled', true)`.

## 5. Events

```
Kurt\Modules\Blog\Events\
  PostCreated(Post $post)
  PostUpdated(Post $post)
  PostPublished(Post $post)
  PostArchived(Post $post)
  CommentCreated(Comment $comment)
  CommentApproved(Comment $comment, $approver)
  CommentRejected(Comment $comment, $rejector)
  CategoryCreated/Updated/Deleted
  TagCreated/Updated/Deleted
```

## 6. Policies

`PostPolicy`, `CommentPolicy`, `CategoryPolicy`, `TagPolicy`. Default gates:

- view: anyone for published posts, author or staff (`canManageBlog` gate) for drafts/scheduled.
- create / update / delete: author or staff.
- approve/reject comment: staff.

Consumer can override by binding their own policy.

## 7. Filament resources

Resources shipped in all three of `src/Filament/V3`, `src/Filament/V4`, `src/Filament/V5`:

- `PostResource` (list, create, edit) — translatable inputs, schedule picker, media uploads, tag/category selects, SEO tab.
- `CategoryResource` — tree-flavoured table (parent_id).
- `TagResource`.
- `CommentResource` — approval bulk actions.

Each version pulls the matching Filament APIs (e.g. v4 form schema vs v3 form schema). The `BlogFilamentServiceProvider` registers exactly one set based on `FilamentVersion::major()`.

When working on Filament v5 resources, load the `epic-skills:filament-v5` skill (or user-level `filament-v5`).

## 8. Trait for user model

`Kurt\Modules\Blog\Concerns\IsBlogAuthor` (renamed from v1 `Traits\BlogUser`). Provides:

```php
public function blogPosts(): HasMany
public function blogComments(): HasMany
public function getAuthorDisplayName(): string
```

Counts via `withCount(['blogPosts', 'blogComments'])` rather than custom relations.

`Kurt\Modules\Blog\Contracts\BlogAuthor` interface with the same methods (without default impl).

## 9. Config (`config/blog.php`)

```php
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
        'conversions' => ['thumb' => [320, 320], 'cover' => [1200, 630]],
    ],
    'video' => [
        'thumbnail_quality' => ['youtube' => 'maxresdefault', 'vimeo' => 'thumbnail_large'],
    ],
    'models' => [
        'post' => Kurt\Modules\Blog\Models\Post::class,
        'category' => Kurt\Modules\Blog\Models\Category::class,
        'tag' => Kurt\Modules\Blog\Models\Tag::class,
        'comment' => Kurt\Modules\Blog\Models\Comment::class,
    ],
    'route_prefix' => 'blog', // used only by app code if it builds public routes
];
```

`BlogModels::post()`, `::category()`, etc. resolve `config('blog.models.*')`.

## 10. Test coverage targets

| Suite | Cases |
|---|---|
| Unit | `VideoUrl::parse` (all providers, edge URLs, returns null on garbage); `SeoMetadata` fallback chain; enum casts; scope SQL matches expected |
| Feature/Models | CRUD + relationships + counts via `withCount` |
| Feature/Scheduling | `blog:publish-due` flips status and dispatches `PostPublished` |
| Feature/Comments | Approval state machine + observer defaults |
| Feature/Filament/V{3,4,5} | List/create/edit screen smoke + form validation per resource |
| Feature/Media | Media collections register correctly; conversions defined |

## 11. Upgrade notes (`UPGRADE-2.0.md`)

- Vendor renamed: `ozankurt/modules-blog` → `ozankurt/laravel-modules-blog`. Update composer.json.
- Custom Model base class removed: subclass `Illuminate\Database\Eloquent\Model` instead. The `getModel()` indirection is replaced by `config('blog.models.*')` and `BlogModels::post()` helpers.
- Repositories removed. Replace `app(PostsRepositoryInterface::class)->all()` with `Post::query()->get()` (or use scopes).
- `media` column dropped. Run the data-migration script:
  - Single image / carousel rows → import into `spatie-medialibrary` `cover` collection.
  - Video rows → copy `media` value into new `video_url` column.
- `published_at` column kept; new `status` and `scheduled_for` columns added. Rows where `published_at <= now()` are auto-set to `status=published`; rows with future `published_at` become `status=scheduled` with `scheduled_for = published_at`.
- `Traits\BlogUser` → `Concerns\IsBlogAuthor`. Same public methods minus the v1 `*Count` relations (use `withCount` on the query instead).
- `Console\Commands\SeedCommand` → `blog:demo` (opt-in, factory-driven).
- Routes / controller removed. Build your own public routes; use `Post::query()->published()->paginate()` etc.
- All translatable columns become JSON. Run the included artisan command `blog:upgrade-translations --locale=en` to wrap existing scalar values in `{"en":"…"}`.

## 12. Directory layout

```
src/
  Concerns/IsBlogAuthor.php
  Console/Commands/
    PublishDuePostsCommand.php
    UpgradeTranslationsCommand.php
    DemoCommand.php
  Contracts/BlogAuthor.php
  Enums/
    CommentApproval.php
    PostStatus.php
    PostType.php
    VideoProvider.php
  Events/{…as §5}
  Exceptions/InvalidVideoUrl.php
  Filament/{V3,V4,V5}/Resources/{Post,Category,Tag,Comment}Resource.php
  Listeners/  (empty; consumer wires their own)
  Models/{Category,Comment,Post,Tag}.php
  Observers/{Category,Comment,Post,Tag}Observer.php
  Policies/{Category,Comment,Post,Tag}Policy.php
  Providers/
    BlogServiceProvider.php
    BlogFilamentServiceProvider.php
  Support/
    BlogModels.php
    RouteBuilder.php           # only used if a consumer asks for it; replaces v1 Links/HasLinks
    SeoMetadata.php
    VideoSource.php
    VideoUrl.php
config/blog.php
database/
  factories/{Category,Comment,Post,Tag}Factory.php
  migrations/
    2026_05_28_000001_create_blog_categories_table.php
    2026_05_28_000002_create_blog_tags_table.php
    2026_05_28_000003_create_blog_posts_table.php
    2026_05_28_000004_create_blog_post_tag_table.php
    2026_05_28_000005_create_blog_comments_table.php
lang/en/blog.php
lang/tr/blog.php
tests/…
```

## 13. Definition of done

- [ ] Matrix CI green: PHP 8.4 × Laravel 12/13 × Filament 3/4/5.
- [ ] `pint`, `phpstan`, `pest --coverage --min=80` pass.
- [ ] `UPGRADE-2.0.md` with concrete data-migration steps.
- [ ] `blog:upgrade-translations`, `blog:publish-due`, `blog:demo` commands work end-to-end.
- [ ] Filament resources render in all three versions (smoke + create + edit).
- [ ] Tagged `v2.0.0` on `master`.
