# `ozankurt/laravel-modules-library` v2.0 — Spec

**Repo:** `KurtModules-Library`
**Date:** 2026-05-28
**Status:** Draft → user review pending
**Umbrella:** [2026-05-28-kurtmodules-v2-design.md](./2026-05-28-kurtmodules-v2-design.md)

---

## 1. Purpose

A **resource library** module for SaaS apps — not a book/ISBN library. Customers (and the SaaS team) share knowledge with end users by uploading or linking **videos, files, documents, and external URLs**, organised into a **nested folder structure** with **per-folder permissions**. Items support **versioning** so updates don't break existing links. Filament admin for curators.

## 2. Status

KurtModules-Library v1 is empty (`SECURITY.md` only). v2.0 is the **initial release**.

## 3. Composer

```jsonc
{
  "name": "ozankurt/laravel-modules-library",
  "description": "SaaS resource library: nested folders with per-folder permissions, versioned items (video link, file, document, URL).",
  "keywords": ["laravel", "filament", "library", "resources", "knowledge-base"],
  "license": "MIT",
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
    "orchestra/testbench": "^9.0 || ^10.0",
    "pestphp/pest": "^3.0",
    "pestphp/pest-plugin-laravel": "^3.0"
  }
}
```

## 4. Concepts

- **Folder** — node in a tree. Self-referential (`parent_id`). Has a `path` denormalised column for fast ancestry queries (`/root/getting-started/quickstart`).
- **Item** — leaf in a folder. Has a `kind` (`video_link`, `file`, `document`, `external_url`). Each kind stores its payload differently (see §5.4).
- **Version** — every save creates a new version row (for items with mutable payload — files and documents). `current_version_id` points to the active one. Old versions remain downloadable until pruned.
- **Permission** — per-folder Access Control List. Subject is either a single user, a "role" string (consumer-defined — the module never hardcodes roles), or `*` (everyone). Grants one of `view`, `download`, `manage`. Permissions cascade into descendants unless a descendant has its own explicit row for the same subject.
- **Access log** — audit row written on `download` or `view` of an item.
- **Tag** — optional cross-folder grouping; many-to-many with items.

## 5. Tables

```
library_folders
  id, parent_id (nullable, self FK, restrictOnDelete),
  slug (string), name (json — translatable), description (json — translatable, nullable),
  path (string indexed), -- e.g. /quickstart/install
  depth (unsigned int default 0),
  position (unsigned int default 0),
  visibility (string — public|restricted|private),  -- 'restricted' = ACL rules apply
  owner_id (FK users.id, restrictOnDelete),
  item_count (unsigned int default 0),
  created_at, updated_at, deleted_at
  unique(parent_id, slug)
  index(path)

library_items
  id, folder_id (FK library_folders.id, cascadeOnDelete),
  slug (string),
  title (json — translatable), description (json — translatable, nullable),
  kind (string — video_link|file|document|external_url),
  owner_id (FK users.id, restrictOnDelete),
  current_version_id (FK library_item_versions.id, nullable),
  -- denormalised quick-access columns mirrored from current_version
  external_url (string nullable),       -- for kind=video_link or external_url
  mime_type (string nullable),
  byte_size (unsignedBigInt nullable),
  thumbnail_path (string nullable),
  download_count (unsignedBigInt default 0),
  view_count (unsignedBigInt default 0),
  published_at (timestamp nullable),
  created_at, updated_at, deleted_at
  unique(folder_id, slug)
  index(kind)

library_item_versions
  id, item_id (FK library_items.id, cascadeOnDelete),
  version (unsigned int), -- 1, 2, 3…
  external_url (string nullable),
  media_path (string nullable),         -- for file/document; resolved via medialibrary
  mime_type (string nullable),
  byte_size (unsignedBigInt nullable),
  checksum (string nullable),
  changelog (text nullable),
  created_by (FK users.id, restrictOnDelete),
  created_at, updated_at
  unique(item_id, version)

library_tags
  id, slug (unique), name (json — translatable), color (string nullable),
  created_at, updated_at

library_item_tag (pivot)
  item_id (FK), tag_id (FK), primary(item_id, tag_id)

library_folder_permissions
  id, folder_id (FK library_folders.id, cascadeOnDelete),
  subject_type (string),       -- 'user' | 'role' | 'everyone'
  subject_value (string nullable), -- user id (string), role name, null when type=everyone
  capability (string),         -- view | download | manage
  cascade (bool default true), -- cascade to descendants
  created_at, updated_at,
  index(folder_id, subject_type, subject_value)

library_access_log
  id, item_id (FK library_items.id, cascadeOnDelete),
  user_id (FK users.id nullable, setNullOnDelete),
  action (string — view|download),
  ip (string nullable),
  user_agent (string nullable),
  occurred_at (timestamp indexed),
  created_at, updated_at
```

