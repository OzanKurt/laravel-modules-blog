# `ozankurt/laravel-modules-library` v2.0 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build `ozankurt/laravel-modules-library` v2.0.0 — a SaaS **resource library** module (nested folders, mixed item kinds: video link / file / document / external URL, per-folder Gate-based permissions, versioning, Filament admin). Greenfield package.

**Architecture:** Same scaffold as Core (Spatie's `PackageServiceProvider`, Pest 3, PHPStan 8, Pint, GH Actions). All ACL logic lives in `Access\LibraryAccess` which resolves capabilities via a consumer-supplied `LibrarySubjectResolver`. Folder tree uses a denormalised `path` column for fast ancestry queries plus a `moveTo()` helper that rewrites paths in one query.

**Tech Stack:** PHP 8.4, Laravel 12/13, Filament 3/4/5, Pest 3, Testbench, spatie/laravel-medialibrary, spatie/laravel-translatable, cviebrock/eloquent-sluggable.

**Spec:** [2026-05-28-kurtmodules-library-v2-spec.md](../specs/2026-05-28-kurtmodules-library-v2-spec.md)

**Prerequisite:** `ozankurt/laravel-modules-core` v2.0.0 reachable (path repo OK during development).

**Working directory:** `D:\Code\Projects\KurtModules-Library` for ALL tasks.

---

## Task 0: Repo prep

- [ ] **Step 1: Create v2 branch from master**

```bash
cd D:/Code/Projects/KurtModules-Library
git fetch --all --prune
git switch master
git pull
git switch -c v2.0
```

(The repo only has `SECURITY.md` — nothing to remove.)

---

## Task 1: composer.json

```json
{
  "name": "ozankurt/laravel-modules-library",
  "description": "SaaS resource library: nested folders with per-folder permissions, versioned items (video link, file, document, URL).",
  "keywords": ["laravel", "filament", "kurtmodules", "library", "resources", "knowledge-base"],
  "license": "MIT",
  "authors": [{ "name": "Ozan Kurt", "email": "ozankurt2@gmail.com" }],
  "require": {
    "php": "^8.4",
    "illuminate/contracts": "^12.0 || ^13.0",
    "illuminate/database": "^12.0 || ^13.0",
    "illuminate/support": "^12.0 || ^13.0",
    "cviebrock/eloquent-sluggable": "^11.0 || ^12.0",
    "ozankurt/laravel-modules-core": "^2.0",
    "spatie/laravel-medialibrary": "^11.0",
    "spatie/laravel-package-tools": "^1.92",
    "spatie/laravel-translatable": "^6.11"
  },
  "require-dev": {
    "filament/filament": "^3.0 || ^4.0 || ^5.0",
    "larastan/larastan": "^3.0",
    "laravel/pint": "^1.18",
    "orchestra/testbench": "^10.0",
    "pestphp/pest": "^3.0",
    "pestphp/pest-plugin-laravel": "^3.0",
    "rector/rector": "^2.0"
  },
  "autoload": { "psr-4": { "Kurt\\Modules\\Library\\": "src/" } },
  "autoload-dev": { "psr-4": { "Kurt\\Modules\\Library\\Tests\\": "tests/" } },
  "extra": { "laravel": { "providers": ["Kurt\\Modules\\Library\\Providers\\LibraryServiceProvider"] } },
  "config": { "sort-packages": true, "allow-plugins": { "pestphp/pest-plugin": true } },
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

Commit:
```bash
git add composer.json composer.lock
git commit -m "feat: composer scaffold for laravel-modules-library v2"
```

---

## Task 2: Dev configs

Copy from `D:\Code\Projects\KurtModules-Core`: `pint.json`, `phpstan.neon`, `rector.php`, `.gitattributes`, `.gitignore`.

Commit:
```bash
git add pint.json phpstan.neon rector.php .gitattributes .gitignore
git commit -m "chore: add pint, phpstan, rector configs"
```

---

## Task 3: Config

`config/library.php`:

```php
<?php

declare(strict_types=1);

use Kurt\Modules\Library\Access\DefaultSubjectResolver;
use Kurt\Modules\Library\Models\AccessLog;
use Kurt\Modules\Library\Models\Folder;
use Kurt\Modules\Library\Models\FolderPermission;
use Kurt\Modules\Library\Models\Item;
use Kurt\Modules\Library\Models\ItemVersion;
use Kurt\Modules\Library\Models\Tag;

return [
    'media' => [
        'disk' => env('LIBRARY_MEDIA_DISK', 'public'),
        'allowed_mimes' => ['*'],
        'max_size_kb' => 100_000,
        'conversions' => [
            'thumb' => [320, 320],
        ],
    ],
    'versions' => [
        'keep_old' => 10,
    ],
    'subject_resolver' => DefaultSubjectResolver::class,
    'access_log' => [
        'enabled' => true,
        'on_view' => false,
    ],
    'video_link_providers' => ['youtube', 'vimeo', 'dailymotion', 'loom'],
    'models' => [
        'folder' => Folder::class,
        'folder_permission' => FolderPermission::class,
        'item' => Item::class,
        'item_version' => ItemVersion::class,
        'tag' => Tag::class,
        'access_log' => AccessLog::class,
    ],
];
```

Commit:
```bash
git add config/library.php
git commit -m "feat(library): add config file"
```

---

## Task 4: Enums

Files: `src/Enums/{FolderVisibility,ItemKind,PermissionSubjectType,Capability,AccessAction}.php` and matching unit tests.

```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Library\Enums;

enum FolderVisibility: string
{
    case Public = 'public';
    case Restricted = 'restricted';
    case Private = 'private';
}
```

```php
enum ItemKind: string
{
    case VideoLink = 'video_link';
    case File = 'file';
    case Document = 'document';
    case ExternalUrl = 'external_url';
}
```

```php
enum PermissionSubjectType: string
{
    case User = 'user';
    case Role = 'role';
    case Everyone = 'everyone';
}
```

```php
enum Capability: string
{
    case View = 'view';
    case Download = 'download';
    case Manage = 'manage';

    public function rank(): int
    {
        return match ($this) {
            self::View => 1,
            self::Download => 2,
            self::Manage => 3,
        };
    }
}
```

```php
enum AccessAction: string
{
    case View = 'view';
    case Download = 'download';
}
```

Tests: one trivial cases-and-values test per enum, plus a test for `Capability::rank()` ordering (View < Download < Manage).

Commit:
```bash
git add src/Enums tests/Unit/Enums
git commit -m "feat(library): add FolderVisibility, ItemKind, PermissionSubjectType, Capability, AccessAction enums"
```

---

## Task 5: Subject + resolver

**Files:**
- `src/Values/Subject.php`
- `src/Contracts/LibrarySubject.php`
- `src/Contracts/LibrarySubjectResolver.php`
- `src/Access/DefaultSubjectResolver.php`
- `tests/Unit/Access/DefaultSubjectResolverTest.php`

`src/Values/Subject.php`:
```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Library\Values;

use Kurt\Modules\Library\Enums\PermissionSubjectType;

final readonly class Subject
{
    public function __construct(
        public PermissionSubjectType $type,
        public ?string $value,
    ) {}

    public function matches(string $rowType, ?string $rowValue): bool
    {
        if ($this->type->value !== $rowType) {
            return false;
        }

        if ($this->type === PermissionSubjectType::Everyone) {
            return true;
        }

        return $this->value === $rowValue;
    }
}
```

`src/Contracts/LibrarySubject.php`:
```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Library\Contracts;

interface LibrarySubject
{
    public function getKey(): int|string;

    public function getLibrarySubjectDisplayName(): string;
}
```

`src/Contracts/LibrarySubjectResolver.php`:
```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Library\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Kurt\Modules\Library\Values\Subject;

interface LibrarySubjectResolver
{
    /** @return array<int, Subject> */
    public function subjects(?Authenticatable $user): array;
}
```

`src/Access/DefaultSubjectResolver.php`:
```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Library\Access;

use Illuminate\Contracts\Auth\Authenticatable;
use Kurt\Modules\Library\Contracts\LibrarySubjectResolver;
use Kurt\Modules\Library\Enums\PermissionSubjectType;
use Kurt\Modules\Library\Values\Subject;

final class DefaultSubjectResolver implements LibrarySubjectResolver
{
    public function subjects(?Authenticatable $user): array
    {
        $subjects = [new Subject(PermissionSubjectType::Everyone, null)];

        if ($user !== null) {
            $subjects[] = new Subject(PermissionSubjectType::User, (string) $user->getAuthIdentifier());
        }

        return $subjects;
    }
}
```

Unit test:
```php
<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Kurt\Modules\Library\Access\DefaultSubjectResolver;
use Kurt\Modules\Library\Enums\PermissionSubjectType;

it('returns Everyone subject only when user is null', function () {
    $subjects = (new DefaultSubjectResolver())->subjects(null);

    expect($subjects)->toHaveCount(1);
    expect($subjects[0]->type)->toBe(PermissionSubjectType::Everyone);
});

it('returns Everyone + User subjects when user is supplied', function () {
    $user = Mockery::mock(Authenticatable::class);
    $user->shouldReceive('getAuthIdentifier')->andReturn(42);

    $subjects = (new DefaultSubjectResolver())->subjects($user);

    expect($subjects)->toHaveCount(2);
    expect($subjects[1]->type)->toBe(PermissionSubjectType::User);
    expect($subjects[1]->value)->toBe('42');
});
```

Add `mockery/mockery` to composer require-dev if needed (it ships with orchestra/testbench so should be auto-installed).

Commit:
```bash
git add src/Values src/Contracts src/Access/DefaultSubjectResolver.php tests/Unit/Access
git commit -m "feat(library): add Subject value + LibrarySubjectResolver + DefaultSubjectResolver"
```

---

## Task 6: Migrations

Seven anonymous migrations under `database/migrations/`. Filenames:

- `2026_05_28_030001_create_library_folders_table.php`
- `2026_05_28_030002_create_library_item_versions_table.php`  ← **before items** because items FK to versions
- `2026_05_28_030003_create_library_items_table.php`
- `2026_05_28_030004_create_library_tags_table.php`
- `2026_05_28_030005_create_library_item_tag_table.php`
- `2026_05_28_030006_create_library_folder_permissions_table.php`
- `2026_05_28_030007_create_library_access_log_table.php`

### library_folders

```php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('library_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('library_folders')->restrictOnDelete();
            $table->string('slug');
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('path')->index();
            $table->unsignedInteger('depth')->default(0);
            $table->unsignedInteger('position')->default(0);
            $table->string('visibility')->default('public');
            $table->foreignId('owner_id')->constrained(config('auth.providers.users.table', 'users'))->restrictOnDelete();
            $table->unsignedBigInteger('item_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['parent_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_folders');
    }
};
```

### library_item_versions (note: created before items)

```php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('library_item_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id'); // FK added later (after items table exists)
            $table->unsignedInteger('version');
            $table->string('external_url')->nullable();
            $table->string('media_path')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('byte_size')->nullable();
            $table->string('checksum')->nullable();
            $table->text('changelog')->nullable();
            $table->foreignId('created_by')->constrained(config('auth.providers.users.table', 'users'))->restrictOnDelete();
            $table->timestamps();
            $table->unique(['item_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_item_versions');
    }
};
```

### library_items

```php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('library_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->constrained('library_folders')->cascadeOnDelete();
            $table->string('slug');
            $table->json('title');
            $table->json('description')->nullable();
            $table->string('kind');
            $table->foreignId('owner_id')->constrained(config('auth.providers.users.table', 'users'))->restrictOnDelete();
            $table->foreignId('current_version_id')->nullable()->constrained('library_item_versions')->nullOnDelete();
            $table->string('external_url')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('byte_size')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->unsignedBigInteger('download_count')->default(0);
            $table->unsignedBigInteger('view_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['folder_id', 'slug']);
            $table->index('kind');
        });

        Schema::table('library_item_versions', function (Blueprint $table) {
            $table->foreign('item_id')->references('id')->on('library_items')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('library_item_versions', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
        });
        Schema::dropIfExists('library_items');
    }
};
```

### library_tags

```php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('library_tags', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->json('name');
            $table->string('color')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_tags');
    }
};
```

### library_item_tag

```php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('library_item_tag', function (Blueprint $table) {
            $table->foreignId('item_id')->constrained('library_items')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('library_tags')->cascadeOnDelete();
            $table->primary(['item_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_item_tag');
    }
};
```

### library_folder_permissions

```php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('library_folder_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->constrained('library_folders')->cascadeOnDelete();
            $table->string('subject_type');
            $table->string('subject_value')->nullable();
            $table->string('capability');
            $table->boolean('cascade')->default(true);
            $table->timestamps();
            $table->index(['folder_id', 'subject_type', 'subject_value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_folder_permissions');
    }
};
```

### library_access_log

```php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('library_access_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('library_items')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained(config('auth.providers.users.table', 'users'))->nullOnDelete();
            $table->string('action');
            $table->string('ip')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_access_log');
    }
};
```

Commit:
```bash
git add database/migrations
git commit -m "feat(library): add migrations for folders, items, versions, tags, permissions, access log"
```

---

## Task 7: Models + factories

Create:

- `src/Models/Folder.php` — `HasTranslations` (`name`, `description`), `Sluggable` on `slug`. `parent()`, `children()`, `items()`, `permissions()` relations. Methods: `path()`, `descendants()`, `ancestors()`, `moveTo(?Folder)` (rewrites `path`/`depth` for subtree in one query via `update(['path' => DB::raw(...)])`). Scopes: `roots()`, `visibleTo(User)`.
- `src/Models/Item.php` — `HasTranslations` (`title`, `description`), `Sluggable`, `HasMedia`. Relations: `folder()`, `tags()`, `versions()`, `currentVersion()`, `owner()`. Methods: `publish()`, `unpublish()`, `newVersion(array $payload, User $by): ItemVersion`, `recordAccess(?User, AccessAction)`. Cast `kind` to `ItemKind` enum.
- `src/Models/ItemVersion.php` — relations: `item()`, `creator()`. `$fillable = ['item_id','version','external_url','media_path','mime_type','byte_size','checksum','changelog','created_by']`.
- `src/Models/Tag.php` — `HasTranslations` (`name`), slug. `items()` belongsToMany.
- `src/Models/FolderPermission.php` — relation `folder()`. `cast subject_type` to `PermissionSubjectType`, `capability` to `Capability`.
- `src/Models/AccessLog.php` — relation `item()`, `user()`. `cast action` to `AccessAction`, `occurred_at` to datetime.

For each, write a matching `*Factory.php` under `database/factories/`. Tests in `tests/Feature/Models/` covering relationships and key scopes.

`Folder::moveTo()` algorithm:
```php
public function moveTo(?Folder $newParent): self
{
    DB::transaction(function () use ($newParent) {
        $oldPath = $this->path;
        $newParentPath = $newParent?->path ?? '';
        $newPath = $newParentPath.'/'.$this->slug;
        $depthDelta = ($newParent?->depth ?? -1) + 1 - $this->depth;

        $this->forceFill([
            'parent_id' => $newParent?->id,
            'path' => $newPath,
            'depth' => $this->depth + $depthDelta,
        ])->save();

        // Update descendant paths in one query.
        static::query()
            ->where('path', 'like', $oldPath.'/%')
            ->update([
                'path' => DB::raw("REPLACE(path, '{$oldPath}', '{$newPath}')"),
                'depth' => DB::raw("depth + ({$depthDelta})"),
            ]);
    });

    return $this->fresh();
}
```

(Note: SQLite supports `REPLACE()` in updates. On MySQL/Postgres the same syntax works. Be careful with double-quoting if `$oldPath` contains apostrophes — Folder slugs are slugged, so they don't. But for safety, escape.)

Commit:
```bash
git add src/Models database/factories
git commit -m "feat(library): add Folder, Item, ItemVersion, Tag, FolderPermission, AccessLog models with factories"
```

---

## Task 8: Access service (the meat)

**Files:**
- `src/Access/PermissionResolver.php`
- `src/Access/LibraryAccess.php`
- `tests/Feature/Access/LibraryAccessTest.php` — exhaustive matrix

`src/Access/PermissionResolver.php`:
```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Library\Access;

