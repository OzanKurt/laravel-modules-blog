# `ozankurt/laravel-modules-core` v2.0 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild `KurtModules-Core` as the shared bootstrap kit every other KurtModule depends on. Ship `ozankurt/laravel-modules-core` v2.0.0 with a Spatie-style package provider, user-model resolver, Filament version detector, common enums, and a Pest-friendly test base.

**Architecture:** Wraps `spatie/laravel-package-tools`. Exposes an abstract `PackageServiceProvider` subclass that detects installed Filament major and dispatches to `registerFilamentV3/V4/V5()` hooks on concrete subclasses. User-model resolution is centralised behind a `UserResolver` contract bound in the container.

**Tech Stack:** PHP 8.4, Laravel 12/13, Pest 3 + Orchestra Testbench, PHPStan 8 via Larastan, Pint, GitHub Actions matrix.

**Spec:** [2026-05-28-kurtmodules-core-v2-spec.md](../specs/2026-05-28-kurtmodules-core-v2-spec.md)

**Working directory:** `D:\Code\Projects\KurtModules-Core` for ALL tasks. (The Blog repo only hosts specs/plans.)

---

## Task 0: Repo prep

**Files:**
- Delete: every existing v1 file in `D:\Code\Projects\KurtModules-Core` except `.git/`, `SECURITY.md`, `LICENSE.md` (if present)
- Modify: `D:\Code\Projects\KurtModules-Core\README.md` (write fresh)

- [ ] **Step 1: Create v2 branch from master**

```bash
cd D:/Code/Projects/KurtModules-Core
git fetch --all --prune
git switch master
git pull
git switch -c v2.0
```

- [ ] **Step 2: Snapshot v1 onto legacy branch**

```bash
git switch master
git switch -c v1-legacy
git push -u origin v1-legacy
git switch v2.0
```

- [ ] **Step 3: Remove v1 source**

```bash
git rm -r src composer.json composer.lock contributors.txt README.md
git commit -m "chore: remove v1 sources ahead of v2 rebuild"
```

(Keep `SECURITY.md`.)

---

## Task 1: composer.json

**Files:**
- Create: `composer.json`

- [ ] **Step 1: Write composer.json**

```json
{
  "name": "ozankurt/laravel-modules-core",
  "description": "Shared bootstrap kit for KurtModules Laravel packages.",
  "keywords": ["laravel", "filament", "kurtmodules", "core"],
  "license": "MIT",
  "authors": [{ "name": "Ozan Kurt", "email": "ozankurt2@gmail.com" }],
  "require": {
    "php": "^8.4",
    "illuminate/contracts": "^12.0 || ^13.0",
    "illuminate/database": "^12.0 || ^13.0",
    "illuminate/support": "^12.0 || ^13.0",
    "spatie/laravel-package-tools": "^1.92"
  },
  "require-dev": {
    "filament/filament": "^3.0 || ^4.0 || ^5.0",
    "larastan/larastan": "^3.0",
    "laravel/pint": "^1.18",
    "orchestra/testbench": "^9.0 || ^10.0",
    "pestphp/pest": "^3.0",
    "pestphp/pest-plugin-laravel": "^3.0",
    "rector/rector": "^2.0"
  },
  "autoload": {
    "psr-4": { "Kurt\\Modules\\Core\\": "src/" }
  },
  "autoload-dev": {
    "psr-4": { "Kurt\\Modules\\Core\\Tests\\": "tests/" }
  },
  "extra": {
    "laravel": {
      "providers": ["Kurt\\Modules\\Core\\Providers\\CoreServiceProvider"]
    }
  },
  "config": {
    "sort-packages": true,
    "allow-plugins": {
      "pestphp/pest-plugin": true
    }
  },
  "scripts": {
    "test": "vendor/bin/pest",
    "test:coverage": "vendor/bin/pest --coverage --min=80",
    "lint": "vendor/bin/pint --test",
    "format": "vendor/bin/pint",
    "stan": "vendor/bin/phpstan analyse --memory-limit=2G"
  },
  "minimum-stability": "stable",
  "prefer-stable": true
}
```

