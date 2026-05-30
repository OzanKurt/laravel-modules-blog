# `ozankurt/laravel-modules-media-library` v1.0 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build `ozankurt/laravel-modules-media-library` v1.0.0 — WordPress-style media bucket for Laravel SaaS. Headless backend wrapping spatie/laravel-medialibrary as the storage engine, with nested folders, polymorphic owner + many-to-many attachments, focal-point conversions, replace-with-stable-id versioning, share links, folder ACL, tag taxonomy, pluggable extractors.

**Architecture:** Five-area layout (Catalog / Storage / Sharing / Access / Search) plus cross-cutting Contracts/Events/Notifications/Policies. Top-level `MediaLibrary` facade orchestrates the workflows. Each MediaLibraryItem owns one thin `MediaLibraryStorage` host (a `HasMedia` model with a single `mli` collection) which in turn owns the actual spatie Media row. Polymorphic attachments link consumer models to MediaLibraryItem ids (stable across replaces).

**Tech Stack:** PHP 8.4, Laravel 12, Pest 3 + Testbench, PHPStan 8, Pint, Rector. Runtime deps: ozankurt/laravel-modules-core ^2.0, spatie/laravel-medialibrary ^11, spatie/laravel-translatable ^6.11, cviebrock/eloquent-sluggable ^11|^12, intervention/image ^3, spatie/laravel-package-tools ^1.92.

**Spec:** [2026-05-30-kurtmodules-media-library-v1-spec.md](../specs/2026-05-30-kurtmodules-media-library-v1-spec.md)

**Working directory:** `D:\Code\Projects\KurtModules-MediaLibrary`. Repo does NOT yet exist on GitHub; Task 0 creates it.

**Defer to v1.1:** Filament v3/v4/v5 resources, Scout default adapter, cross-module audit log promotion.

---

## File structure

```
src/
  Catalog/
    Enums/{Visibility, ItemKind, AttachmentRole}.php
    Models/{MediaLibraryItem, MediaLibraryFolder, MediaLibraryTag, MediaLibraryAttachment, MediaLibrarySavedSearch, MediaLibraryStorage}.php
    Observers/{MediaLibraryItemObserver, MediaLibraryFolderObserver}.php
    Support/{FolderPathBuilder, ItemSlugger}.php
  Storage/
    Enums/PendingUploadStatus.php
    Models/{MediaLibraryVersion, MediaLibraryVariant, MediaLibraryPendingUpload}.php
    Support/{UploadCoordinator, ConversionEngine, VariantGenerator, FocalPointCropper, ReplaceCoordinator, MetadataExtractor}.php
    Contracts/{ExifExtractor, OcrExtractor, AiTagger, BlurhashGenerator, PaletteExtractor}.php
    Extractors/{DefaultExifExtractor, InterventionBlurhashGenerator, InterventionPaletteExtractor}.php
  Sharing/
    Enums/{ShareAbility, AccessAction}.php
    Models/{ShareLink, AccessLogEntry}.php
    Support/{ShareLinkSigner, ShareLinkResolver, AccessLogger}.php
    Http/Controllers/ShareLinkController.php
  Access/
    Enums/{Capability, SubjectType}.php
    Models/FolderPermission.php
    Contracts/MediaSubjectResolver.php
    Support/{DefaultSubjectResolver, FolderPermissionResolver, MediaLibraryAccess}.php
    Values/Subject.php
  Search/
    Contracts/ScoutAdapter.php
    Support/DefaultSearchEngine.php
  Concerns/
    HasMediaLibraryItems.php
    IsMediaLibraryOwner.php
  Contracts/MediaLibraryOwner.php
  Console/Commands/{PruneVersions, PruneVariants, RebuildPaths, Recount, ExpireShares, PruneShares, ExpirePendingUploads, Reextract, Reindex, Demo}Command.php
  Events/...
  Exceptions/{OwnerNotResolved, InvalidUpload, ReplaceFailed, ShareLinkInvalid, SelfReferentialFolder}.php
  Notifications/{ShareLinkCreated, ShareLinkAccessed, ItemReplaced, LargeUploadCompleted}.php
  Policies/{MediaLibraryItemPolicy, MediaLibraryFolderPolicy, ShareLinkPolicy, SavedSearchPolicy}.php
  Providers/MediaLibraryServiceProvider.php
  Support/MediaLibrary.php
  Facades/MediaLibrary.php
routes/share.php
config/media-library.php
resources/views/notifications/*.blade.php
database/factories/...
database/migrations/...
tests/Pest.php
tests/TestCase.php
tests/Stubs/StubUser.php
tests/migrations/2026_05_28_000010_create_media_table.php
.github/workflows/tests.yml
.github/dependabot.yml
phpstan.neon
pint.json
rector.php
phpunit.xml.dist
.gitattributes
.gitignore
README.md
CHANGELOG.md
UPGRADE-1.0.md
LICENSE.md
SECURITY.md
composer.json
```

---

## Task 0: GitHub repo + local scaffold

- [ ] **Step 1: Create GitHub repo + clone**

```bash
gh repo create OzanKurt/KurtModules-MediaLibrary --public --description "WordPress-style media bucket for Laravel SaaS: tenant-aware folders, polymorphic attachments, focal-point conversions, replace-with-stable-id, share links, folder ACL." --license=mit
cd D:/Code/Projects
git clone https://github.com/OzanKurt/KurtModules-MediaLibrary.git
cd KurtModules-MediaLibrary
git branch -m main master  # match family convention
git push -u origin master
gh api -X PATCH 'repos/OzanKurt/KurtModules-MediaLibrary' -f default_branch=master
gh api -X DELETE 'repos/OzanKurt/KurtModules-MediaLibrary/git/refs/heads/main' || true
```

- [ ] **Step 2: Copy SECURITY.md from Core, branch v1.0**

```bash
cp ../KurtModules-Core/SECURITY.md .
git add SECURITY.md
git commit -m "chore: bootstrap repo with SECURITY.md"
git push origin master
git switch -c v1.0
```

---

## Task 1: composer.json

**Files:**
- Create: `composer.json`

- [ ] **Step 1: Write composer.json**

```json
{
  "name": "ozankurt/laravel-modules-media-library",
  "description": "WordPress-style media bucket for Laravel SaaS: tenant-aware folders, polymorphic attachments, focal-point conversions, replace-with-stable-id, share links, folder ACL.",
  "keywords": ["laravel", "filament", "kurtmodules", "media-library", "wordpress"],
  "license": "MIT",
  "authors": [{ "name": "Ozan Kurt", "email": "ozankurt2@gmail.com" }],
  "require": {
    "php": "^8.4",
    "illuminate/contracts": "^12.0 || ^13.0",
    "illuminate/database": "^12.0 || ^13.0",
    "illuminate/support": "^12.0 || ^13.0",
    "cviebrock/eloquent-sluggable": "^11.0 || ^12.0",
    "intervention/image": "^3.0",
    "ozankurt/laravel-modules-core": "^2.0",
    "spatie/laravel-medialibrary": "^11.0",
    "spatie/laravel-package-tools": "^1.92",
    "spatie/laravel-translatable": "^6.11"
  },
  "require-dev": {
    "filament/filament": "^3.0 || ^4.0 || ^5.0",
    "larastan/larastan": "^3.0",
    "laravel/pint": "^1.18",
    "mockery/mockery": "^1.6",
    "orchestra/testbench": "^10.0",
    "pestphp/pest": "^3.0",
    "pestphp/pest-plugin-laravel": "^3.0",
    "rector/rector": "^2.0"
  },
  "autoload": { "psr-4": { "Kurt\\Modules\\MediaLibrary\\": "src/" } },
  "autoload-dev": {
    "psr-4": {
      "Kurt\\Modules\\MediaLibrary\\Tests\\": "tests/",
      "Database\\Factories\\Kurt\\Modules\\MediaLibrary\\": "database/factories/"
    }
  },
  "extra": { "laravel": { "providers": ["Kurt\\Modules\\MediaLibrary\\Providers\\MediaLibraryServiceProvider"] } },
  "repositories": [
    { "type": "vcs", "url": "https://github.com/OzanKurt/KurtModules-Core" }
  ],
  "config": { "sort-packages": true, "allow-plugins": { "pestphp/pest-plugin": true } },
  "scripts": {
    "test": "vendor/bin/pest",
    "lint": "vendor/bin/pint --test",
    "format": "vendor/bin/pint",
    "stan": "vendor/bin/phpstan analyse --memory-limit=2G"
  },
  "minimum-stability": "stable",
  "prefer-stable": true
}
```

