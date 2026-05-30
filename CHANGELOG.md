# Changelog

All notable changes follow [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and [SemVer](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