use Illuminate\Contracts\Auth\Authenticatable;
use Kurt\Modules\Library\Contracts\LibrarySubjectResolver;
use Kurt\Modules\Library\Enums\Capability;
use Kurt\Modules\Library\Enums\FolderVisibility;
use Kurt\Modules\Library\Models\Folder;
use Kurt\Modules\Library\Models\FolderPermission;
use Kurt\Modules\Library\Values\Subject;

final class PermissionResolver
{
    public function __construct(private readonly LibrarySubjectResolver $subjectResolver) {}

    public function highestCapability(?Authenticatable $user, Folder $folder): ?Capability
    {
        $subjects = $this->subjectResolver->subjects($user);

        // Walk ancestry from self upward.
        foreach ($this->ancestry($folder) as $ancestor) {
            $best = $this->matchOn($ancestor, $subjects, allowCascadeOnly: $ancestor->id !== $folder->id);
            if ($best !== null) {
                return $best;
            }
        }

        // Fallback to visibility.
        return match ($folder->visibility) {
            FolderVisibility::Public => Capability::Download,
            FolderVisibility::Restricted => null,
            FolderVisibility::Private => $user !== null && $folder->owner_id === $user->getAuthIdentifier()
                ? Capability::Manage
                : null,
        };
    }

    /** @return iterable<Folder> */
    private function ancestry(Folder $folder): iterable
    {
        $current = $folder;
        while ($current !== null) {
            yield $current;
            $current = $current->parent;
        }
    }

