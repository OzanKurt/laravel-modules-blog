# KurtModules Filament Admin Resources — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. When building V3 resources load the `filament-v3` skill; V4 → `filament-v4`; V5 → `filament-v5`. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Ship Filament admin resources for all 6 UI-bearing KurtModules (Blog, Forum, Chat, ResourceLibrary, Events, MediaLibrary), supporting Filament v3 + v4 + v5 in parallel, as a minor release per module.

**Architecture:** Each module ships three parallel resource sets under `src/Filament/V3`, `src/Filament/V4`, `src/Filament/V5`, plus a version-dispatching plugin facade the consumer registers on their panel. Runtime registration picks the set matching the installed Filament major via Core's `FilamentVersion`. The cross-version static-analysis/test problem (only one major installed at a time, mutually-exclusive namespaces) is solved with per-version PHPStan configs + Filament-major-guarded Pest tests + a CI matrix axis.

**Tech Stack:** PHP 8.4, Laravel 12, Filament 3/4/5, Pest 3 + Testbench, PHPStan 8, Pint. Reference skills: `filament-v3`, `filament-v4`, `filament-v5`.

**Pilot first:** Blog (smallest clean surface: Post/Category/Tag/Comment). Prove the cross-version CI matrix end-to-end, ship Blog `v2.1.0`, then replicate to the other 5.

---

## Shared architecture (applies to every module)

### Directory layout added per module

```
src/Filament/
  {Module}Plugin.php                 # version-dispatching facade: ::make() returns the right V{n} plugin
  V3/
    {Module}Plugin.php               # implements Filament\Contracts\Plugin; registers V3 resources
    Resources/{...}Resource.php      # Filament v3 resource classes (+ Pages/)
  V4/
    {Module}Plugin.php
    Resources/{...}Resource.php      # Filament v4
  V5/
    {Module}Plugin.php
    Resources/{...}Resource.php      # Filament v5
phpstan-filament-v3.neon             # includes phpstan.neon, scopes Filament analysis to V3
phpstan-filament-v4.neon
phpstan-filament-v5.neon
tests/Feature/Filament/V3/...        # guarded: skip unless FilamentVersion::major() === 3
tests/Feature/Filament/V4/...
tests/Feature/Filament/V5/...
```

### The version-dispatching plugin facade

`src/Filament/{Module}Plugin.php` — what the consumer registers (`->plugin(\Kurt\Modules\Blog\Filament\BlogPlugin::make())`):

```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Filament;

use Filament\Contracts\Plugin;
use Kurt\Modules\Core\Support\FilamentVersion;

final class BlogPlugin
{
    public static function make(): Plugin
    {
        return match (FilamentVersion::major()) {
            5 => new V5\BlogPlugin(),
            4 => new V4\BlogPlugin(),
            3 => new V3\BlogPlugin(),
            default => throw new \RuntimeException('Filament is not installed; cannot register the Blog plugin.'),
        };
    }
}
```

Each `V{n}\{Module}Plugin` implements `Filament\Contracts\Plugin`:

```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Filament\V5;

use Filament\Contracts\Plugin;
use Filament\Panel;

final class BlogPlugin implements Plugin
{
    public function getId(): string
    {
        return 'kurtmodules-blog';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            Resources\PostResource::class,
            Resources\CategoryResource::class,
            Resources\TagResource::class,
            Resources\CommentResource::class,
        ]);
    }

    public function boot(Panel $panel): void {}

    public static function make(): static
    {
        return app(static::class);
    }
}
```

Why a facade + per-version plugin (not auto-registration in the service provider): Filament resources attach to a *panel*, which the consumer owns and configures. A package cannot know which panel to mutate. The clean Filament-native integration is a plugin the consumer adds to their panel. The facade removes the version-choice burden — one `BlogPlugin::make()` call resolves correctly whether the app runs Filament 3, 4, or 5.

### Cross-version static analysis (the hard part)

Only one Filament major is installed at a time (CI matrix cell, or local dev). The other two version dirs reference classes that don't exist under the installed major → PHPStan and even autoload would fatal. Solution:

