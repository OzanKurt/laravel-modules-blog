# Changelog

All notable changes follow [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and [SemVer](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **Related posts** — `Post::related(int $limit = 5)` (backed by a `relatedTo`
  scope) returns published, non-self posts ranked by shared-tag overlap and
  then a shared category, in a single N+1-free query.
- **RSS / feed data** — `Kurt\Modules\Blog\Support\FeedBuilder` produces a valid
  RSS 2.0 XML string (`toRss()`) or a plain data structure (`toArray()`) for the
  latest published posts, with configurable count and optional per-category
  scoping. Headless: the consuming app wires it to a route.
- **Sitemap hooks** — `Kurt\Modules\Blog\Support\SitemapBuilder` returns
  `SitemapEntry` (loc/lastmod/changefreq/priority) rows for published posts,
  categories with published posts, and (opt-in) tags, for the app to feed into
  its own sitemap.
- `feed` and `sitemap` default blocks in `config/blog.php`.
- **REST API** — an opt-in JSON API built on the Core API kit
  (`ozankurt/laravel-modules-core` ^2.2). Full REST for posts, categories, tags
  and comments plus `publish`/`unpublish`/`related` post actions. Reads are
  public and respect the published scope; writes require auth and are guarded by
  the module Policies. Safe by default: nothing registers unless
  `BLOG_HTTP_MODE=api`. Adds the `http` block to `config/blog.php`, a
  `routes/api.php` file, Resources, FormRequests and controllers under
  `src/Http`.

### Fixed
- Policies now resolve the access gate via the `Gate` contract instead of the
  unbound `app('gate')` container alias, so the `canManageBlog` staff check works
  when a Policy is invoked (previously unreachable in the headless module).

## [2.2.1] - 2026-05-31

### Removed
- The legacy `blog_comments` table and its data migration. Comments live in the
  shared `interactions_comments` store from a fresh install.

## [2.2.0] - 2026-05-31

### Changed

- **Comments now persist through the [Interactions](https://github.com/OzanKurt/KurtModules-Interactions) module** instead of the Blog-local `blog_comments` table. `Comment` extends the Interactions comment, inheriting threading, revisions, soft-deletes, reactions, mentions, and a moderation audit trail while keeping the Blog-facing API: a `post_id` shim onto the polymorphic `commentable`, the `approval` enum mapped onto the Interactions `status` (pending↔Pending, approved↔Published, rejected↔Spam), and `approve()` / `reject()` — which now record `moderated_by` / `moderated_at` and still fire `CommentApproved` / `CommentRejected`.
- `Post::comments()` / `Post::approvedComments()` are now polymorphic `MorphMany` relations to the shared store.

### Added

- `ozankurt/laravel-modules-interactions` (`^1.3`) dependency.

## [2.1.0] - 2026-05-30

### Added
- Filament admin resources for **v3, v4, and v5** in parallel: `PostResource`, `CategoryResource`, `TagResource`, `CommentResource` under `src/Filament/V{3,4,5}`.
- Version-dispatching `Kurt\Modules\Blog\Filament\BlogPlugin` facade — register `->plugin(BlogPlugin::make())` on your panel and the matching V{n} resource set is resolved from the installed Filament major via Core's `FilamentVersion`.
- Post form with per-locale (en/tr) translatable title/excerpt/body and SEO meta, status/type enum selects, conditional `scheduled_for` (status=scheduled) and `video_url` (type=video) fields, category + tags relationship selects, and a `SpatieMediaLibraryFileUpload` cover; Comment moderation queue with approve/reject row + bulk actions.
- `filament/spatie-laravel-media-library-plugin` (`^3.0 || ^4.0 || ^5.0`) added to `require-dev` for the Post cover upload.
- Per-Filament-version PHPStan configs (`phpstan-filament-v{3,4,5}.neon`); the base `phpstan.neon` excludes the three version dirs and the dispatching facade.
- CI matrix gains a Filament axis (`3.*`, `4.*`, `5.*`) with a per-major PHPStan step.

## [2.0.1] - 2026-05-30

### Fixed
- Migrations now publish correctly via `vendor:publish --tag=modules-blog-migrations`. The previous bare-name `hasMigrations()` list pointed at non-existent source paths (real files are timestamp-prefixed), so consumers got an empty publish. Switched to `discoversMigrations()`.

## [2.0.0] - 2026-05-28

### Added
- Full v2 rewrite under vendor `ozankurt/laravel-modules-blog`.
- Models: `Category`, `Tag`, `Post`, `Comment` with translatable JSON columns, Sluggable, SoftDeletes.
- Enums: `PostStatus`, `PostType`, `CommentApproval`, `VideoProvider`.
- Support: `VideoUrl::parse()` -> `VideoSource`, `SeoMetadata::forPost()`, `BlogModels`.
- Console commands: `blog:publish-due`, `blog:upgrade-translations`, `blog:demo`.
- Domain events + observers.
- Default policies.
- `BlogAuthor` contract + `IsBlogAuthor` trait.
- GitHub Actions matrix CI (Laravel 12, PHP 8.4).

### Removed
- Repositories pattern.
- Custom Model base class.
- Global helper functions (`getYoutubeId`/`getVimeoId`/`getDailyMotionId`) — replaced by `VideoUrl::parse()`.
- `Http/blogRoutes.php`, `Http/Controllers/BlogController.php` — module is now headless.
- `Console\Commands\SeedCommand` — replaced by `DemoCommand`.

### Changed
- All translatable columns moved to JSON via `spatie/laravel-translatable`.
- Media uploads moved to `spatie/laravel-medialibrary`.
- `published_at` joined by new `status` and `scheduled_for` columns.

### Planned (v2.1)
- Filament v3/v4/v5 admin resources.
