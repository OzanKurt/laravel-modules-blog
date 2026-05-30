# Changelog

All notable changes follow [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and [SemVer](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