- [ ] **Step 2: composer install + commit**

```bash
C:/laragon/bin/php/php-8.4.5-nts-Win32-vs17-x64/php.exe composer.phar install --no-interaction
git add composer.json composer.lock
git commit -m "feat: composer scaffold for laravel-modules-media-library v1"
```

---

## Task 2: Dev configs

**Files:**
- Copy: `pint.json`, `phpstan.neon`, `rector.php`, `.gitattributes`, `.gitignore`, `phpunit.xml.dist` from `../KurtModules-Events/`

- [ ] **Step 1: Copy + commit**

```bash
cp ../KurtModules-Events/pint.json .
cp ../KurtModules-Events/phpstan.neon .
cp ../KurtModules-Events/rector.php .
cp ../KurtModules-Events/.gitattributes .
cp ../KurtModules-Events/.gitignore .
cp ../KurtModules-Events/phpunit.xml.dist .
git add pint.json phpstan.neon rector.php .gitattributes .gitignore phpunit.xml.dist
git commit -m "chore: add pint, phpstan, rector, phpunit configs"
```

---

## Task 3: config/media-library.php

**Files:**
- Create: `config/media-library.php`

- [ ] **Step 1: Write full config**

Use the exact block from spec §19.

- [ ] **Step 2: Commit**

```bash
git add config/media-library.php
git commit -m "feat(media-library): add config file"
```

---

## Task 4: Enums

**Files:**
- Create: `src/Catalog/Enums/{Visibility,ItemKind,AttachmentRole}.php`
- Create: `src/Storage/Enums/PendingUploadStatus.php`
- Create: `src/Sharing/Enums/{ShareAbility,AccessAction}.php`
- Create: `src/Access/Enums/{Capability,SubjectType}.php`
- Create: `tests/Unit/Enums/*Test.php`

TDD per enum. Use exact case names + values from spec §6.

- [ ] **Step 1: Catalog enums**

`src/Catalog/Enums/Visibility.php`:
```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Catalog\Enums;

enum Visibility: string
{
    case Private = 'private';
    case Restricted = 'restricted';
    case Public = 'public';
}
```

Test (`tests/Unit/Enums/Catalog/VisibilityTest.php`):
```php
<?php

declare(strict_types=1);

use Kurt\Modules\MediaLibrary\Catalog\Enums\Visibility;

it('exposes private, restricted, public', function () {
    expect(Visibility::Private->value)->toBe('private');
    expect(Visibility::Restricted->value)->toBe('restricted');
    expect(Visibility::Public->value)->toBe('public');
});
```

Repeat for `ItemKind` (Image/Video/Audio/Document/Archive/Other), `AttachmentRole` (Cover/Social/Gallery/Thumbnail/Attachment/Hero/Logo/Favicon).

- [ ] **Step 2: Storage enums**

`PendingUploadStatus` (Pending/Completed/Cancelled/Expired).

- [ ] **Step 3: Sharing enums**

`ShareAbility` (View/Download), `AccessAction` (View/Download/Upload/Replace/Delete).

- [ ] **Step 4: Access enums**

`Capability` with `rank()` method (View=1, Download=2, Manage=3). `SubjectType` (User/Role/Everyone).

```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Access\Enums;

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

Test the rank ordering as part of the CapabilityTest.

- [ ] **Step 5: Run + commit**

```bash
C:/laragon/bin/php/php-8.4.5-nts-Win32-vs17-x64/php.exe vendor/bin/pest tests/Unit/Enums
git add src/Catalog/Enums src/Storage/Enums src/Sharing/Enums src/Access/Enums tests/Unit/Enums
git commit -m "feat(media-library): add enums across sub-areas"
```

Expected: ~10 enum tests pass.

---

## Task 5: Migrations

**Files:**
- Create: 13 migrations under `database/migrations/` per spec §5.

Order:
1. `2026_05_30_000010_create_media_library_folders_table.php`
2. `2026_05_30_000020_create_media_library_storage_table.php`
3. `2026_05_30_000030_create_media_library_items_table.php`
4. `2026_05_30_000040_create_media_library_tags_table.php`
5. `2026_05_30_000050_create_media_library_item_tag_table.php`
6. `2026_05_30_000060_create_media_library_attachments_table.php`
7. `2026_05_30_000070_create_media_library_saved_searches_table.php`
8. `2026_05_30_000080_create_media_library_versions_table.php`
9. `2026_05_30_000090_create_media_library_variants_table.php`
10. `2026_05_30_000100_create_media_library_pending_uploads_table.php`
11. `2026_05_30_000110_create_media_library_share_links_table.php`
12. `2026_05_30_000120_create_media_library_access_log_table.php`
13. `2026_05_30_000130_create_media_library_folder_permissions_table.php`

Each is `return new class extends Migration { … };` with exact columns from spec §5.

Critical points:
- `media_library_items.storage_id` FK to `media_library_storage` (NOT NULL, cascadeOnDelete).
- `media_library_storage` has `item_uid` (uuid, unique).
- Owner columns: `owner_type` string + `owner_id` unsignedBigInteger; combined into a polymorphic index per the spec.
- All FKs to `users` use `config('auth.providers.users.table', 'users')` with the appropriate delete behavior.
- All domain rows: `softDeletes()` + `timestamps()`.

- [ ] **Step 1: Write all 13 migrations**

Iterate one at a time. Verify each table-schema after creation by writing a single Pest test file `tests/Feature/MigrationsTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('creates every media_library_* table', function () {
    foreach ([
        'media_library_folders', 'media_library_storage', 'media_library_items',
        'media_library_tags', 'media_library_item_tag', 'media_library_attachments',
        'media_library_saved_searches', 'media_library_versions', 'media_library_variants',
        'media_library_pending_uploads', 'media_library_share_links', 'media_library_access_log',
        'media_library_folder_permissions',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue("missing {$table}");
    }
});
```

Add minimal `tests/TestCase.php` so the migration test can run:

```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Tests;

use Kurt\Modules\Core\Providers\CoreServiceProvider;
use Kurt\Modules\Core\Testing\PackageTestCase;

abstract class TestCase extends PackageTestCase
{
    protected function getPackageProviders($app): array
    {
        return [CoreServiceProvider::class];
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
```

`tests/Pest.php`:
```php
<?php

declare(strict_types=1);

use Kurt\Modules\MediaLibrary\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');
```

- [ ] **Step 2: Run + commit**

```bash
C:/laragon/bin/php/php-8.4.5-nts-Win32-vs17-x64/php.exe vendor/bin/pest tests/Feature/MigrationsTest.php
git add database/migrations tests/TestCase.php tests/Pest.php tests/Feature/MigrationsTest.php
git commit -m "feat(media-library): add all v1 migrations"
```

Expected: migrations test asserts 13 tables exist.

---

## Task 6: Catalog models + factories

**Files:**
- Create: `src/Catalog/Models/{MediaLibraryStorage, MediaLibraryFolder, MediaLibraryItem, MediaLibraryTag, MediaLibraryAttachment, MediaLibrarySavedSearch}.php`
- Create: `database/factories/Catalog/<Model>Factory.php` for each
- Create: `tests/Feature/Catalog/<Model>Test.php` for each (CRUD + relations + scopes)

Patterns:
- Translatable models (Folder, Item, Tag) use `HasTranslations` + `public array $translatable = [...]`.
- Sluggable models (Folder, Item, Tag) use `Sluggable` trait + `sluggable()` method.
- All models override `newFactory()` for PSR-4 factory namespace.

### Step 1: MediaLibraryStorage

`src/Catalog/Models/MediaLibraryStorage.php`:

```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaLibraryStorage extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'media_library_storage';

    protected $fillable = ['item_uid'];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('mli')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        foreach ((array) config('media-library.conversions', []) as $name => $spec) {
            $width = (int) ($spec['width'] ?? 0);
            $height = (int) ($spec['height'] ?? 0);
            $conv = $this->addMediaConversion((string) $name);
            if ($width > 0) $conv->width($width);
            if ($height > 0) $conv->height($height);
            if (($spec['fit'] ?? 'fit') === 'crop') {
                $conv->fit(\Spatie\Image\Enums\Fit::Crop, $width ?: null, $height ?: null);
            }
        }
    }
}
```

Test the host model + media collection registration.

### Step 2: MediaLibraryFolder

```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Catalog\Models;

use Cviebrock\EloquentSluggable\Sluggable;
use Database\Factories\Kurt\Modules\MediaLibrary\Catalog\MediaLibraryFolderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kurt\Modules\MediaLibrary\Catalog\Enums\Visibility;
use Spatie\Translatable\HasTranslations;

class MediaLibraryFolder extends Model
{
    use HasFactory;
    use HasTranslations;
    use Sluggable;
    use SoftDeletes;

    protected $table = 'media_library_folders';

    /** @var list<string> */
    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'owner_type', 'owner_id', 'parent_id', 'slug', 'name', 'description',
        'path', 'depth', 'position', 'visibility', 'item_count', 'descendant_count', 'created_by',
    ];

    protected $casts = [
        'visibility' => Visibility::class,
        'depth' => 'integer',
        'position' => 'integer',
        'item_count' => 'integer',
        'descendant_count' => 'integer',
    ];

    public function sluggable(): array
    {
        return ['slug' => ['source' => 'name', 'onUpdate' => true]];
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MediaLibraryItem::class, 'folder_id');
    }

    protected static function newFactory(): MediaLibraryFolderFactory
    {
        return MediaLibraryFolderFactory::new();
    }
}
```

Factory + a feature test asserting CRUD + parent/children + items relation.

### Step 3: MediaLibraryItem

Translatable on `title, alt_text, caption, description`. Sluggable on `slug`. Cast `focal_x/focal_y` as float, `palette/exif/ai_tags/metadata` as array, `byte_size/download_count/view_count` as integer.

Key relations:
```php
public function owner(): MorphTo { return $this->morphTo(); }
public function folder(): BelongsTo { return $this->belongsTo(MediaLibraryFolder::class); }
public function storage(): BelongsTo { return $this->belongsTo(MediaLibraryStorage::class, 'storage_id'); }
public function tags(): BelongsToMany { return $this->belongsToMany(MediaLibraryTag::class, 'media_library_item_tag', 'item_id', 'tag_id')->withTimestamps(); }
public function attachments(): HasMany { return $this->hasMany(MediaLibraryAttachment::class, 'item_id'); }
public function versions(): HasMany { return $this->hasMany(\Kurt\Modules\MediaLibrary\Storage\Models\MediaLibraryVersion::class, 'item_id'); }
public function variants(): HasMany { return $this->hasMany(\Kurt\Modules\MediaLibrary\Storage\Models\MediaLibraryVariant::class, 'item_id'); }
```

Methods:

```php
public function spatieMedia(): ?\Spatie\MediaLibrary\MediaCollections\Models\Media
{
    return $this->storage?->getFirstMedia('mli');
}