- [ ] **Step 2: Run composer validate**

Run: `composer validate --strict`
Expected: `./composer.json is valid`

- [ ] **Step 3: Install dependencies**

Run: `composer install --no-interaction`
Expected: vendor/ populated, no errors.

- [ ] **Step 4: Commit**

```bash
git add composer.json composer.lock
git commit -m "feat: composer scaffold targeting laravel 12/13 + filament 3/4/5"
```

---

## Task 2: Pint + PHPStan + Rector configs

**Files:**
- Create: `pint.json`
- Create: `phpstan.neon`
- Create: `rector.php`
- Create: `.gitattributes`
- Create: `.gitignore`

- [ ] **Step 1: Write `pint.json`**

```json
{
    "preset": "laravel",
    "rules": {
        "declare_strict_types": true,
        "ordered_imports": { "sort_algorithm": "alpha" },
        "no_unused_imports": true
    }
}
```

- [ ] **Step 2: Write `phpstan.neon`**

```neon
includes:
    - vendor/larastan/larastan/extension.neon

parameters:
    level: 8
    paths:
        - src
    tmpDir: build/phpstan
```

- [ ] **Step 3: Write `rector.php`**

```php
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
    ->withPaths([__DIR__.'/src', __DIR__.'/tests'])
    ->withSets([
        LevelSetList::UP_TO_PHP_84,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::TYPE_DECLARATION,
    ]);
```

- [ ] **Step 4: Write `.gitattributes`**

```
/.github                export-ignore
/tests                  export-ignore
/.gitattributes         export-ignore
/.gitignore             export-ignore
/phpstan.neon           export-ignore
/pint.json              export-ignore
/rector.php             export-ignore
/.editorconfig          export-ignore
```

- [ ] **Step 5: Write `.gitignore`**

```
/vendor
/build
.phpunit.result.cache
composer.lock.bak
.idea
.phpstorm.meta.php
```

- [ ] **Step 6: Run Pint dry run**

Run: `vendor/bin/pint --test`
Expected: "PASS … 0 files passed" (no source files yet).

- [ ] **Step 7: Commit**

```bash
git add pint.json phpstan.neon rector.php .gitattributes .gitignore
git commit -m "chore: add pint, phpstan, rector configs"
```

---

## Task 3: Config file

**Files:**
- Create: `config/kurtmodules.php`

- [ ] **Step 1: Write config**

```php
<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | KurtModules global config
    |--------------------------------------------------------------------------
    |
    | Settings shared by every kurtmodules package.
    |
    */

    'user_model' => env('KURTMODULES_USER_MODEL'),

    'date_format' => 'Y-m-d H:i:s',
];
```

- [ ] **Step 2: Commit**

```bash
git add config/kurtmodules.php
git commit -m "feat(core): add base config file"
```

---

## Task 4: Common enums

**Files:**
- Create: `src/Enums/Approval.php`
- Create: `src/Enums/MediaKind.php`
- Create: `src/Enums/Visibility.php`
- Create: `tests/Unit/Enums/ApprovalTest.php`
- Create: `tests/Unit/Enums/MediaKindTest.php`
- Create: `tests/Unit/Enums/VisibilityTest.php`

- [ ] **Step 1: Write failing test for Approval enum**

```php
<?php

declare(strict_types=1);

use Kurt\Modules\Core\Enums\Approval;

it('exposes pending, approved and rejected cases with stable string values', function () {
    expect(Approval::Pending->value)->toBe('pending');
    expect(Approval::Approved->value)->toBe('approved');
    expect(Approval::Rejected->value)->toBe('rejected');
});

it('is constructible from value', function () {
    expect(Approval::from('approved'))->toBe(Approval::Approved);
});
```

- [ ] **Step 2: Run test (must fail)**

Run: `vendor/bin/pest tests/Unit/Enums/ApprovalTest.php`
Expected: `Error: Class "Kurt\Modules\Core\Enums\Approval" not found.`

- [ ] **Step 3: Implement Approval enum**

```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Core\Enums;

enum Approval: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
```

