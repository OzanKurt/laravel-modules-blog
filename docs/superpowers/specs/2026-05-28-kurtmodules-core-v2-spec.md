# `ozankurt/laravel-modules-core` v2.0 — Spec

**Repo:** `KurtModules-Core`
**Date:** 2026-05-28
**Status:** Draft → user review pending
**Umbrella:** [2026-05-28-kurtmodules-v2-design.md](./2026-05-28-kurtmodules-v2-design.md)

---

## 1. Purpose

Core is the **module bootstrap kit** every other KurtModule depends on. It provides:

- A shared base service provider (extends Spatie's `PackageServiceProvider`) plus discovery helpers for Filament v3/v4/v5.
- Common traits, contracts, and enums reused across Blog / Chat / Forum / Library.
- A test base (`PackageTestCase`) so each module's testbench bootstrap is one line.
- Static utilities (`UserResolver`) for resolving the consumer-supplied User model and primary key once.

Core has **zero domain logic**. It contains only cross-cutting plumbing.

## 2. v1 → v2 delta

| v1 (`ozankurt/modules-core`) | v2 (`ozankurt/laravel-modules-core`) |
|---|---|
| 3 traits, 1 utility, no provider | Provider + traits + contracts + enums + test base + utilities |
| `illuminate/support: 5.*\|6.*\|7.*` | `illuminate/contracts: ^12.0\|^13.0` only |
| No tests, no CI | Pest + GH Actions matrix |
| `Links` URL-template util tied to v1 `links` attribute | **Removed.** Templated URLs were a Blog-specific concept; v2 Blog ships a small `RouteBuilder` instead (see Blog spec). |
| `GetUserModelData` trait pulling `config('kurt_modules.user_model')` | `UserResolver` service + `ResolvesUser` trait, fed by `config('kurtmodules.user_model')` (renamed root key) |
| `GetCountFromRelation` trait | Removed — Laravel 12 has first-class `withCount()` + `loadCount()` |
| `HasLinks` trait | Removed (same reason as `Links`) |

## 3. Package metadata

```jsonc
{
  "name": "ozankurt/laravel-modules-core",
  "description": "Shared bootstrap kit for KurtModules Laravel packages.",
  "keywords": ["laravel", "filament", "kurtmodules", "core"],
  "license": "MIT",
  "require": {
    "php": "^8.4",
    "illuminate/contracts": "^12.0 || ^13.0",
    "illuminate/database": "^12.0 || ^13.0",
    "illuminate/support": "^12.0 || ^13.0",
    "spatie/laravel-package-tools": "^1.92"
  },
  "require-dev": {
    "pestphp/pest": "^3.0",
    "pestphp/pest-plugin-laravel": "^3.0",
    "orchestra/testbench": "^9.0 || ^10.0",
    "larastan/larastan": "^3.0",
    "laravel/pint": "^1.18",
    "filament/filament": "^3.0 || ^4.0 || ^5.0"
  },
  "autoload": { "psr-4": { "Kurt\\Modules\\Core\\": "src/" } },
  "autoload-dev": { "psr-4": { "Kurt\\Modules\\Core\\Tests\\": "tests/" } },
  "extra": {
    "laravel": { "providers": ["Kurt\\Modules\\Core\\Providers\\CoreServiceProvider"] }
  }
}
```

## 4. Public API

### 4.1 `Kurt\Modules\Core\Providers\PackageServiceProvider` (abstract)

Thin wrapper over `Spatie\LaravelPackageTools\PackageServiceProvider`. Adds:

- `protected function registerFilament(): void` — detects installed Filament major via `Composer\InstalledVersions::getVersion('filament/filament')`, then calls `registerFilamentV3() / V4() / V5()` if defined on the concrete subclass.
- `protected function module(): string` — abstract; returns module short-name (`'blog'`, `'chat'`, …).
- `protected function configKey(string $key): string` — sugar for `"{$this->module()}.{$key}"`.

### 4.2 `Kurt\Modules\Core\Providers\CoreServiceProvider`

Concrete provider for the Core package itself. Binds:

- `Kurt\Modules\Core\Contracts\UserResolver` → `Kurt\Modules\Core\Support\ConfigUserResolver` (singleton).
- Publishes `config/kurtmodules.php`.

### 4.3 `Kurt\Modules\Core\Contracts\UserResolver`

```php
interface UserResolver
{
    public function modelClass(): string;        // FQCN
    public function newQuery(): Builder;
    public function primaryKey(): string;        // column name
    public function table(): string;
}
```

Default implementation reads `config('kurtmodules.user_model', config('auth.providers.users.model'))`.

### 4.4 `Kurt\Modules\Core\Concerns\ResolvesUser` (trait)

Convenience wrapper used by module models to call `UserResolver` without manual `app()->make` plumbing.

```php
trait ResolvesUser
{
    protected function userResolver(): UserResolver
    {
        return app(UserResolver::class);
    }

    protected function userBelongsTo(string $foreignKey = 'user_id'): BelongsTo
    {
        $resolver = $this->userResolver();
        return $this->belongsTo($resolver->modelClass(), $foreignKey, $resolver->primaryKey());
    }
}
```

### 4.5 `Kurt\Modules\Core\Concerns\InteractsWithModuleConfig` (trait)

Tiny helper for module models needing `config("{$module}.foo")` access; standardises the path.

### 4.6 `Kurt\Modules\Core\Support\FilamentVersion`

```php
final class FilamentVersion
{
    public static function major(): ?int;          // 3, 4, 5, or null
    public static function isAtLeast(int $major): bool;
    public static function isExactly(int $major): bool;
}
```

Reads via `Composer\InstalledVersions::getVersion('filament/filament')`. Returns null when Filament is not installed (modules degrade to headless).

### 4.7 `Kurt\Modules\Core\Enums\Common`

Generic enums used cross-module:

- `Visibility { Public, Unlisted, Private }`
- `Approval { Pending, Approved, Rejected }`
- `MediaKind { None, Image, Video, Carousel, File, Document, Link }`

Modules **may** re-export their own narrower enums but should prefer Core's where the semantics fit.

### 4.8 `Kurt\Modules\Core\Testing\PackageTestCase`

Abstract Pest-friendly Testbench base. Sets up:

- SQLite `:memory:` DB.
- A minimal `users` table migration.
- `User` model implementing the relevant author contracts (used by all module test suites).
- `setUp()` runs the module's anonymous migrations.

Modules extend it like:

```php
abstract class TestCase extends \Kurt\Modules\Core\Testing\PackageTestCase
{
    protected function getPackageProviders($app): array
    {
        return [\Kurt\Modules\Core\Providers\CoreServiceProvider::class, \Kurt\Modules\Blog\Providers\BlogServiceProvider::class];
    }
}
```

### 4.9 Config (`config/kurtmodules.php`)

```php
return [
    'user_model' => env('KURTMODULES_USER_MODEL', null), // null → falls back to auth.providers.users.model
    'date_format' => 'Y-m-d H:i:s',
];
```

## 5. Removed in v2

- `Kurt\Modules\Core\Links` — Blog-only concern; superseded by Blog's `RouteBuilder` (see Blog spec).
- `Kurt\Modules\Core\Traits\GetCountFromRelation` — Laravel `withCount` / `loadCount` is the idiomatic replacement.
- `Kurt\Modules\Core\Traits\GetUserModelData` — replaced by `UserResolver` + `ResolvesUser` trait.
- `Kurt\Modules\Core\Traits\HasLinks` — same fate as `Links`.

## 6. Directory layout

```
src/
  Concerns/
    InteractsWithModuleConfig.php
    ResolvesUser.php
  Contracts/
    UserResolver.php
  Enums/
    Approval.php
    MediaKind.php
    Visibility.php
  Providers/
    CoreServiceProvider.php
    PackageServiceProvider.php
  Support/
    ConfigUserResolver.php
    FilamentVersion.php
  Testing/
    PackageTestCase.php
config/kurtmodules.php
tests/
  Feature/FilamentVersionTest.php
  Feature/UserResolverTest.php
  Pest.php
  TestCase.php
```

## 7. Migrations

None. Core ships no tables.

## 8. Tests

- `FilamentVersionTest`: covers each major detection branch via stubbed `InstalledVersions`.
- `UserResolverTest`: default resolution from `auth.providers.users.model`; override via `kurtmodules.user_model`; primary-key resolution.
- `PackageTestCaseSmokeTest`: verifies base test case boots and migrates a dummy `users` table.

## 9. Upgrade notes (`UPGRADE-2.0.md`)

- Vendor renamed from `ozankurt/modules-core` to `ozankurt/laravel-modules-core`.
- Composer key rename: `config('kurt_modules.*')` → `config('kurtmodules.*')`.
- Three removed traits documented with one-line replacements each.
- No data migration needed (Core never had tables).

## 10. Definition of done

- [ ] `composer install` green on PHP 8.4 + Laravel 12 + Filament 3/4/5 matrix.
- [ ] `vendor/bin/pint --test` clean.
- [ ] `vendor/bin/phpstan analyse` at level 8 clean.
- [ ] `vendor/bin/pest --coverage --min=80` passes.
- [ ] GitHub Actions matrix green on every cell.
- [ ] `README.md` + `CHANGELOG.md` + `UPGRADE-2.0.md` populated.
- [ ] Tag `v2.0.0` cut from `master`.