1. **Base `phpstan.neon` excludes ALL three Filament version dirs** from the default analysis (the non-Filament `src/` is always analysable):

```neon
includes:
    - vendor/larastan/larastan/extension.neon
parameters:
    level: 8
    paths:
        - src
    excludePaths:
        - src/Filament/V3
        - src/Filament/V4
        - src/Filament/V5
    tmpDir: build/phpstan
```

2. **Three thin per-version configs** each re-include the base and add back ONLY the matching dir:

`phpstan-filament-v5.neon`:
```neon
includes:
    - phpstan.neon
parameters:
    excludePaths!:
        - src/Filament/V3
        - src/Filament/V4
```
(The `!` overrides the parent's `excludePaths` so only V3+V4 are excluded → V5 analysed.) Mirror for v3 (exclude V4+V5) and v4 (exclude V3+V5).

3. **CI runs the config matching the installed major.** Local dev (Filament v5 vendored) uses `phpstan-filament-v5.neon`. The base `composer stan` script stays `phpstan analyse` (non-Filament src only) so a plain local run never trips on uninstalled-version dirs.

### Cross-version tests

Resource smoke tests live under `tests/Feature/Filament/V{n}/`. Each test file guards on the installed major so only the relevant set runs:

```php
beforeEach(function () {
    if (\Kurt\Modules\Core\Support\FilamentVersion::major() !== 5) {
        $this->markTestSkipped('Filament v5 not installed.');
    }
});
```

Each version's tests assert the panel registers the resources and the list/create/edit pages render (Livewire smoke). Use Filament's testing helpers per the version skill (`filament-v{n}` documents the correct assertions — they differ across majors).

### TestCase + panel for Filament tests

Filament resource tests need a booted Filament panel. Add a test-only panel provider under `tests/Fixtures/AdminPanelProvider.php` registering the module plugin, and register it in `tests/TestCase.php`'s providers when Filament is installed. The `filament-v{n}` skill shows the minimal panel boot for tests.

### CI matrix

Extend `.github/workflows/tests.yml` with a Filament axis:

```yaml
matrix:
  php: ['8.4']
  laravel: ['12.*']
  filament: ['3.*', '4.*', '5.*']
  include:
    - laravel: '12.*'
      testbench: '10.*'
steps:
  ...
  - name: Require Filament ${{ matrix.filament }}
    run: composer require --no-update "filament/filament:${{ matrix.filament }}"
  - name: Install
    run: composer update --prefer-stable --prefer-dist --no-interaction
  - name: Pint
    run: vendor/bin/pint --test
  - name: PHPStan (Filament ${{ matrix.filament }})
    run: |
      MAJOR=$(echo "${{ matrix.filament }}" | cut -d. -f1)
      vendor/bin/phpstan analyse -c phpstan-filament-v${MAJOR}.neon --memory-limit=2G
  - name: Pest
    run: vendor/bin/pest
```

Pint must be clean across all three version dirs regardless of installed major (Pint is syntactic, not type-aware, so it analyses all files fine). PHPStan uses the per-major config. Pest auto-skips the non-installed versions.

### Resource design conventions (all versions, all modules)

- Translatable fields: use the version-appropriate translated input. For v5/v4 the `filament/spatie-laravel-translatable-plugin` is the clean path; if that adds dependency weight, a per-locale Tabs layout with `TextInput`/`Textarea` per locale is acceptable. Pick the Tabs approach to avoid an extra dep unless the module already requires the plugin.
- Enum-backed fields: `Select::make(...)->options(EnumClass::class)` (Filament supports backed enums directly).
- Media (Blog cover, MediaLibrary items, etc.): use `SpatieMediaLibraryFileUpload` from `filament/spatie-laravel-media-library-plugin` (add to require-dev) OR a plain `FileUpload` wired to the model's media collection. Prefer the Spatie plugin for media-bearing models since they already depend on spatie/medialibrary.
- Tables: key columns + a status/badge column + searchable title + common filters (status, date range). Bulk delete + per-row edit/delete actions.
- Keep resource bodies focused; one resource per file, Pages under `Resources/{Name}Resource/Pages/`.

### Per-module resource inventory + version bumps

| Module | Repo | New version | Resources |
|---|---|---|---|
| Blog | KurtModules-Blog | v2.1.0 | Post, Category, Tag, Comment |
| Forum | KurtModules-Forum | v2.1.0 | Board, Thread, Post, ModerationReport, Badge, UserBadge |
| Chat | KurtModules-Chat | v2.2.0 | Conversation, Message, Presence |
| ResourceLibrary | KurtModules-Library | v3.1.0 | Folder, Item, Tag, AccessLog |
| Events | KurtModules-Events | v1.1.0 | Event, TicketType, Order, Application, DiscountCode, DocumentVerification, Refund, Waitlist |
| MediaLibrary | KurtModules-MediaLibrary | v1.1.0 | MediaLibraryItem, MediaLibraryFolder, MediaLibraryTag, ShareLink |

Each module's per-module spec (`docs/superpowers/specs/2026-05-2{8,9}-kurtmodules-{module}-*-spec.md`, and `2026-05-30-...media-library...`) lists the intended resource feature set — consult it for which fields/columns/filters each resource needs.

---

## Pilot: Blog v2.1.0 (prove the pattern)

Working dir: `D:\Code\Projects\KurtModules-Blog`. PHP 8.4: `C:/laragon/bin/php/php-8.4.5-nts-Win32-vs17-x64/php.exe`. Branch `feature/filament-admin` off master.

### Task P0: Branch + composer

- [ ] Branch:
```bash
git fetch --all --prune && git switch master && git pull && git switch -c feature/filament-admin
```
- [ ] Add to `composer.json` require-dev (if not present): `filament/spatie-laravel-media-library-plugin: ^3.0 || ^4.0 || ^5.0` (Blog Post has a cover media collection). Run composer update. Commit.

### Task P1: PHPStan per-version configs

- [ ] Edit `phpstan.neon` to add the `excludePaths` for the three Filament version dirs (see shared architecture).
- [ ] Create `phpstan-filament-v3.neon`, `phpstan-filament-v4.neon`, `phpstan-filament-v5.neon` per the template.
- [ ] Commit `chore(blog): phpstan per-filament-version configs`.

### Task P2: V5 resources (load filament-v5 skill)

- [ ] Build `src/Filament/V5/Resources/{Post,Category,Tag,Comment}Resource.php` + their Pages, per the `filament-v5` skill's namespace/API guidance. Translatable fields via per-locale Tabs. Post cover via `SpatieMediaLibraryFileUpload`. Status/type via enum selects. Comment approval bulk actions.
- [ ] Build `src/Filament/V5/BlogPlugin.php` registering the four resources.
- [ ] Build `src/Filament/BlogPlugin.php` version-dispatching facade.
- [ ] Add `tests/Fixtures/AdminPanelProvider.php` (test panel registering `BlogPlugin::make()`) + wire into `tests/TestCase.php` providers.
- [ ] Add `tests/Feature/Filament/V5/` smoke tests (guarded on major===5): each resource's List page renders; Post create page renders + validates required title; Comment approve action works.
- [ ] Verify: `vendor/bin/pint --test` (all dirs), `vendor/bin/phpstan analyse -c phpstan-filament-v5.neon --memory-limit=2G`, `vendor/bin/pest`. All green (v5 is the vendored local major, so its tests run; V3/V4 tests skip).
- [ ] Commit `feat(blog): add Filament v5 admin resources + version-dispatching plugin`.

### Task P3: V4 resources (load filament-v4 skill)

- [ ] Build `src/Filament/V4/Resources/*` + `V4/BlogPlugin.php`, using filament-v4 namespaces (`Filament\Schemas\Schema`, `Filament\Actions`, `->recordActions()`/`->toolbarActions()`, Get/Set in `Filament\Schemas\Components\Utilities`). Mirror the V5 resources' feature set.
- [ ] `tests/Feature/Filament/V4/` guarded on major===4.
- [ ] Verify locally what you can: `vendor/bin/pint --test` (covers V4 files syntactically). PHPStan against V4 can't run locally (v5 vendored) — that's the CI matrix's job; do a careful self-review against the filament-v4 skill instead. Note this in the commit.
- [ ] Commit `feat(blog): add Filament v4 admin resources`.

### Task P4: V3 resources (load filament-v3 skill)

- [ ] Build `src/Filament/V3/Resources/*` + `V3/BlogPlugin.php`, using filament-v3 namespaces (`Filament\Forms\Form`, `Filament\Infolists\Infolist`, split actions `Filament\Tables\Actions\*` + `Filament\Actions\*`, Get/Set in `Filament\Forms`). Mirror feature set.
- [ ] `tests/Feature/Filament/V3/` guarded on major===3.
- [ ] `vendor/bin/pint --test`. Self-review against filament-v3 skill. Commit `feat(blog): add Filament v3 admin resources`.

### Task P5: CI matrix + docs + release

- [ ] Extend `.github/workflows/tests.yml` with the Filament axis + per-major PHPStan step (see shared architecture).
- [ ] README: add a "Filament admin" section showing `->plugin(BlogPlugin::make())`.
- [ ] CHANGELOG: prepend `## [2.1.0] - 2026-05-30` with the Filament resources entry.
- [ ] Push, open PR. **Watch the full matrix** — this is the real proof: v3 cell exercises V3 resources + V3 PHPStan, v4 cell V4, v5 cell V5. Fix per-version namespace errors the matrix surfaces (this is expected — local couldn't catch v3/v4 type errors). Iterate until all three cells green.
- [ ] Merge, tag `v2.1.0`, release.

**Checkpoint:** After Blog v2.1.0 is green + released, the cross-version pattern is proven. Report before fanning out.

---

## Fan-out: remaining 5 modules

Once Blog proves the pattern, replicate per module. Each module's subagent:

1. Branches `feature/filament-admin` off master.
2. Copies the PHPStan per-version config pattern + the CI matrix change from Blog (adapt paths/names).
3. Builds V5 → V4 → V3 resources for that module's resource inventory (table above), loading the matching `filament-v{n}` skill per version, consulting the module's spec for each resource's fields/columns/filters.
4. Adds the version-dispatching `{Module}Plugin` facade + per-version plugins + test panel + guarded smoke tests.
5. Adds the media plugin dep only if the module has media-bearing models (Forum Post, Events documents, MediaLibrary items — yes; Chat/ResourceLibrary — already depend on medialibrary; Blog — done).
6. CI matrix green across 3 cells → merge → tag the module's minor version → release.

Order: Forum → ResourceLibrary → Chat → MediaLibrary → Events (Events last; largest resource set).

---

## Definition of done (per module)

- [ ] Three parallel resource sets under `src/Filament/V{3,4,5}/Resources/`.
- [ ] Version-dispatching `{Module}Plugin::make()` facade + per-version `Plugin` classes.
- [ ] Per-version PHPStan configs; base config excludes all three version dirs.
- [ ] Guarded smoke tests per version; only the installed major's run.
- [ ] CI matrix green across Filament 3/4/5 cells.
- [ ] README "Filament admin" section + CHANGELOG entry.
- [ ] Tagged minor release + GitHub release.

## Risks / notes

- v3/v4 type-correctness can only be validated in CI (local has v5 vendored). Expect the first PR matrix run to surface namespace mistakes in V3/V4 dirs; the per-version skills minimise these but CI is the backstop. Budget 1-3 fix iterations per module's first PR.
- Pint is version-agnostic (syntactic) so it gates all three dirs locally.
- If a module's translatable fields make per-locale Tabs unwieldy, the `filament/spatie-laravel-translatable-plugin` is the fallback (add to require-dev). Decide per module; default to Tabs to avoid the dep.
- Maintenance cost: 3 parallel resource sets is real ongoing burden. Acceptable per the spec's matrix mandate; revisit if Filament v3 EOLs.