- [ ] **Step 4: Re-run — must pass**

Run: `vendor/bin/pest tests/Unit/Enums/ApprovalTest.php`
Expected: 2 passed.

- [ ] **Step 5: Repeat steps 1–4 for MediaKind**

Test:
```php
<?php

declare(strict_types=1);

use Kurt\Modules\Core\Enums\MediaKind;

it('covers none/image/video/carousel/file/document/link', function () {
    expect(MediaKind::cases())->toHaveCount(7);
    expect(MediaKind::None->value)->toBe('none');
    expect(MediaKind::Image->value)->toBe('image');
    expect(MediaKind::Video->value)->toBe('video');
    expect(MediaKind::Carousel->value)->toBe('carousel');
    expect(MediaKind::File->value)->toBe('file');
    expect(MediaKind::Document->value)->toBe('document');
    expect(MediaKind::Link->value)->toBe('link');
});
```

Implementation:
```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Core\Enums;

enum MediaKind: string
{
    case None = 'none';
    case Image = 'image';
    case Video = 'video';
    case Carousel = 'carousel';
    case File = 'file';
    case Document = 'document';
    case Link = 'link';
}
```

- [ ] **Step 6: Repeat for Visibility**

Test:
```php
<?php

declare(strict_types=1);

use Kurt\Modules\Core\Enums\Visibility;

it('exposes public/unlisted/private', function () {
    expect(Visibility::Public->value)->toBe('public');
    expect(Visibility::Unlisted->value)->toBe('unlisted');
    expect(Visibility::Private->value)->toBe('private');
});
```

Implementation:
```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Core\Enums;

enum Visibility: string
{
    case Public = 'public';
    case Unlisted = 'unlisted';
    case Private = 'private';
}
```

- [ ] **Step 7: Commit**

```bash
git add src/Enums tests/Unit/Enums
git commit -m "feat(core): add Approval, MediaKind, Visibility enums with tests"
```

---

## Task 5: `UserResolver` contract + default impl

**Files:**
- Create: `src/Contracts/UserResolver.php`
- Create: `src/Support/ConfigUserResolver.php`
- Create: `src/Concerns/ResolvesUser.php`
- Create: `tests/Feature/UserResolverTest.php`
- Create: `tests/Stubs/StubUser.php`

- [ ] **Step 1: Write the contract**

```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Core\Contracts;

use Illuminate\Database\Eloquent\Builder;

interface UserResolver
{
    /** Fully-qualified class name of the user model. */
    public function modelClass(): string;

    /** Builder for the user model. */
    public function newQuery(): Builder;

    /** Primary-key column name on the user model. */
    public function primaryKey(): string;

    /** Database table the user model maps to. */
    public function table(): string;
}
```

- [ ] **Step 2: Write the stub user model used by tests**

```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Core\Tests\Stubs;

use Illuminate\Database\Eloquent\Model;

final class StubUser extends Model
{
    protected $table = 'users';
    protected $guarded = [];
    public $timestamps = false;
}
```

- [ ] **Step 3: Write failing test for ConfigUserResolver**

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Kurt\Modules\Core\Contracts\UserResolver;
use Kurt\Modules\Core\Support\ConfigUserResolver;
use Kurt\Modules\Core\Tests\Stubs\StubUser;

it('prefers kurtmodules.user_model over auth.providers.users.model', function () {
    Config::set('kurtmodules.user_model', StubUser::class);
    Config::set('auth.providers.users.model', \stdClass::class);

    expect(app(UserResolver::class)->modelClass())->toBe(StubUser::class);
});

it('falls back to auth.providers.users.model when kurtmodules.user_model is null', function () {
    Config::set('kurtmodules.user_model', null);
    Config::set('auth.providers.users.model', StubUser::class);

    expect(app(UserResolver::class)->modelClass())->toBe(StubUser::class);
});