## 6. Enums

```php
enum FolderVisibility: string { case Public='public'; case Restricted='restricted'; case Private='private'; }
enum ItemKind: string { case VideoLink='video_link'; case File='file'; case Document='document'; case ExternalUrl='external_url'; }
enum PermissionSubjectType: string { case User='user'; case Role='role'; case Everyone='everyone'; }
enum Capability: string { case View='view'; case Download='download'; case Manage='manage'; }
enum AccessAction: string { case View='view'; case Download='download'; }
```

## 7. Permission resolution (the meat of the module)

Permission resolution is gate-based; consumer maps **their** roles to the module's `Capability` values. The module never knows which roles exist.

### 7.1 Subject identifiers

The consumer supplies a `LibrarySubjectResolver` (default ships): given a `User`, it returns a set of subject tuples:

```php
return [
    new Subject(PermissionSubjectType::Everyone, null),
    new Subject(PermissionSubjectType::User, (string) $user->getKey()),
    new Subject(PermissionSubjectType::Role, 'editor'),  // for example
    new Subject(PermissionSubjectType::Role, 'admin'),
];
```

A consumer using `spatie/laravel-permission` writes a one-liner that maps roles to the module subject. A consumer with custom roles writes their own resolver. Module defaults: `everyone` + `user(id)` only.

### 7.2 Resolution algorithm

To determine whether `User U` has `Capability C` on `Folder F`:

1. Build the ancestry chain `F, parent(F), parent(parent(F)), …` (uses `path` column).
2. For the nearest ancestor that has any matching ACL row for any subject in `U`'s subject set, evaluate.
3. Match by `subject_type` + `subject_value`, choosing the **highest** capability among matches (`manage > download > view`).
4. If no ACL row matches, fall back to `FolderVisibility`:
   - `public` → all capabilities up to `view`/`download` (manage gated by owner check).
   - `restricted` → deny.
   - `private` → owner-only.

The algorithm runs in a `LibraryAccess` service that is cache-decorated per request (so listing 1000 folders performs ~constant DB hits).

### 7.3 Policies

`FolderPolicy` and `ItemPolicy` delegate every decision to `LibraryAccess::check(User, Folder|Item, Capability)`. `Manage` capability is required to:
- create/move/rename/delete a folder,
- create/update/delete an item (independent of authorship),
- edit ACL rows.

`canAdminLibrary` global gate bypasses everything for staff.

## 8. Models

```
Kurt\Modules\Library\Models\
  Folder (translatable)
  Item   (HasMedia)
  ItemVersion
  Tag    (translatable)
  FolderPermission
  AccessLog
```

Scopes / methods (selected):

- `Folder::scopeRoots()`, `::scopeVisibleTo(User)` (uses `LibraryAccess`).
- `Folder::path()` → string; `::descendantsOf(Folder)`; `::ancestorsOf(Folder)`.
- `Folder::moveTo(?Folder $newParent)` — recomputes `path`/`depth` for the whole subtree in one query.
- `Item::publish()` / `::unpublish()`.
- `Item::newVersion(array $payload, User $by): ItemVersion` — increments `version`, updates denormalised `current_version_id`.
- `Item::recordAccess(?User, AccessAction)`.

Counts kept denormalised (`Folder.item_count`) and rebuilt by `library:recount`.

## 9. Events

```
FolderCreated, FolderUpdated, FolderMoved, FolderDeleted, FolderPermissionChanged
ItemCreated, ItemUpdated, ItemPublished, ItemUnpublished, ItemDeleted, ItemAccessed(item, user?, action)
ItemVersionCreated($item, $version)
TagCreated, TagDeleted
```

## 10. Filament resources

- `FolderResource` (tree view; ACL relation manager for `library_folder_permissions`).
- `ItemResource` (filter by kind; version relation manager; per-row preview action).
- `TagResource`.
- `AccessLogResource` (read-only; daily filter; user filter).