    /** @param array<int, Subject> $subjects */
    private function matchOn(Folder $folder, array $subjects, bool $allowCascadeOnly): ?Capability
    {
        /** @var FolderPermission[] $rows */
        $rows = $folder->permissions()
            ->when($allowCascadeOnly, fn ($q) => $q->where('cascade', true))
            ->get()
            ->all();

        $best = null;
        foreach ($rows as $row) {
            foreach ($subjects as $subject) {
                if ($subject->matches($row->subject_type->value, $row->subject_value)) {
                    if ($best === null || $row->capability->rank() > $best->rank()) {
                        $best = $row->capability;
                    }
                }
            }
        }

        return $best;
    }
}
```

`src/Access/LibraryAccess.php`:
```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Library\Access;

use Illuminate\Contracts\Auth\Authenticatable;
use Kurt\Modules\Library\Enums\Capability;
use Kurt\Modules\Library\Models\Folder;
use Kurt\Modules\Library\Models\Item;

final class LibraryAccess
{
    /** @var array<string, ?Capability> */
    private array $cache = [];

    public function __construct(private readonly PermissionResolver $resolver) {}

    public function check(?Authenticatable $user, Folder|Item $target, Capability $needed): bool
    {
        $folder = $target instanceof Item ? $target->folder : $target;
        $key = sprintf('%s:%d', $user?->getAuthIdentifier() ?? 'guest', $folder->id);

        $best = $this->cache[$key] ??= $this->resolver->highestCapability($user, $folder);

        return $best !== null && $best->rank() >= $needed->rank();
    }