it('exposes primary key and table', function () {
    Config::set('kurtmodules.user_model', StubUser::class);

    $resolver = app(UserResolver::class);

    expect($resolver->primaryKey())->toBe('id');
    expect($resolver->table())->toBe('users');
});
```

- [ ] **Step 4: Run — must fail with binding/class-not-found**

Run: `vendor/bin/pest tests/Feature/UserResolverTest.php`
Expected: failures because `ConfigUserResolver` does not exist and `UserResolver` is not bound (we'll bind it in Task 8 when CoreServiceProvider lands; for now, also fails because class missing).

- [ ] **Step 5: Implement ConfigUserResolver**

```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Core\Support;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Kurt\Modules\Core\Contracts\UserResolver;

final class ConfigUserResolver implements UserResolver
{
    public function __construct(private readonly Repository $config) {}

    public function modelClass(): string
    {
        $class = $this->config->get('kurtmodules.user_model')
            ?: $this->config->get('auth.providers.users.model');

        if (! is_string($class) || $class === '') {
            throw new \RuntimeException('No user model configured. Set kurtmodules.user_model or auth.providers.users.model.');
        }

        return $class;
    }

    public function newQuery(): Builder
    {
        return $this->modelInstance()->newQuery();
    }

    public function primaryKey(): string
    {
        return $this->modelInstance()->getKeyName();
    }

    public function table(): string
    {
        return $this->modelInstance()->getTable();
    }

    private function modelInstance(): Model
    {
        $class = $this->modelClass();

        return new $class();
    }
}
```

- [ ] **Step 6: Write the ResolvesUser trait**

```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Core\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kurt\Modules\Core\Contracts\UserResolver;

trait ResolvesUser
{
    protected function userResolver(): UserResolver
    {
        return app(UserResolver::class);
    }

    /**
     * Return a BelongsTo to the consumer-supplied user model.
     */
    protected function userBelongsTo(string $foreignKey = 'user_id'): BelongsTo
    {
        $resolver = $this->userResolver();

        return $this->belongsTo(
            $resolver->modelClass(),
            $foreignKey,
            $resolver->primaryKey(),
        );
    }
}
```

- [ ] **Step 7: Re-run UserResolver tests**

These still fail until the CoreServiceProvider binds the contract (Task 8). Mark the file with `->skip()` temporarily? **No.** Continue, but expect failure. We will revisit in Task 8.

- [ ] **Step 8: Commit (failing tests OK at this point — they pass after Task 8)**

```bash
git add src/Contracts src/Support/ConfigUserResolver.php src/Concerns/ResolvesUser.php tests/Stubs tests/Feature/UserResolverTest.php
git commit -m "feat(core): add UserResolver contract, ConfigUserResolver, ResolvesUser trait"
```

---

## Task 6: `FilamentVersion` support class

**Files:**
- Create: `src/Support/FilamentVersion.php`
- Create: `tests/Unit/Support/FilamentVersionTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php

declare(strict_types=1);

use Kurt\Modules\Core\Support\FilamentVersion;

afterEach(function () {
    FilamentVersion::override(null);
});

it('returns null when filament is not installed', function () {
    FilamentVersion::override(false);

    expect(FilamentVersion::major())->toBeNull();
    expect(FilamentVersion::isAtLeast(3))->toBeFalse();
    expect(FilamentVersion::isExactly(3))->toBeFalse();
});

it('extracts major from semver string', function () {
    FilamentVersion::override('3.2.1');

    expect(FilamentVersion::major())->toBe(3);
    expect(FilamentVersion::isAtLeast(3))->toBeTrue();
    expect(FilamentVersion::isExactly(3))->toBeTrue();
    expect(FilamentVersion::isAtLeast(4))->toBeFalse();
});

it('handles prefixed versions like v4.0.0-beta', function () {
    FilamentVersion::override('v4.0.0-beta.2');

    expect(FilamentVersion::major())->toBe(4);
});
```

- [ ] **Step 2: Run — must fail**

Run: `vendor/bin/pest tests/Unit/Support/FilamentVersionTest.php`
Expected: class not found.

- [ ] **Step 3: Implement FilamentVersion**

```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Core\Support;

use Composer\InstalledVersions;