V3 / V4 / V5 parallel namespaces.

## 11. Trait + contract

```
Kurt\Modules\Library\Concerns\IsLibrarySubject
Kurt\Modules\Library\Contracts\LibrarySubject       // for User model: provides display name
Kurt\Modules\Library\Contracts\LibrarySubjectResolver  // app-supplied subject set
```

Default `LibrarySubjectResolver` returns `[Everyone, User($id)]`. Consumers can swap by binding their FQCN in `config('library.subject_resolver')`.

## 12. Config (`config/library.php`)

```php
return [
    'media' => [
        'disk' => env('LIBRARY_MEDIA_DISK', 'public'),
        'allowed_mimes' => ['*'],
        'max_size_kb' => 100_000,
        'conversions' => ['thumb' => [320, 320]],
    ],
    'versions' => [
        'keep_old' => 10, // null = keep all
    ],
    'subject_resolver' => null, // FQCN; null = default
    'access_log' => [
        'enabled' => true,
        'on_view' => false, // log download by default; views opt-in
    ],
    'video_link_providers' => ['youtube', 'vimeo', 'dailymotion', 'loom'],
    'models' => [/* … */],
];
```

## 13. Console commands

- `library:recount` — rebuilds `Folder.item_count`, `Item.view_count`, `Item.download_count`.
- `library:prune-versions` — removes versions older than `config('library.versions.keep_old')`.
- `library:rebuild-paths` — recomputes every folder's `path` and `depth` after a manual DB edit.
- `library:demo` — seeds folders + sample items.

## 14. Test coverage targets

| Suite | Cases |
|---|---|
| Unit | Path recomputation for moves; ACL resolution edge cases; enum casts |
| Feature/Folders | Create root, create nested; move subtree; rename updates `path` cascadingly |
| Feature/Items | Create per `ItemKind`; new-version flow; publish/unpublish; access logging |
| Feature/Permissions | Matrix: each subject type × cascade on/off × ancestor override × visibility fallback |
| Feature/AccessLog | View opt-in toggle; download always logged; nullable user (anonymous) |
| Feature/Filament/V{3,4,5} | Resource smoke; ACL relation manager renders |
| Feature/Recount | `library:recount` rebuilds counters identically |

## 15. Directory layout

```
src/
  Access/
    LibraryAccess.php
    PermissionResolver.php
    SubjectResolver.php
    DefaultSubjectResolver.php
  Concerns/IsLibrarySubject.php
  Console/Commands/{RecountCommand,PruneVersionsCommand,RebuildPathsCommand,DemoCommand}.php
  Contracts/{LibrarySubject,LibrarySubjectResolver}.php
  Enums/{FolderVisibility,ItemKind,PermissionSubjectType,Capability,AccessAction}.php
  Events/{…}
  Filament/{V3,V4,V5}/Resources/{Folder,Item,Tag,AccessLog}Resource.php
  Models/{AccessLog,Folder,FolderPermission,Item,ItemVersion,Tag}.php
  Observers/{Folder,Item,ItemVersion}Observer.php
  Policies/{Folder,Item}Policy.php
  Providers/{LibraryServiceProvider,LibraryFilamentServiceProvider}.php
  Support/PathBuilder.php
  Values/Subject.php
config/library.php
database/factories/…
database/migrations/
  2026_05_28_030001_create_library_folders_table.php
  …030002_create_library_item_versions_table.php  -- yes, before items so FK is satisfied
  …030003_create_library_items_table.php
  …030004_create_library_tags_table.php
  …030005_create_library_item_tag_table.php
  …030006_create_library_folder_permissions_table.php
  …030007_create_library_access_log_table.php
lang/en/library.php
lang/tr/library.php
tests/…
```

## 16. Definition of done

- [ ] Matrix CI green.
- [ ] Move-subtree test: moving a folder with 100 descendants rewrites paths correctly in one query.
- [ ] ACL matrix test exhaustive (subject × cascade × visibility).
- [ ] `library:prune-versions` keeps `keep_old` newest, retires older, never the `current` version.
- [ ] Access-log writes pass under load (idempotent on retry).
- [ ] Filament resources render across V3/V4/V5.
- [ ] Tagged `v2.0.0`.