    public function flush(): void
    {
        $this->cache = [];
    }
}
```

Test matrix should cover: cascade on/off × ancestor wins × visibility fallback × owner of private folder × user vs everyone subject types.

Commit:
```bash
git add src/Access/PermissionResolver.php src/Access/LibraryAccess.php tests/Feature/Access
git commit -m "feat(library): add PermissionResolver + LibraryAccess with exhaustive ACL tests"
```

---

## Task 9: Events + observers + policies

Events (one class per file under `src/Events/`): same pattern as Blog.

Observers (`src/Observers/`):
- `FolderObserver` — recomputes `path`/`depth` on create (if not set), dispatches events.
- `ItemObserver` — dispatches `ItemCreated/Updated/Published/Unpublished/Deleted`. Decrements `Folder.item_count` on delete, increments on create.
- `ItemVersionObserver` — dispatches `ItemVersionCreated`.

Policies (`src/Policies/`):
- `FolderPolicy::view/download/manage` → delegate to `LibraryAccess::check`.
- `ItemPolicy` same.
- Global `canAdminLibrary` gate bypasses everything.

Commit:
```bash
git add src/Events src/Observers src/Policies
git commit -m "feat(library): add events, observers, policies"
```

---

## Task 10: Console commands

- `library:recount` — rebuilds `Folder.item_count`, `Item.view_count`, `Item.download_count` from raw rows.
- `library:prune-versions` — for each item, keep newest N (config) + current_version; delete older.
- `library:rebuild-paths` — recompute `path` + `depth` for every folder by walking tree.
- `library:demo` — seed folders + sample items via factories.

Commit:
```bash
git add src/Console
git commit -m "feat(library): add recount, prune-versions, rebuild-paths, demo commands"
```

---

## Task 11: Service provider + Filament provider

`src/Providers/LibraryServiceProvider.php`:

```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Library\Providers;

