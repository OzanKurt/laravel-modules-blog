# `ozankurt/laravel-modules-library` → `ozankurt/laravel-modules-resource-library` v3.0 — Rename Spec

**Repo:** `KurtModules-Library`
**Date:** 2026-05-30
**Status:** Draft → user review pending
**Family:** [KurtModules v2 umbrella](./2026-05-28-kurtmodules-v2-design.md)

---

## 1. Purpose

The existing `ozankurt/laravel-modules-library` v2 is a **SaaS resource library** (nested folders containing videos/files/documents/links with per-folder permissions). The new `ozankurt/laravel-modules-media-library` v1 is a **WordPress-style media bucket** for image/video/audio assets.

To prevent confusion between the two, rename the existing Library module to **Resource Library** in a v3 release. The media library keeps the clean name.

## 2. Scope

### 2.1 What changes

| | v2 | v3 |
|---|---|---|
| Composer name | `ozankurt/laravel-modules-library` | `ozankurt/laravel-modules-resource-library` |
| PHP namespace | `Kurt\Modules\Library\` | `Kurt\Modules\ResourceLibrary\` |
| Service provider | `LibraryServiceProvider` | `ResourceLibraryServiceProvider` |
| Config key | `library` | `resource-library` |
| Facade alias | `Library` | `ResourceLibrary` |
| Console command prefix | `library:*` | `resource-library:*` |
| Table prefix | `library_*` | `resource_library_*` |
| README + docs | "Library" | "Resource Library" |

### 2.2 What stays the same

- Repo name on GitHub stays `KurtModules-Library` (cheap to leave alone).
- Schema shape (column names, relations, indexes).
- Public API surface (model methods, scopes, policy gates).
- Filament resource class names (planned for v2.1).
- Domain event class names — but they move namespace.
- spatie/laravel-translatable + spatie/laravel-medialibrary + Sluggable deps unchanged.

## 3. Migration strategy

### 3.1 Database

Rename all `library_*` tables to `resource_library_*` via a single migration shipped in v3:

```
library_folders                 -> resource_library_folders
library_items                   -> resource_library_items
library_item_versions           -> resource_library_item_versions
library_tags                    -> resource_library_tags
library_item_tag                -> resource_library_item_tag
library_folder_permissions      -> resource_library_folder_permissions
library_access_log              -> resource_library_access_log
```

Migration uses `Schema::rename(...)` per table. Indexes and foreign keys persist across the rename.

### 3.2 Composer

Consumers update `composer.json`:

```diff
-"ozankurt/laravel-modules-library": "^2.0"
+"ozankurt/laravel-modules-resource-library": "^3.0"
```

The old package name is **abandoned** on Packagist (once published) with a pointer to the new name. Until Packagist publishing happens, the existing VCS repository entry in downstream modules continues to work (URL unchanged).

### 3.3 Code

Find-and-replace across consumer code:

| Find | Replace |
|---|---|
| `Kurt\Modules\Library\` | `Kurt\Modules\ResourceLibrary\` |
| `Kurt\\Modules\\Library\\` | `Kurt\\Modules\\ResourceLibrary\\` |
| `config('library.` | `config('resource-library.` |
| `'library:` (artisan signatures) | `'resource-library:` |
| `LibraryServiceProvider` | `ResourceLibraryServiceProvider` |
| `library/*` view names (if any) | `resource-library/*` |

A `resource-library:upgrade-from-library` artisan command optionally automates step 3.3 inside a consumer app — scans for the old namespace usage in `app/`, prints a diff, applies on confirmation.

### 3.4 Branch + tag plan

- Branch `v3.0` off `master`.
- Rename refactor on `v3.0`.
- Shipping migration `2026_05_30_NNNNNN_rename_library_to_resource_library.php`.
- Updated README + CHANGELOG + UPGRADE-3.0.md describing the rename + steps for consumers.
- Tag `v3.0.0` after merge.
- Optional: also tag a `v2.0.1` on the v2 line that does nothing but warn (e.g. via composer scripts post-install message) about the upcoming rename.

## 4. Files to touch

Across the repo:

- `composer.json` — `name`, `description`, `keywords`, `autoload.psr-4`, `autoload-dev.psr-4`, `extra.laravel.providers`.
- `config/library.php` → `config/resource-library.php` (rename file + replace internal references to model FQCNs).
- `src/` — rename `Kurt\Modules\Library\` namespace to `Kurt\Modules\ResourceLibrary\` via mass `sed` + composer autoload regenerate.
- `src/Providers/LibraryServiceProvider.php` → `ResourceLibraryServiceProvider.php`.
- `src/Facades/Library.php` → `ResourceLibrary.php`.
- `src/Console/Commands/*` — update signatures from `library:foo` to `resource-library:foo`.
- All migrations: rename inside `Schema::create(...)` calls in **new follow-up migration**; existing migrations stay untouched for installs already on v2.
- All factories: namespace rename.
- All tests: namespace rename + table assertion fixes.
- `tests/TestCase.php` — provider FQCN.
- `README.md`, `CHANGELOG.md`, `UPGRADE-3.0.md` — full rewrite.
- `.github/workflows/tests.yml` — workflow name + comment refresh.
- `LICENSE.md` — author line unchanged.

## 5. Definition of done

- [ ] All tables renamed via a v3 migration.
- [ ] Namespace + composer name + provider class updated everywhere.
- [ ] All previously-passing tests still pass after refactor.
- [ ] Pint + PHPStan clean.
- [ ] `UPGRADE-3.0.md` provides a step-by-step for consumer migration.
- [ ] Tagged `v3.0.0` on `master`.
- [ ] GitHub release + CHANGELOG entry.
- [ ] Downstream modules' VCS repo references continue to work (URL unchanged).
- [ ] Old v2 line marked deprecated in README + Packagist (when published) → "Use ozankurt/laravel-modules-resource-library instead."

## 6. Non-goals

- No functional feature changes.
- No schema changes beyond table renames.
- No Filament resource changes (still deferred to v3.1 — was v2.1).
- No tooling upgrade (still Laravel 12 + PHP 8.4 + same dev deps).

## 7. References

- [Media Library v1 spec](./2026-05-30-kurtmodules-media-library-v1-spec.md) — the reason for the rename.
- [Umbrella v2 design](./2026-05-28-kurtmodules-v2-design.md).