final class FilamentVersion
{
    /** Test override. `null` = use composer. `false` = simulate "not installed". Anything else = forced version string. */
    private static null|false|string $override = null;

    public static function major(): ?int
    {
        $version = self::resolve();

        if ($version === null) {
            return null;
        }

        if (! preg_match('/(\d+)/', $version, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    public static function isAtLeast(int $major): bool
    {
        $current = self::major();

        return $current !== null && $current >= $major;
    }

    public static function isExactly(int $major): bool
    {
        return self::major() === $major;
    }

    /** @internal Test hook. */
    public static function override(null|false|string $value): void
    {
        self::$override = $value;
    }

    private static function resolve(): ?string
    {
        if (self::$override === false) {
            return null;
        }

        if (is_string(self::$override)) {
            return self::$override;
        }

        if (! class_exists(InstalledVersions::class)) {
            return null;
        }

        try {
            return InstalledVersions::getVersion('filament/filament');
        } catch (\Throwable) {
            return null;
        }
    }
}
```

- [ ] **Step 4: Re-run tests — must pass**

Run: `vendor/bin/pest tests/Unit/Support/FilamentVersionTest.php`
Expected: 3 passed.

- [ ] **Step 5: Commit**

```bash
git add src/Support/FilamentVersion.php tests/Unit/Support/FilamentVersionTest.php
git commit -m "feat(core): add FilamentVersion detector"
```

---

## Task 7: `InteractsWithModuleConfig` trait

**Files:**
- Create: `src/Concerns/InteractsWithModuleConfig.php`
- Create: `tests/Unit/Concerns/InteractsWithModuleConfigTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;

it('reads a namespaced key', function () {
    $obj = new class {
        use \Kurt\Modules\Core\Concerns\InteractsWithModuleConfig;
        protected function module(): string { return 'blog'; }
        public function probe(string $key, mixed $default = null): mixed { return $this->moduleConfig($key, $default); }
    };

    Config::set('blog.foo', 'bar');
    expect($obj->probe('foo'))->toBe('bar');
    expect($obj->probe('missing', 'default'))->toBe('default');
});
```

- [ ] **Step 2: Run — must fail**

- [ ] **Step 3: Implement trait**

```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Core\Concerns;

trait InteractsWithModuleConfig
{
    abstract protected function module(): string;

    protected function moduleConfig(string $key, mixed $default = null): mixed
    {
        return config("{$this->module()}.{$key}", $default);
    }
}
```

- [ ] **Step 4: Re-run — must pass**

- [ ] **Step 5: Commit**

```bash
git add src/Concerns/InteractsWithModuleConfig.php tests/Unit/Concerns
git commit -m "feat(core): add InteractsWithModuleConfig trait"
```

---

## Task 8: Abstract `PackageServiceProvider` + concrete `CoreServiceProvider`

**Files:**
- Create: `src/Providers/PackageServiceProvider.php`
- Create: `src/Providers/CoreServiceProvider.php`
- Create: `tests/Feature/CoreServiceProviderTest.php`

- [ ] **Step 1: Write the abstract base**

```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Core\Providers;

use Kurt\Modules\Core\Support\FilamentVersion;
use Spatie\LaravelPackageTools\PackageServiceProvider as BasePackageServiceProvider;

abstract class PackageServiceProvider extends BasePackageServiceProvider
{
    /** Module short-name, e.g. 'blog'. */
    abstract protected function module(): string;

    final protected function configKey(string $key): string
    {
        return "{$this->module()}.{$key}";
    }

    /**
     * Hook to wire Filament resources. Concrete providers may override
     * registerFilamentV3 / V4 / V5 to attach the matching resource set.
     */
    final protected function registerFilament(): void
    {
        $major = FilamentVersion::major();

        match (true) {
            $major === 5 => $this->registerFilamentV5(),
            $major === 4 => $this->registerFilamentV4(),
            $major === 3 => $this->registerFilamentV3(),
            default => null,
        };
    }

    protected function registerFilamentV3(): void {}
    protected function registerFilamentV4(): void {}
    protected function registerFilamentV5(): void {}
}
```

- [ ] **Step 2: Write the concrete CoreServiceProvider**

```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Core\Providers;

use Kurt\Modules\Core\Contracts\UserResolver;
use Kurt\Modules\Core\Support\ConfigUserResolver;
use Spatie\LaravelPackageTools\Package;

final class CoreServiceProvider extends PackageServiceProvider
{
    protected function module(): string
    {
        return 'kurtmodules';
    }

    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-modules-core')
            ->hasConfigFile('kurtmodules');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(UserResolver::class, fn ($app) => new ConfigUserResolver($app['config']));
    }
}
```

- [ ] **Step 3: Write a smoke test for the provider**

```php
<?php

declare(strict_types=1);

use Kurt\Modules\Core\Contracts\UserResolver;
use Kurt\Modules\Core\Support\ConfigUserResolver;

it('binds UserResolver to ConfigUserResolver', function () {
    expect(app(UserResolver::class))->toBeInstanceOf(ConfigUserResolver::class);
});

it('publishes config under kurtmodules key', function () {
    expect(config('kurtmodules.date_format'))->toBe('Y-m-d H:i:s');
});
```

- [ ] **Step 4: Implement TestCase (next task) before this passes — skip running yet**

(Continue to Task 9; the provider tests run there.)

- [ ] **Step 5: Commit (tests will go green after Task 9)**

```bash
git add src/Providers tests/Feature/CoreServiceProviderTest.php
git commit -m "feat(core): add abstract PackageServiceProvider and CoreServiceProvider"
```

---

## Task 9: `PackageTestCase` test base

**Files:**
- Create: `src/Testing/PackageTestCase.php`
- Create: `tests/Pest.php`
- Create: `tests/TestCase.php`

- [ ] **Step 1: Write PackageTestCase**

```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Core\Testing;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Kurt\Modules\Core\Providers\CoreServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class PackageTestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return array_unique(array_merge(
            [CoreServiceProvider::class],
            $this->modulePackageProviders($app),
        ));
    }