public function url(?string $conversion = null): string
{
    $media = $this->spatieMedia();
    if ($media === null) return '';
    return $conversion === null ? $media->getUrl() : $media->getUrl($conversion);
}

public function variant(array $spec): \Kurt\Modules\MediaLibrary\Storage\Models\MediaLibraryVariant
{
    return app(\Kurt\Modules\MediaLibrary\Storage\Support\VariantGenerator::class)->generateOrFetch($this, $spec);
}

public function activeShares(): \Illuminate\Database\Eloquent\Collection
{
    return $this->shareLinks()->whereNull('revoked_at')->where(function ($q) {
        $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
    })->get();
}

public function shareLinks(): HasMany
{
    return $this->hasMany(\Kurt\Modules\MediaLibrary\Sharing\Models\ShareLink::class, 'item_id');
}
```

Scopes per spec §14.1 (`byOwner`, `byFolder`, `byTag`, `byMimeType`, `byDateRange`, `search`).

### Step 4: MediaLibraryTag

```php
public array $translatable = ['name'];
protected $fillable = ['owner_type', 'owner_id', 'slug', 'name', 'color', 'position'];

public function items(): BelongsToMany
{
    return $this->belongsToMany(MediaLibraryItem::class, 'media_library_item_tag', 'tag_id', 'item_id');
}
```

### Step 5: MediaLibraryAttachment

```php
protected $fillable = ['item_id', 'attachable_type', 'attachable_id', 'role', 'position'];
protected $casts = ['position' => 'integer'];

public function item(): BelongsTo
{
    return $this->belongsTo(MediaLibraryItem::class, 'item_id');
}

public function attachable(): MorphTo
{
    return $this->morphTo();
}
```

### Step 6: MediaLibrarySavedSearch

```php
protected $fillable = ['user_id', 'name', 'filters'];
protected $casts = ['filters' => 'array'];

public function user(): BelongsTo
{
    return $this->belongsTo(config('auth.providers.users.model'), 'user_id');
}
```

### Step 7: Run + commit

```bash
C:/laragon/bin/php/php-8.4.5-nts-Win32-vs17-x64/php.exe vendor/bin/pint --test
C:/laragon/bin/php/php-8.4.5-nts-Win32-vs17-x64/php.exe vendor/bin/phpstan analyse --memory-limit=2G
C:/laragon/bin/php/php-8.4.5-nts-Win32-vs17-x64/php.exe vendor/bin/pest tests/Feature/Catalog
git add src/Catalog database/factories tests
git commit -m "feat(media-library): add Catalog models, factories, relations, scopes"
```

Expected: ~12 catalog tests pass.

---

## Task 7: Storage models + factories

**Files:**
- Create: `src/Storage/Models/{MediaLibraryVersion, MediaLibraryVariant, MediaLibraryPendingUpload}.php`
- Factories under `database/factories/Storage/`

- [ ] **Step 1: MediaLibraryVersion**

```php
protected $fillable = ['item_id', 'spatie_media_id', 'filename', 'mime_type', 'byte_size', 'changelog', 'created_by'];
protected $casts = ['byte_size' => 'integer'];

public function item(): BelongsTo { return $this->belongsTo(MediaLibraryItem::class, 'item_id'); }
public function creator(): BelongsTo { return $this->belongsTo(config('auth.providers.users.model'), 'created_by'); }
```

- [ ] **Step 2: MediaLibraryVariant**

```php
protected $fillable = ['item_id', 'key', 'spec', 'path', 'mime_type', 'byte_size', 'last_used_at', 'generated_at'];
protected $casts = ['spec' => 'array', 'last_used_at' => 'datetime', 'generated_at' => 'datetime', 'byte_size' => 'integer'];

public function item(): BelongsTo { return $this->belongsTo(MediaLibraryItem::class, 'item_id'); }
```

- [ ] **Step 3: MediaLibraryPendingUpload**

```php
protected $fillable = ['owner_type', 'owner_id', 'upload_id', 'filename', 'mime_type', 'byte_size', 'driver', 'driver_payload', 'status', 'completed_at', 'expires_at', 'created_by'];
protected $casts = ['driver_payload' => 'array', 'status' => PendingUploadStatus::class, 'completed_at' => 'datetime', 'expires_at' => 'datetime', 'byte_size' => 'integer'];

public function owner(): MorphTo { return $this->morphTo(); }
```

- [ ] **Step 4: Tests + commit**

```bash
C:/laragon/bin/php/php-8.4.5-nts-Win32-vs17-x64/php.exe vendor/bin/pest tests/Feature/Storage
git add src/Storage database/factories tests
git commit -m "feat(media-library): add Storage models (version, variant, pending upload)"
```

---

## Task 8: Sharing + Access models

**Files:**
- Create: `src/Sharing/Models/{ShareLink, AccessLogEntry}.php`
- Create: `src/Access/Models/FolderPermission.php`
- Create: `src/Access/Values/Subject.php`
- Factories + tests

- [ ] **Step 1: ShareLink**

```php
protected $fillable = ['item_id', 'folder_id', 'token', 'abilities', 'invitee_email', 'expires_at', 'revoked_at', 'access_count', 'last_accessed_at', 'last_accessed_ip', 'created_by'];
protected $casts = ['abilities' => 'array', 'expires_at' => 'datetime', 'revoked_at' => 'datetime', 'last_accessed_at' => 'datetime', 'access_count' => 'integer'];