use Kurt\Modules\Library\Access\LibraryAccess;
use Kurt\Modules\Library\Access\PermissionResolver;
use Kurt\Modules\Library\Contracts\LibrarySubjectResolver;
use Kurt\Modules\Library\Models\Folder;
use Kurt\Modules\Library\Models\Item;
use Kurt\Modules\Library\Models\ItemVersion;
use Kurt\Modules\Library\Observers\FolderObserver;
use Kurt\Modules\Library\Observers\ItemObserver;
use Kurt\Modules\Library\Observers\ItemVersionObserver;
use Kurt\Modules\Library\Policies\FolderPolicy;
use Kurt\Modules\Library\Policies\ItemPolicy;
use Kurt\Modules\Library\Console\Commands\{RecountCommand, PruneVersionsCommand, RebuildPathsCommand, DemoCommand};
use Kurt\Modules\Core\Providers\PackageServiceProvider;
use Spatie\LaravelPackageTools\Package;

final class LibraryServiceProvider extends PackageServiceProvider
{
    protected function module(): string
    {
        return 'library';
    }

    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-modules-library')
            ->hasConfigFile('library')
            ->hasTranslations()
            ->hasMigrations([
                'create_library_folders_table',
                'create_library_item_versions_table',
                'create_library_items_table',
                'create_library_tags_table',
                'create_library_item_tag_table',
                'create_library_folder_permissions_table',
                'create_library_access_log_table',
            ])
            ->hasCommands([
                RecountCommand::class,
                PruneVersionsCommand::class,
                RebuildPathsCommand::class,
                DemoCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(LibrarySubjectResolver::class, fn () => $this->app->make(
            (string) config('library.subject_resolver'),
        ));

        $this->app->singleton(PermissionResolver::class);
        $this->app->scoped(LibraryAccess::class);
    }

    public function packageBooted(): void
    {
        Folder::observe(FolderObserver::class);
        Item::observe(ItemObserver::class);
        ItemVersion::observe(ItemVersionObserver::class);

        $gate = $this->app['Illuminate\Contracts\Auth\Access\Gate'];
        $gate->policy(Folder::class, FolderPolicy::class);
        $gate->policy(Item::class, ItemPolicy::class);
    }
}
```

`src/Providers/LibraryFilamentServiceProvider.php` mirrors Blog's: detect Filament major, expose `library.filament.resources` array of FQCNs.

Commit:
```bash
git add src/Providers
git commit -m "feat(library): add LibraryServiceProvider + LibraryFilamentServiceProvider"
```

---

## Task 12: Filament resources

V3 / V4 / V5 parallel namespaces, each with:
- `FolderResource` — tree-aware listing + ACL relation manager.
- `ItemResource` — kind filter + version relation manager + per-row preview action.
- `TagResource`.
- `AccessLogResource` — read-only.

When working on V5 specifically, load `epic-skills:filament-v5` skill. For V3/V4, use Filament's official docs via context7.

Smoke test per version: `Livewire::test(...)->assertOk()` on list + create + edit pages.

Commit progressively per version:
```bash
git add src/Filament/V3
git commit -m "feat(library): add Filament v3 resources"

git add src/Filament/V4
git commit -m "feat(library): add Filament v4 resources"

git add src/Filament/V5
git commit -m "feat(library): add Filament v5 resources"
```

---

## Task 13: Test base + feature tests

`tests/TestCase.php` extends `PackageTestCase`, registers `LibraryServiceProvider`, loads migrations.

`tests/Pest.php` applies TestCase to `Feature`.

Feature tests cover (per spec §14):
- Folder tree create + move (subtree path rewrite).
- Item kinds: each `ItemKind` creates correctly.
- Versioning: `newVersion()` increments + updates `current_version_id`.
- ACL matrix (already in Task 8 — broaden here with real DB).
- AccessLog: download always logged, view only when configured.
- Recount: corrupt then run `library:recount`, verify rebuilt.

Commit:
```bash
git add tests
git commit -m "feat(library): add test base + feature tests"
```

---

## Task 14: CI + docs

Copy `.github/workflows/tests.yml` from Core (with the corrected Laravel-12 + testbench-10 mapping). Add Filament matrix axis.

Adapt README/CHANGELOG/UPGRADE-2.0 (this is "Initial release" so the upgrade file is short).

Commit:
```bash
git add .github README.md CHANGELOG.md UPGRADE-2.0.md
git commit -m "ci+docs: add github actions, README, CHANGELOG, UPGRADE-2.0"
```

---

## Task 15: Push + PR

```bash
git push -u origin v2.0
gh pr create --title "v2.0: initial release of ozankurt/laravel-modules-library" --body ...
```

After merge + tag:
```bash
git tag v2.0.0
git push origin v2.0.0
gh release create v2.0.0 --title "v2.0.0" --notes-file CHANGELOG.md
```

---

## Definition of done

- [ ] All migrations + tests run on SQLite in-memory.
- [ ] Pint + PHPStan + Pest all green.
- [ ] CI matrix green.
- [ ] Folder `moveTo()` rewrites a 100-descendant subtree in one query (test asserts query count ≤ 3).
- [ ] ACL matrix tests cover every subject × cascade × visibility combination.
- [ ] `library:prune-versions` keeps newest N + never the current.
- [ ] Filament resources V3/V4/V5 render.
- [ ] Tagged `v2.0.0`.