    /** @return array<int, class-string> */
    protected function modulePackageProviders($app): array
    {
        return [];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->timestamps();
        });
    }
}
```

- [ ] **Step 2: Write tests/TestCase.php**

```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Core\Tests;

use Kurt\Modules\Core\Testing\PackageTestCase;

abstract class TestCase extends PackageTestCase {}
```

- [ ] **Step 3: Write tests/Pest.php**

```php
<?php

declare(strict_types=1);

use Kurt\Modules\Core\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');
pest()->extend(TestCase::class)->in('Unit');
```

- [ ] **Step 4: Run the full suite**

Run: `vendor/bin/pest`
Expected: all tests from Tasks 4, 6, 7, 8 (and Task 5's UserResolver tests once Config::set wiring picks up the binding) — all green.

- [ ] **Step 5: Commit**

```bash
git add src/Testing tests/Pest.php tests/TestCase.php
git commit -m "feat(core): add PackageTestCase test base + Pest bootstrap"
```

---

## Task 10: PHPStan + Pint pass

- [ ] **Step 1: Run Pint check**

Run: `vendor/bin/pint --test`
Expected: PASS on all files. Run `vendor/bin/pint` to fix any styling violations, then re-run `--test`.

- [ ] **Step 2: Run PHPStan**

Run: `vendor/bin/phpstan analyse --memory-limit=2G`
Expected: `[OK] No errors`. Fix any issues; common fixes: add return types, narrow `mixed`, add docblocks for generic Eloquent collections.

- [ ] **Step 3: Commit any cleanup**

```bash
git add -A
git commit -m "chore: pint + phpstan clean"
```

---

## Task 11: GitHub Actions CI

**Files:**
- Create: `.github/workflows/tests.yml`
- Create: `.github/dependabot.yml`

- [ ] **Step 1: Write tests.yml**

```yaml
name: tests

on:
    push:
        branches: [master, v2.0]
    pull_request:

permissions:
    contents: read