public function item(): BelongsTo { return $this->belongsTo(MediaLibraryItem::class); }
public function folder(): BelongsTo { return $this->belongsTo(MediaLibraryFolder::class); }
public function creator(): BelongsTo { return $this->belongsTo(config('auth.providers.users.model'), 'created_by'); }

public function isExpired(): bool { return $this->expires_at !== null && $this->expires_at->isPast(); }
public function isActive(): bool { return $this->revoked_at === null && ! $this->isExpired(); }
```

- [ ] **Step 2: AccessLogEntry**

```php
protected $fillable = ['item_id', 'share_link_id', 'user_id', 'action', 'ip', 'user_agent', 'occurred_at'];
protected $casts = ['action' => AccessAction::class, 'occurred_at' => 'datetime'];

public function item(): BelongsTo { return $this->belongsTo(MediaLibraryItem::class); }
public function shareLink(): BelongsTo { return $this->belongsTo(ShareLink::class); }
public function user(): BelongsTo { return $this->belongsTo(config('auth.providers.users.model'), 'user_id'); }
```

- [ ] **Step 3: FolderPermission**

```php
protected $fillable = ['folder_id', 'subject_type', 'subject_value', 'capability', 'cascade'];
protected $casts = ['subject_type' => SubjectType::class, 'capability' => Capability::class, 'cascade' => 'boolean'];

public function folder(): BelongsTo { return $this->belongsTo(MediaLibraryFolder::class); }
```

- [ ] **Step 4: Subject value object**

```php
namespace Kurt\Modules\MediaLibrary\Access\Values;

use Kurt\Modules\MediaLibrary\Access\Enums\SubjectType;

final readonly class Subject
{
    public function __construct(public SubjectType $type, public ?string $value) {}

    public function matches(string $rowType, ?string $rowValue): bool
    {
        if ($this->type->value !== $rowType) return false;
        if ($this->type === SubjectType::Everyone) return true;
        return $this->value === $rowValue;
    }
}
```

- [ ] **Step 5: Tests + commit**

```bash
git add src/Sharing src/Access database/factories tests
git commit -m "feat(media-library): add Sharing + Access models with Subject value"
```

---

## Task 9: Contracts + traits

**Files:**
- Create: `src/Contracts/MediaLibraryOwner.php`
- Create: `src/Storage/Contracts/{ExifExtractor, OcrExtractor, AiTagger, BlurhashGenerator, PaletteExtractor}.php`
- Create: `src/Access/Contracts/MediaSubjectResolver.php`
- Create: `src/Search/Contracts/ScoutAdapter.php`
- Create: `src/Concerns/{HasMediaLibraryItems, IsMediaLibraryOwner}.php`
- Create: `src/Exceptions/{OwnerNotResolved, InvalidUpload, ReplaceFailed, ShareLinkInvalid, SelfReferentialFolder}.php`

- [ ] **Step 1: MediaLibraryOwner contract**

```php
namespace Kurt\Modules\MediaLibrary\Contracts;

interface MediaLibraryOwner
{
    public function getKey(): int|string;
    public function getMorphClass(): string;
    public function getMediaLibraryDisplayName(): string;
}
```

- [ ] **Step 2: Storage contracts**

```php
namespace Kurt\Modules\MediaLibrary\Storage\Contracts;

interface ExifExtractor { public function extract(string $path): array; }

interface OcrExtractor { public function extract(string $path): string; }

/** @return array<int, string> */
interface AiTagger { public function tag(string $path): array; }

interface BlurhashGenerator { public function generate(string $path): string; }

/** @return array{dominant: string, palette: array<int, string>} */
interface PaletteExtractor { public function extract(string $path): array; }
```

- [ ] **Step 3: MediaSubjectResolver**

```php
namespace Kurt\Modules\MediaLibrary\Access\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Kurt\Modules\MediaLibrary\Access\Values\Subject;
use Kurt\Modules\MediaLibrary\Contracts\MediaLibraryOwner;

interface MediaSubjectResolver
{
    /** @return array<int, Subject> */
    public function subjects(?Authenticatable $user): array;

    public function defaultOwner(?Authenticatable $user): MediaLibraryOwner;
}
```

- [ ] **Step 4: ScoutAdapter**

```php
namespace Kurt\Modules\MediaLibrary\Search\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;

interface ScoutAdapter
{
    public function index(MediaLibraryItem $item): void;
    public function unindex(MediaLibraryItem $item): void;

    /** @return Collection<int, MediaLibraryItem> */
    public function search(string $query, array $filters = [], int $limit = 50): Collection;
}
```

- [ ] **Step 5: HasMediaLibraryItems trait**

Use the full body from spec §11.1.

- [ ] **Step 6: IsMediaLibraryOwner trait**

```php
namespace Kurt\Modules\MediaLibrary\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;

/** @mixin \Illuminate\Database\Eloquent\Model */
trait IsMediaLibraryOwner
{
    public function mediaLibraryItems(): MorphMany
    {
        return $this->morphMany(MediaLibraryItem::class, 'owner');
    }

    public function mediaLibraryFolders(): MorphMany
    {
        return $this->morphMany(MediaLibraryFolder::class, 'owner');
    }

    public function getMediaLibraryDisplayName(): string
    {
        return (string) ($this->getAttribute('name') ?? $this->getAttribute('email') ?? $this->getKey());
    }
}
```

- [ ] **Step 7: Exceptions**

Each is `final class XYZ extends \RuntimeException {}`.

- [ ] **Step 8: Commit**

```bash
git add src/Contracts src/Storage/Contracts src/Access/Contracts src/Search/Contracts src/Concerns src/Exceptions
git commit -m "feat(media-library): add contracts + traits + exceptions"
```

---

## Task 10: Default extractors

**Files:**
- Create: `src/Storage/Extractors/{DefaultExifExtractor, InterventionBlurhashGenerator, InterventionPaletteExtractor}.php`
- Create: `tests/Feature/Storage/Extractors/*Test.php`

- [ ] **Step 1: DefaultExifExtractor**

```php
final class DefaultExifExtractor implements ExifExtractor
{
    /** @return array<string, mixed> */
    public function extract(string $path): array
    {
        if (! function_exists('exif_read_data') || ! is_readable($path)) return [];
        $data = @exif_read_data($path);
        return is_array($data) ? array_filter($data, fn ($v) => is_scalar($v) || is_array($v)) : [];
    }
}
```

- [ ] **Step 2: InterventionBlurhashGenerator + PaletteExtractor**

Use Intervention Image 3.x. Blurhash via the algorithm port from `kornrunner/blurhash`. Palette via a 4×4 downsample + most-common-color extraction.

```php
final class InterventionBlurhashGenerator implements BlurhashGenerator
{
    public function generate(string $path): string
    {
        if (! class_exists(\kornrunner\Blurhash\Blurhash::class)) {
            return '';
        }
        $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
        $img = $manager->read($path)->scaleDown(32);
        $pixels = [];
        for ($y = 0; $y < $img->height(); $y++) {
            $row = [];
            for ($x = 0; $x < $img->width(); $x++) {
                $c = $img->pickColor($x, $y);
                $row[] = [$c->red()->value(), $c->green()->value(), $c->blue()->value()];
            }
            $pixels[] = $row;
        }
        return \kornrunner\Blurhash\Blurhash::encode($pixels, 4, 4);
    }
}
```

Add `kornrunner/blurhash: ^1.0` to `composer.json` as `require-dev` (consumer can swap to a different generator).

- [ ] **Step 3: Tests + commit**

Test using a known JPEG fixture.

```bash
git add src/Storage/Extractors tests/Feature/Storage composer.json composer.lock
git commit -m "feat(media-library): add default EXIF + blurhash + palette extractors"
```

---

## Task 11: DefaultSubjectResolver + FolderPermissionResolver + MediaLibraryAccess

**Files:**
- Create: `src/Access/Support/{DefaultSubjectResolver, FolderPermissionResolver, MediaLibraryAccess}.php`
- Tests under `tests/Feature/Access/`

- [ ] **Step 1: DefaultSubjectResolver**

```php
namespace Kurt\Modules\MediaLibrary\Access\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Kurt\Modules\MediaLibrary\Access\Contracts\MediaSubjectResolver;
use Kurt\Modules\MediaLibrary\Access\Enums\SubjectType;
use Kurt\Modules\MediaLibrary\Access\Values\Subject;
use Kurt\Modules\MediaLibrary\Contracts\MediaLibraryOwner;
use Kurt\Modules\MediaLibrary\Exceptions\OwnerNotResolved;

final class DefaultSubjectResolver implements MediaSubjectResolver
{
    public function subjects(?Authenticatable $user): array
    {
        $subjects = [new Subject(SubjectType::Everyone, null)];
        if ($user !== null) {
            $subjects[] = new Subject(SubjectType::User, (string) $user->getAuthIdentifier());
        }
        return $subjects;
    }

    public function defaultOwner(?Authenticatable $user): MediaLibraryOwner
    {
        if (! $user instanceof MediaLibraryOwner) {
            throw new OwnerNotResolved('Authenticated user does not implement MediaLibraryOwner');
        }
        return $user;
    }
}
```

- [ ] **Step 2: FolderPermissionResolver**

Mirrors ResourceLibrary's algorithm. Walk ancestry, find highest matching capability per subject, fall back to folder visibility.

- [ ] **Step 3: MediaLibraryAccess**

Request-scoped cache keyed by `userId:folderId:capability`.

- [ ] **Step 4: Exhaustive ACL matrix tests**

Cascade × visibility × ancestor override × subject mismatch.

- [ ] **Step 5: Commit**

```bash
git add src/Access/Support tests/Feature/Access
git commit -m "feat(media-library): add DefaultSubjectResolver + FolderPermissionResolver + MediaLibraryAccess"
```

---

## Task 12: ShareLinkSigner + ShareLinkResolver + AccessLogger

**Files:**
- Create: `src/Sharing/Support/{ShareLinkSigner, ShareLinkResolver, AccessLogger}.php`
- Tests under `tests/Feature/Sharing/`

- [ ] **Step 1: ShareLinkSigner**

```php
final class ShareLinkSigner
{
    public function generateToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
    }

    public function url(string $token): string
    {
        $prefix = trim((string) config('media-library.routes.share_prefix', 'media-library/share'), '/');
        return url($prefix . '/' . $token);
    }
}
```

- [ ] **Step 2: ShareLinkResolver**

```php
final class ShareLinkResolver
{
    public function resolve(string $token): ShareLink
    {
        $link = ShareLink::query()->where('token', $token)->first();
        if ($link === null) throw new ShareLinkInvalid('not_found');
        if (! $link->isActive()) throw new ShareLinkInvalid('inactive');
        return $link;
    }
}
```

- [ ] **Step 3: AccessLogger**

```php
final class AccessLogger
{
    public function log(?MediaLibraryItem $item, ?ShareLink $link, ?Model $user, AccessAction $action): void
    {
        if (! (bool) config('media-library.access_log.enabled', true)) return;
        if ($action === AccessAction::View && ! (bool) config('media-library.access_log.on_view', true)) return;
        if ($action === AccessAction::Download && ! (bool) config('media-library.access_log.on_download', true)) return;

        $request = request();
        AccessLogEntry::create([
            'item_id' => $item?->id,
            'share_link_id' => $link?->id,
            'user_id' => $user?->getKey(),
            'action' => $action,
            'ip' => $request?->ip(),
            'user_agent' => (string) $request?->userAgent(),
            'occurred_at' => now(),
        ]);
    }
}
```

- [ ] **Step 4: ShareLinkController**

Route opt-in via `config('media-library.routes.share_enabled')`. Controller handles view + download. Increments `access_count`, sets `last_accessed_*`, writes access log.

```php
final class ShareLinkController
{
    public function __construct(
        private readonly ShareLinkResolver $resolver,
        private readonly AccessLogger $logger,
    ) {}

    public function show(string $token, Request $request)
    {
        $link = $this->resolver->resolve($token);
        $abilities = (array) $link->abilities;
        $requested = $request->query('download') ? 'download' : 'view';

        if (! in_array($requested, $abilities, true)) {
            abort(403, 'ability_not_granted');
        }

        $link->forceFill([
            'access_count' => $link->access_count + 1,
            'last_accessed_at' => now(),
            'last_accessed_ip' => $request->ip(),
        ])->save();

        $this->logger->log($link->item, $link, $request->user(), AccessAction::from($requested));

        $media = $link->item?->spatieMedia();
        if ($media === null) abort(410, 'media_gone');

        return $requested === 'download'
            ? response()->download($media->getPath(), $media->file_name)
            : response()->file($media->getPath());
    }
}
```

`routes/share.php`:

```php
<?php

use Illuminate\Support\Facades\Route;
use Kurt\Modules\MediaLibrary\Sharing\Http\Controllers\ShareLinkController;

Route::middleware('web')
    ->prefix((string) config('media-library.routes.share_prefix', 'media-library/share'))
    ->group(function () {
        Route::get('{token}', [ShareLinkController::class, 'show'])->name('media-library.share.show');
    });
```

The service provider conditionally requires this file when `config('media-library.routes.share_enabled')` is true.

- [ ] **Step 5: Tests + commit**

Tests cover: token generation uniqueness; resolver throws for missing/revoked/expired; controller streams + logs.

```bash
git add src/Sharing tests/Feature/Sharing routes/share.php
git commit -m "feat(media-library): add ShareLinkSigner + Resolver + AccessLogger + share controller"
```

---

## Task 13: UploadCoordinator (server proxy)

**Files:**
- Create: `src/Storage/Support/{UploadCoordinator, MetadataExtractor}.php`
- Tests under `tests/Feature/Storage/Upload/`

- [ ] **Step 1: MetadataExtractor**

Extracts dimensions (Intervention), dominant_color + palette + blurhash via bound contracts.

```php
final class MetadataExtractor
{
    public function __construct(
        private readonly BlurhashGenerator $blurhash,
        private readonly PaletteExtractor $palette,
    ) {}

    /** @return array<string, mixed> */
    public function extractSync(string $path, string $mimeType): array
    {
        $meta = ['width' => null, 'height' => null];
        if (str_starts_with($mimeType, 'image/')) {
            try {
                $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
                $img = $manager->read($path);
                $meta['width'] = $img->width();
                $meta['height'] = $img->height();
                $meta['blurhash'] = $this->blurhash->generate($path);
                $palette = $this->palette->extract($path);
                $meta['dominant_color'] = $palette['dominant'] ?? null;
                $meta['palette'] = $palette['palette'] ?? [];
            } catch (\Throwable) {
                // best-effort; leave nulls
            }
        }
        return $meta;
    }
}
```

- [ ] **Step 2: UploadCoordinator**

```php
final class UploadCoordinator
{
    public function __construct(
        private readonly MediaSubjectResolver $subjects,
        private readonly MetadataExtractor $extractor,
    ) {}