jobs:
    test:
        runs-on: ubuntu-latest
        strategy:
            fail-fast: false
            matrix:
                php: ['8.4']
                laravel: ['12.*', '13.*']
                include:
                    - laravel: 12.*
                      testbench: 9.*
                    - laravel: 13.*
                      testbench: 10.*

        name: PHP ${{ matrix.php }} - Laravel ${{ matrix.laravel }}

        steps:
            - uses: actions/checkout@v4

            - name: Setup PHP
              uses: shivammathur/setup-php@v2
              with:
                  php-version: ${{ matrix.php }}
                  coverage: pcov
                  tools: composer:v2

            - name: Cache composer
              uses: actions/cache@v4
              with:
                  path: ~/.composer/cache
                  key: composer-${{ matrix.php }}-${{ matrix.laravel }}-${{ hashFiles('composer.json') }}

            - name: Require Laravel ${{ matrix.laravel }}
              run: |
                  composer require --no-update --no-interaction \
                      "illuminate/contracts:${{ matrix.laravel }}" \
                      "illuminate/database:${{ matrix.laravel }}" \
                      "illuminate/support:${{ matrix.laravel }}" \
                      "orchestra/testbench:${{ matrix.testbench }}"

            - name: Install
              run: composer update --prefer-stable --prefer-dist --no-interaction

            - name: Pint
              run: vendor/bin/pint --test

            - name: PHPStan
              run: vendor/bin/phpstan analyse --memory-limit=2G

            - name: Pest
              run: vendor/bin/pest --coverage --min=80
```

- [ ] **Step 2: Write dependabot.yml**

```yaml
version: 2
updates:
    - package-ecosystem: composer
      directory: /
      schedule:
          interval: weekly
    - package-ecosystem: github-actions
      directory: /
      schedule:
          interval: weekly
```

- [ ] **Step 3: Commit**

```bash
git add .github
git commit -m "ci: add github actions matrix"
```

---

## Task 12: README + CHANGELOG + UPGRADE-2.0

**Files:**
- Create: `README.md`
- Create: `CHANGELOG.md`
- Create: `UPGRADE-2.0.md`

- [ ] **Step 1: Write README.md**

```markdown
# laravel-modules-core

Shared bootstrap kit for [KurtModules](https://github.com/ozankurt) Laravel packages.

## Requirements

- PHP 8.4+
- Laravel 12.x or 13.x
- (Optional) Filament 3, 4, or 5

## Installation

```bash
composer require ozankurt/laravel-modules-core
```

## What it provides

- `Kurt\Modules\Core\Providers\PackageServiceProvider` — abstract base every kurtmodules service provider extends. Wraps `spatie/laravel-package-tools` and dispatches to `registerFilamentV{3,4,5}` based on the installed Filament major.
- `Kurt\Modules\Core\Contracts\UserResolver` (+ `ConfigUserResolver`) — resolves the consumer's user model via `kurtmodules.user_model` config or `auth.providers.users.model` fallback.
- `Kurt\Modules\Core\Concerns\ResolvesUser` — trait that gives module models a `userBelongsTo()` helper.
- `Kurt\Modules\Core\Concerns\InteractsWithModuleConfig` — sugar for `config("{module}.key")` access.
- `Kurt\Modules\Core\Support\FilamentVersion` — `::major()`, `::isAtLeast()`, `::isExactly()`.
- `Kurt\Modules\Core\Enums\{Approval,MediaKind,Visibility}` — generic cross-module enums.
- `Kurt\Modules\Core\Testing\PackageTestCase` — Testbench-backed base test case with an in-memory `users` table.

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag="kurtmodules-config"
```

```php
return [
    'user_model' => env('KURTMODULES_USER_MODEL'),
    'date_format' => 'Y-m-d H:i:s',
];
```

## License

MIT © Ozan Kurt
```

- [ ] **Step 2: Write CHANGELOG.md**

```markdown
# Changelog

All notable changes follow [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and [SemVer](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.0] - 2026-05-28

### Added
- `PackageServiceProvider` abstract base wrapping `spatie/laravel-package-tools` with Filament major-version dispatch.
- `UserResolver` contract + `ConfigUserResolver` default implementation.
- `ResolvesUser` and `InteractsWithModuleConfig` traits.
- `FilamentVersion` detector.
- `Approval`, `MediaKind`, `Visibility` enums.
- `PackageTestCase` Testbench-backed test base.
- GitHub Actions matrix CI (PHP 8.4 × Laravel 12/13).

### Removed
- Everything from v0.x — see UPGRADE-2.0.md.
```

- [ ] **Step 3: Write UPGRADE-2.0.md**

```markdown
# Upgrade Guide — v0.x → v2.0

The 2.0 line is a clean rebuild. Backwards compatibility is **not** preserved.

## Composer rename

```diff
-"ozankurt/modules-core": "^0.4"
+"ozankurt/laravel-modules-core": "^2.0"
```

Update `composer.json`, run `composer update`.

## Config rename

`config('kurt_modules.*')` is gone. The new root key is `kurtmodules`:

```diff
-config('kurt_modules.user_model')
+config('kurtmodules.user_model')
```

## Removed traits

| v0.x trait | Replacement |
|---|---|
| `Kurt\Modules\Core\Traits\GetUserModelData` | `Kurt\Modules\Core\Concerns\ResolvesUser` (trait) + `Kurt\Modules\Core\Contracts\UserResolver` (service) |
| `Kurt\Modules\Core\Traits\GetCountFromRelation` | Use Laravel `withCount()` / `loadCount()`. |
| `Kurt\Modules\Core\Traits\HasLinks` | Removed — Blog-only concern. Replaced by Blog v2's `Support\RouteBuilder` (only when needed). |

## Removed utility

`Kurt\Modules\Core\Links` was a string-template URL builder for Blog v1. Removed. Blog v2 ships its own `RouteBuilder` (opt-in).

## New requirement

PHP 8.4 is now the minimum. Laravel 12 or 13 only.
```

- [ ] **Step 4: Commit**

```bash
git add README.md CHANGELOG.md UPGRADE-2.0.md
git commit -m "docs: add README, CHANGELOG, UPGRADE-2.0"
```

---

## Task 13: Final verification + push + tag

- [ ] **Step 1: Run the entire CI script locally**

Run sequentially:
```bash
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=2G
vendor/bin/pest --coverage --min=80
```
Expected: all three green.

- [ ] **Step 2: Push branch**

```bash
git push -u origin v2.0
```

- [ ] **Step 3: Open PR**

```bash
gh pr create --title "v2.0: rebuild as laravel-modules-core" --body "$(cat <<'EOF'
## Summary

- Renames vendor from ozankurt/modules-core to ozankurt/laravel-modules-core.
- Targets PHP 8.4 + Laravel 12/13 + optional Filament 3/4/5.
- Replaces v0.x trait-only API with PackageServiceProvider base, UserResolver contract, FilamentVersion detector, Pest + Testbench test base.
- See UPGRADE-2.0.md for v0.x → v2.0 migration.

## Test plan
- [x] vendor/bin/pint --test
- [x] vendor/bin/phpstan analyse
- [x] vendor/bin/pest --coverage --min=80
EOF
)"
```

- [ ] **Step 4: Wait for CI green, then merge**

Pause here — wait for the user to review the PR and approve the merge. **Do not auto-merge.**

- [ ] **Step 5: Tag v2.0.0**

After merge:
```bash
git switch master
git pull
git tag -a v2.0.0 -m "v2.0.0"
git push origin v2.0.0
gh release create v2.0.0 --title "v2.0.0" --notes-file CHANGELOG.md
```

---

## Definition of done

- [ ] `vendor/bin/pint --test` clean.
- [ ] `vendor/bin/phpstan analyse` level 8 clean.
- [ ] `vendor/bin/pest --coverage --min=80` passes.
- [ ] CI matrix green.
- [ ] README, CHANGELOG, UPGRADE-2.0 populated.
- [ ] Tag `v2.0.0` pushed; GitHub release created.
- [ ] [Blog plan](2026-05-28-kurtmodules-blog-v2.md) can now require `ozankurt/laravel-modules-core: ^2.0`.