    public function upload(UploadedFile $file, ?MediaLibraryOwner $owner, ?MediaLibraryFolder $folder = null, array $attributes = []): MediaLibraryItem
    {
        $owner ??= $this->subjects->defaultOwner(auth()->user());

        return DB::transaction(function () use ($file, $owner, $folder, $attributes) {
            $storage = MediaLibraryStorage::create(['item_uid' => (string) Str::uuid()]);
            $media = $storage->addMedia($file->getPathname())
                ->preservingOriginal()
                ->usingFileName($file->getClientOriginalName())
                ->toMediaCollection('mli', (string) config('media-library.uploads.disk', 'public'));

            $extracted = $this->extractor->extractSync($media->getPath(), $media->mime_type);

            $title = $attributes['title'] ?? ['en' => $file->getClientOriginalName()];
            $item = MediaLibraryItem::create([
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => $owner->getKey(),
                'folder_id' => $folder?->id,
                'storage_id' => $storage->id,
                'slug' => Str::slug((string) ($attributes['slug'] ?? $file->getClientOriginalName())) . '-' . substr($storage->item_uid, 0, 8),
                'title' => $title,
                'alt_text' => $attributes['alt_text'] ?? null,
                'caption' => $attributes['caption'] ?? null,
                'description' => $attributes['description'] ?? null,
                'filename' => $file->getClientOriginalName(),
                'mime_type' => $media->mime_type,
                'byte_size' => $media->size,
                'width' => $extracted['width'] ?? null,
                'height' => $extracted['height'] ?? null,
                'dominant_color' => $extracted['dominant_color'] ?? null,
                'palette' => $extracted['palette'] ?? null,
                'blurhash' => $extracted['blurhash'] ?? null,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            if ($folder !== null) {
                $folder->increment('item_count');
            }

            ItemUploaded::dispatch($item);

            return $item;
        });
    }
}
```

- [ ] **Step 3: Tests + commit**

Server-proxy upload happy path with PNG fixture. Asserts item persisted, spatie media attached, dimensions extracted, dispatched event.

```bash
git add src/Storage/Support tests
git commit -m "feat(media-library): add MetadataExtractor + UploadCoordinator (server proxy)"
```

---

## Task 14: Presigned upload flow

**Files:**
- Extend `src/Storage/Support/UploadCoordinator.php` with `initiateUpload` + `completeUpload` + `cancelUpload`
- Tests under `tests/Feature/Storage/PresignedUpload/`

- [ ] **Step 1: initiateUpload**

Validates mime + size against config. Generates presigned PUT URL via `Storage::disk(config('media-library.uploads.disk'))->temporaryUploadUrl(...)`. Persists `media_library_pending_uploads` row.

- [ ] **Step 2: completeUpload**

Look up pending row + verify status. Attach the already-on-disk object as the spatie Media file. Run extractor pipeline. Persist item. Mark pending Completed.

- [ ] **Step 3: cancelUpload**

Mark pending Cancelled.

- [ ] **Step 4: Tests**

Cover initiate happy path, complete happy path, cancel, expired pending. Use a fake disk for S3-style storage simulation (`Storage::fake('s3')`).

- [ ] **Step 5: Commit**

```bash
git add src/Storage/Support tests
git commit -m "feat(media-library): add presigned direct-to-S3 upload via UploadCoordinator"
```

---

## Task 15: ConversionEngine + FocalPointCropper + VariantGenerator

**Files:**
- Create: `src/Storage/Support/{ConversionEngine, FocalPointCropper, VariantGenerator}.php`

- [ ] **Step 1: FocalPointCropper**

Translates focal_x/focal_y + target dimensions into a crop rectangle. Returns coordinates compatible with spatie's `manualCrop`.

```php
final class FocalPointCropper
{
    /** @return array{x: int, y: int, width: int, height: int} */
    public function compute(int $sourceWidth, int $sourceHeight, int $targetWidth, int $targetHeight, float $focalX, float $focalY): array
    {
        $sourceRatio = $sourceWidth / max($sourceHeight, 1);
        $targetRatio = $targetWidth / max($targetHeight, 1);

        if ($sourceRatio > $targetRatio) {
            $cropHeight = $sourceHeight;
            $cropWidth = (int) round($sourceHeight * $targetRatio);
        } else {
            $cropWidth = $sourceWidth;
            $cropHeight = (int) round($sourceWidth / $targetRatio);
        }

        $cx = (int) round($focalX * $sourceWidth);
        $cy = (int) round($focalY * $sourceHeight);
        $x = max(0, min($sourceWidth - $cropWidth, $cx - intdiv($cropWidth, 2)));
        $y = max(0, min($sourceHeight - $cropHeight, $cy - intdiv($cropHeight, 2)));

        return ['x' => $x, 'y' => $y, 'width' => $cropWidth, 'height' => $cropHeight];
    }
}
```

- [ ] **Step 2: VariantGenerator**

```php
final class VariantGenerator
{
    public function __construct(private readonly FocalPointCropper $cropper) {}

    public function generateOrFetch(MediaLibraryItem $item, array $spec): MediaLibraryVariant
    {
        $key = $this->canonicalizeKey($spec);
        $existing = MediaLibraryVariant::query()->where('item_id', $item->id)->where('key', $key)->first();
        if ($existing !== null) {
            $existing->forceFill(['last_used_at' => now()])->save();
            return $existing;
        }

        $sourcePath = $item->spatieMedia()?->getPath();
        if ($sourcePath === null) throw new \RuntimeException('item has no source media');

        $disk = (string) config('media-library.uploads.disk', 'public');
        $variantRelative = 'media-library/variants/' . $item->storage->item_uid . '/' . $key . '.' . ($spec['format'] ?? 'jpg');
        $variantFull = Storage::disk($disk)->path($variantRelative);
        @mkdir(dirname($variantFull), recursive: true);

        $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
        $img = $manager->read($sourcePath);
        $fit = $spec['fit'] ?? 'fit';
        $width = (int) ($spec['width'] ?? $img->width());
        $height = (int) ($spec['height'] ?? $img->height());

        if ($fit === 'crop') {
            $crop = $this->cropper->compute($img->width(), $img->height(), $width, $height, (float) $item->focal_x, (float) $item->focal_y);
            $img = $img->crop($crop['width'], $crop['height'], $crop['x'], $crop['y'])->resize($width, $height);
        } else {
            $img = $img->scaleDown($width, $height);
        }

        $quality = (int) ($spec['quality'] ?? 85);
        $img->save($variantFull, $quality);

        return MediaLibraryVariant::create([
            'item_id' => $item->id,
            'key' => $key,
            'spec' => $spec,
            'path' => $variantRelative,
            'mime_type' => mime_content_type($variantFull) ?: 'application/octet-stream',
            'byte_size' => filesize($variantFull) ?: 0,
            'last_used_at' => now(),
            'generated_at' => now(),
        ]);
    }

    private function canonicalizeKey(array $spec): string
    {
        ksort($spec);
        return implode('-', [
            ($spec['width'] ?? 'auto') . 'x' . ($spec['height'] ?? 'auto'),
            $spec['fit'] ?? 'fit',
            $spec['format'] ?? 'jpg',
            'q' . ($spec['quality'] ?? 85),
        ]);
    }
}
```

- [ ] **Step 3: ConversionEngine**

Stub for now — preset conversions live on the `MediaLibraryStorage` host (spatie handles them). This class is just a thin wrapper that translates `MediaLibraryItem::url($conversion)` calls.

- [ ] **Step 4: Tests + commit**

Tests cover: focal corner math, ad-hoc variant generation, cache hit on second call, key canonicalization.

```bash
git add src/Storage/Support tests
git commit -m "feat(media-library): add ConversionEngine + FocalPointCropper + VariantGenerator"
```

---

## Task 16: ReplaceCoordinator

**Files:**
- Create: `src/Storage/Support/ReplaceCoordinator.php`
- Test under `tests/Feature/Storage/ReplaceTest.php`

- [ ] **Step 1: ReplaceCoordinator**

Per spec §9. Atomic transaction: capture previous spatie Media id, write version row, attach new file, swap storage's spatie media, recompute extractors, regenerate conversions, dispatch ItemReplaced.

- [ ] **Step 2: Tests**

Confirm stable item id after replace; attachment rows preserved; version row exists with previous spatie media id; ItemReplaced event dispatched.

- [ ] **Step 3: Commit**

```bash
git add src/Storage/Support/ReplaceCoordinator.php tests
git commit -m "feat(media-library): add ReplaceCoordinator with version history + stable item id"
```

---

## Task 17: Domain events

**Files:**
- Create: under `src/Catalog/Events/`, `src/Storage/Events/`, `src/Sharing/Events/`, `src/Access/Events/`

Per spec §22. Each uses `Illuminate\Foundation\Events\Dispatchable`.

Bulk creation:

- Catalog: `FolderCreated/Updated/Moved/Deleted/Restored/PermissionChanged`, `ItemUploaded/Updated/Replaced/Attached/Detached/Tagged/Untagged/Trashed/Restored/Deleted/Viewed/Downloaded`.
- Storage: `UploadInitiated/Completed/Cancelled/Expired`, `VariantGenerated`, `VersionPruned`, `BlurhashComputed/PaletteComputed/ExifExtracted/AiTagsAssigned/TextExtracted`.
- Sharing: `ShareLinkCreated/Accessed/Revoked/Expired`.

Single-arg events: one readonly promoted property. Multi-arg as needed (e.g. `ItemReplaced(MediaLibraryItem $item, int $previousSpatieMediaId)`).

- [ ] **Step 1: Write all event classes**
- [ ] **Step 2: Commit**

```bash
git add src
git commit -m "feat(media-library): add domain events across sub-areas"
```

---

## Task 18: Observers

**Files:**
- Create: `src/Catalog/Observers/{MediaLibraryItemObserver, MediaLibraryFolderObserver}.php`

- [ ] **Step 1: MediaLibraryItemObserver**

```php
final class MediaLibraryItemObserver
{
    public function deleted(MediaLibraryItem $item): void
    {
        if ($item->folder_id !== null) {
            DB::table('media_library_folders')
                ->where('id', $item->folder_id)
                ->update(['item_count' => DB::raw('CASE WHEN item_count > 0 THEN item_count - 1 ELSE 0 END')]);
        }
        ItemDeleted::dispatch($item);
    }

    public function restored(MediaLibraryItem $item): void
    {
        if ($item->folder_id !== null) {
            DB::table('media_library_folders')->where('id', $item->folder_id)->increment('item_count');
        }
        ItemRestored::dispatch($item);
    }
}
```

- [ ] **Step 2: MediaLibraryFolderObserver**

```php
final class MediaLibraryFolderObserver
{
    public function creating(MediaLibraryFolder $folder): void
    {
        if (! $folder->path) {
            $parentPath = $folder->parent?->path ?? '';
            $folder->path = $parentPath . '/' . $folder->slug;
            $folder->depth = ($folder->parent?->depth ?? -1) + 1;
        }
    }

    public function created(MediaLibraryFolder $folder): void
    {
        FolderCreated::dispatch($folder);
    }

    public function deleted(MediaLibraryFolder $folder): void
    {
        FolderDeleted::dispatch($folder);
    }
}
```

- [ ] **Step 3: Tests + commit**

```bash
git add src/Catalog/Observers tests
git commit -m "feat(media-library): add Folder + Item observers"
```

---

## Task 19: Policies

**Files:**
- Create: `src/Policies/{MediaLibraryItemPolicy, MediaLibraryFolderPolicy, ShareLinkPolicy, SavedSearchPolicy}.php`

Each delegates to `MediaLibraryAccess::check`. Tests verify allow/deny per matrix per spec §18.

- [ ] **Step 1: Write each policy + tests**
- [ ] **Step 2: Commit**

```bash
git add src/Policies tests/Feature/Policies
git commit -m "feat(media-library): add policies"
```

---

## Task 20: Console commands

**Files:**
- Create: `src/Console/Commands/{PruneVersions, PruneVariants, RebuildPaths, Recount, ExpireShares, PruneShares, ExpirePendingUploads, Reextract, Reindex, Demo}Command.php`

Per spec §20. Each is a small Command.

Highlights:

```php
// PruneVersionsCommand
protected $signature = 'media-library:prune-versions {item?}';
public function handle(): int
{
    $keepNewest = (int) config('media-library.versions.keep_old', 10);
    $items = $this->argument('item') ? MediaLibraryItem::query()->whereKey($this->argument('item'))->get() : MediaLibraryItem::all();
    foreach ($items as $item) {
        $versions = $item->versions()->orderByDesc('created_at')->skip($keepNewest)->take(1000)->get();
        foreach ($versions as $version) $version->delete();
    }
    return self::SUCCESS;
}
```

```php
// ExpirePendingUploadsCommand
public function handle(): int
{
    MediaLibraryPendingUpload::query()
        ->where('status', PendingUploadStatus::Pending->value)
        ->where('expires_at', '<', now())
        ->update(['status' => PendingUploadStatus::Expired->value]);
    return self::SUCCESS;
}
```

- [ ] **Step 1: Write each command**
- [ ] **Step 2: Tests for non-trivial commands**
- [ ] **Step 3: Commit**

```bash
git add src/Console tests/Feature/Console
git commit -m "feat(media-library): add console commands"
```

---

## Task 21: Notifications + Blade templates

**Files:**
- Create: `src/Notifications/{ShareLinkCreated, ShareLinkAccessed, ItemReplaced, LargeUploadCompleted}.php`
- Create: `resources/views/notifications/*.blade.php`

Each Notification implements `ShouldQueue`, uses `via(['mail', 'database'])` when notifications enabled in config.

- [ ] **Step 1: Each Notification class + Blade template**
- [ ] **Step 2: Tests with `Notification::fake()`**
- [ ] **Step 3: Commit**

```bash
git add src/Notifications resources/views tests
git commit -m "feat(media-library): add optional Notifications + default Blade templates"
```

---

## Task 22: Top-level MediaLibrary facade-service

**Files:**
- Create: `src/Support/MediaLibrary.php`
- Create: `src/Facades/MediaLibrary.php`

Implements every method from spec §16. Delegates to UploadCoordinator / ReplaceCoordinator / ShareLinkSigner / etc.

- [ ] **Step 1: Write facade-service**
- [ ] **Step 2: Thin Facade class**

```php
namespace Kurt\Modules\MediaLibrary\Facades;

use Illuminate\Support\Facades\Facade;
use Kurt\Modules\MediaLibrary\Support\MediaLibrary as Service;

final class MediaLibrary extends Facade
{
    protected static function getFacadeAccessor(): string { return Service::class; }
}
```

- [ ] **Step 3: Tests covering top-level API**
- [ ] **Step 4: Commit**

```bash
git add src/Support src/Facades tests
git commit -m "feat(media-library): add top-level MediaLibrary facade-service"
```

---

## Task 23: MediaLibraryServiceProvider

**Files:**
- Create: `src/Providers/MediaLibraryServiceProvider.php`

Per the pattern used in Events/Chat providers. Highlights:

- `configurePackage`: name, hasConfigFile('media-library'), hasTranslations, hasViews('media-library'), hasMigrations(every filename), hasCommands(all 10).
- `packageRegistered`: bind `MediaSubjectResolver`, `ScoutAdapter` (if FQCN bound), `ExifExtractor`/`BlurhashGenerator`/`PaletteExtractor`/`OcrExtractor`/`AiTagger` from config. Singleton bindings for UploadCoordinator, ReplaceCoordinator, VariantGenerator, FolderPermissionResolver, MediaLibraryAccess, ShareLinkSigner, ShareLinkResolver, AccessLogger. Singleton for `Support\MediaLibrary`.
- `packageBooted`: observe Folder + Item, register policies, conditionally load `routes/share.php`, schedule commands.

- [ ] **Step 1: Write provider**
- [ ] **Step 2: Update `tests/TestCase.php`**

```php
protected function modulePackageProviders($app): array
{
    return [
        \Spatie\MediaLibrary\MediaLibraryServiceProvider::class,
        \Cviebrock\EloquentSluggable\ServiceProvider::class,
        \Kurt\Modules\MediaLibrary\Providers\MediaLibraryServiceProvider::class,
    ];
}
```

- [ ] **Step 3: Smoke tests + commit**

```bash
git add src/Providers tests
git commit -m "feat(media-library): add MediaLibraryServiceProvider"
```

---

## Task 24: Cross-cutting integration tests

**Files:**
- Create: `tests/Feature/Integration/*.php`

Tests covering scenarios that thread multiple sub-areas:

- `UploadAndAttachTest.php`: upload → attach to Post (HasMediaLibraryItems trait stub) → retrieve via `mediaItems('cover')`.
- `ReplaceKeepsAttachmentsTest.php`: attach item to multiple consumer rows → replace file → consumers still resolve.
- `FolderMoveCascadesPathsTest.php`: nested folder move rewrites descendant paths.
- `AclMatrixTest.php`: cascade × visibility × subject mismatch.
- `ShareEndToEndTest.php`: shareItem → access via controller → access log row + counter.
- `PresignedUploadFlowTest.php`: initiate → complete → item issued.
- `VariantCacheHitTest.php`: variant(spec) twice → second call hits cache, first uses generator.
- `GdprAnonymizationTest.php`: anonymize user → owner fields preserved as morph but display strings hashed.

- [ ] **Step 1: Write each test**
- [ ] **Step 2: Commit**

```bash
git add tests/Feature/Integration
git commit -m "test(media-library): add cross-cutting integration tests"
```

Expected: ~250-300 total tests.

---

## Task 25: CI + README + CHANGELOG + UPGRADE-1.0

**Files:**
- Copy: `.github/workflows/tests.yml`, `.github/dependabot.yml` from Events.
- Create: `README.md`, `CHANGELOG.md`, `UPGRADE-1.0.md`.

- [ ] **Step 1: Copy CI**

```bash
cp ../KurtModules-Events/.github/workflows/tests.yml .github/workflows/tests.yml
cp ../KurtModules-Events/.github/dependabot.yml .github/dependabot.yml
```

- [ ] **Step 2: README.md**

```markdown
# laravel-modules-media-library

WordPress-style media bucket for Laravel SaaS: tenant-aware folders, polymorphic attachments, focal-point conversions, replace-with-stable-id versioning, share links, folder ACL.

Wraps `spatie/laravel-medialibrary` as the storage engine.

## Requirements

- PHP 8.4+
- Laravel 12.x
- `ozankurt/laravel-modules-core` v2.x
- `spatie/laravel-medialibrary` v11.x

## Installation

```bash
composer require ozankurt/laravel-modules-media-library
php artisan vendor:publish --tag=media-library-config
php artisan vendor:publish --tag=media-library-migrations
php artisan migrate
```

## What it provides

- **Catalog** — MediaLibraryFolder (nested tree), MediaLibraryItem (WordPress-style row), MediaLibraryTag, polymorphic attachments to consumer models, saved searches.
- **Storage** — Wraps spatie/laravel-medialibrary via a per-item host model. Versioning with stable item ids. Ad-hoc focal-point-aware variant generation. Presigned direct-to-S3 + server-proxy upload flows.
- **Sharing** — TTL share links with abilities (view/download) + access log + invitee email.
- **Access** — Folder ACL with the same SubjectResolver pattern as ResourceLibrary.
- **Search** — Eloquent scopes (byOwner/byFolder/byTag/byMimeType/byDateRange/search) + optional Scout adapter contract.
- **Pluggable contracts** — EXIF, OCR, AI tagger, blurhash, palette extractor, Scout adapter, MediaSubjectResolver.
- **Optional Laravel Notifications** — Mail + Database channels with publishable Blade templates.

## Filament admin

Filament v3/v4/v5 admin (WordPress-style grid + edit modal) lands in v1.1.

## License

MIT © Ozan Kurt
```

- [ ] **Step 3: CHANGELOG.md**

```markdown
# Changelog

## [1.0.0] - 2026-05-30

### Added
- Initial release of `ozankurt/laravel-modules-media-library`.
- 13 migrations across Catalog / Storage / Sharing / Access.
- `MediaLibraryItem` + `MediaLibraryStorage` host pattern that wraps spatie/laravel-medialibrary as the storage engine.
- Polymorphic owner (User/Team/Organization).
- Polymorphic many-to-many attachments to consumer models with role + position.
- Nested folder tree + per-folder ACL (same shape as ResourceLibrary).
- WordPress-style metadata fields: title, alt_text, caption, description, focal_x/y, dominant_color, palette, blurhash, exif, ai_tags, extracted_text.
- Named-preset + ad-hoc focal-point-aware conversions.
- Server-proxy AND direct-to-S3 presigned uploads.
- Replace-in-place with stable item id + version history (`media_library_versions`).
- Share links with TTL + abilities + access log.
- Tag taxonomy + saved searches per user.
- Pluggable extractor contracts (EXIF / OCR / AI tagger / blurhash / palette / Scout adapter / MediaSubjectResolver).
- Default extractors shipped: EXIF (PHP exif), Blurhash (kornrunner), Palette (Intervention).
- Console commands: prune-versions, prune-variants, rebuild-paths, recount, expire-shares, prune-shares, expire-pending-uploads, reextract, reindex, demo.
- Optional Laravel Notifications + default Blade templates.

### Planned (v1.1)
- Filament v3/v4/v5 admin resources.
- Default Scout adapter.
- Promote cross-module audit log into a shared package.
```

- [ ] **Step 4: UPGRADE-1.0.md (initial release stub)**

```markdown
# Upgrade Guide — initial release

This is v1.0.0 of `ozankurt/laravel-modules-media-library`. There is no previous version to upgrade from.

## Installation

See `README.md`. Key environment knobs:

- `MEDIA_LIBRARY_DISK` — default `public`.

## Future upgrades

This file will document migration steps for v2.0 and beyond.
```

- [ ] **Step 5: Commit**

```bash
git add .github README.md CHANGELOG.md UPGRADE-1.0.md
git commit -m "ci+docs: add github actions, README, CHANGELOG, UPGRADE-1.0"
```

---

## Task 26: Push + PR + merge + tag

- [ ] **Step 1: Final local verification**

```bash
C:/laragon/bin/php/php-8.4.5-nts-Win32-vs17-x64/php.exe vendor/bin/pint --test
C:/laragon/bin/php/php-8.4.5-nts-Win32-vs17-x64/php.exe vendor/bin/phpstan analyse --memory-limit=2G
C:/laragon/bin/php/php-8.4.5-nts-Win32-vs17-x64/php.exe vendor/bin/pest
```

All three green. ~250+ tests.

- [ ] **Step 2: Push + PR**

```bash
git push -u origin v1.0
gh pr create --title "v1.0: initial release of ozankurt/laravel-modules-media-library" --body "$(cat <<'EOF'
## Summary

- Greenfield WordPress-style media bucket for Laravel SaaS.
- 13 tables across Catalog / Storage / Sharing / Access.
- Wraps spatie/laravel-medialibrary as the storage engine via a thin per-item host model.
- Polymorphic owner + polymorphic many-to-many attachments.
- Replace-with-stable-id versioning so consumer attachments survive file swaps.
- Server-proxy + direct-to-S3 presigned uploads.
- Focal-point-aware conversions + ad-hoc variant cache.
- Share links with TTL + access log.
- Folder ACL reusing ResourceLibrary's SubjectResolver pattern.
- Pluggable contracts for EXIF / OCR / AI / blurhash / palette / Scout / subject resolver.
- Filament admin lands in v1.1.

## Test plan
- [x] vendor/bin/pint --test
- [x] vendor/bin/phpstan analyse
- [x] vendor/bin/pest
- [ ] CI matrix green on this PR
EOF
)"
```

- [ ] **Step 3: Wait for CI green, merge, tag, release**

```bash
gh pr checks <PR_NUMBER> --watch
gh pr merge <PR_NUMBER> --merge
git switch master
git pull
git tag -a v1.0.0 -m "v1.0.0"
git push origin v1.0.0
gh release create v1.0.0 --title "v1.0.0" --notes-file CHANGELOG.md
```

---

## Definition of done

- [ ] All 13 migrations applied; smoke test confirms every table present.
- [ ] Server-proxy + presigned upload paths covered.
- [ ] Replace flow proves stable item id + attachment preservation.
- [ ] Conversion presets + ad-hoc variants + focal cropping all tested.
- [ ] Folder ACL matrix exhaustive.
- [ ] Share-link controller end-to-end with access log.
- [ ] All commands behave per spec §20.
- [ ] Pint + PHPStan level 8 + Pest pass.
- [ ] CI matrix green.
- [ ] README + CHANGELOG + UPGRADE-1.0 in place.
- [ ] Tagged `v1.0.0`; GitHub release published.
