# `ozankurt/laravel-modules-media-library` v1.0 — Spec

**Repo:** `KurtModules-MediaLibrary` (to be created)
**Date:** 2026-05-30
**Status:** Draft → user review pending
**Family:** [KurtModules v2 umbrella](./2026-05-28-kurtmodules-v2-design.md)

---

## 1. Purpose

A **WordPress-style central media bucket** for Laravel SaaS apps. Headless backend that wraps `spatie/laravel-medialibrary` as the storage engine and adds:

- Tenant-aware ownership (User / Team / Organization via polymorphic owner).
- Nested folder tree with per-folder ACL (same shape as ResourceLibrary).
- WordPress-flavour item metadata (title / alt / caption / description / focal point / blurhash / dominant color / palette / EXIF / AI tags).
- Named-preset + ad-hoc conversions with focal-point-aware cropping.
- Versioning with stable item IDs so polymorphic links survive replacements.
- Polymorphic many-to-many attachment to any consumer model with roles (cover / gallery / social / attachment / etc.).
- Server-proxy AND direct-to-S3 upload flows.
- Public share links with TTL + revoke + access log.
- Tag taxonomy, structured filters, saved searches.
- Optional Scout / EXIF / OCR / AI tagger / blurhash / palette extractor contracts (no third-party calls in module).

v1.0 is **headless**. Filament v3/v4/v5 admin (a WordPress-grid-style UI) lands in v1.1.

## 2. Family relationship

Sibling to Core/Blog/Library/Forum/Chat/Events. Same conventions per the [umbrella v2 design](./2026-05-28-kurtmodules-v2-design.md). Depends on Core only. **Companion change**: existing `ozankurt/laravel-modules-library` v2 is renamed to `ozankurt/laravel-modules-resource-library` v3 — see [Library rename spec](./2026-05-30-kurtmodules-resource-library-rename-spec.md).

## 3. Stack & metadata

| | |
|---|---|
| Composer name | `ozankurt/laravel-modules-media-library` |
| PHP namespace | `Kurt\Modules\MediaLibrary\` |
| Table prefix | `media_library_` |
| PHP | `^8.4` |
| Laravel | `^12.0 \|\| ^13.0` |
| Core | `ozankurt/laravel-modules-core: ^2.0` |
| Spatie media | `spatie/laravel-medialibrary: ^11.0` (hard runtime dep — storage engine) |
| Translatable | `spatie/laravel-translatable: ^6.11` |
| Sluggable | `cviebrock/eloquent-sluggable: ^11.0 \|\| ^12.0` |
| Image processing | `intervention/image: ^3.0` |
| Filament | `^3.0 \|\| ^4.0 \|\| ^5.0` (require-dev; resources land v1.1) |
| Tests | Pest 3 + Testbench + PHPStan 8 + Pint, GH Actions CI |

## 4. Architecture: 5 sub-areas + cross-cutting

Code is organised by responsibility, not technical layer:

```
src/
  Catalog/                  # Items, folders, tags, owner relationships
    Enums/{Visibility, ItemKind, AttachmentRole}.php
    Models/{MediaLibraryItem, MediaLibraryFolder, MediaLibraryTag, MediaLibraryAttachment, MediaLibrarySavedSearch, MediaLibraryStorage}.php
    Observers/{MediaLibraryItemObserver, MediaLibraryFolderObserver}.php
    Support/{FolderPathBuilder, ItemSlugger}.php
  Storage/                  # Spatie integration + uploads + conversions + variants + versions
    Enums/{PendingUploadStatus}.php
    Models/{MediaLibraryVersion, MediaLibraryVariant, MediaLibraryPendingUpload}.php
    Support/{UploadCoordinator, ConversionEngine, VariantGenerator, FocalPointCropper, ReplaceCoordinator}.php
    Contracts/{ExifExtractor, OcrExtractor, AiTagger, BlurhashGenerator, PaletteExtractor}.php
    Extractors/{DefaultExifExtractor, InterventionBlurhashGenerator, InterventionPaletteExtractor}.php
  Sharing/                  # Share links + access log
    Enums/{ShareAbility, AccessAction}.php
    Models/{ShareLink, AccessLogEntry}.php
    Support/{ShareLinkSigner, ShareLinkResolver}.php
  Access/                   # Folder ACL — reuses ResourceLibrary's pattern
    Models/{FolderPermission}.php
    Contracts/{MediaSubjectResolver}.php
    Support/{DefaultSubjectResolver, FolderPermissionResolver, MediaLibraryAccess}.php
    Values/Subject.php
  Search/                   # Default DB scopes + Scout adapter
    Contracts/ScoutAdapter.php
    Support/DefaultSearchEngine.php

  Concerns/                 # Consumer-side traits
    HasMediaLibraryItems.php
    IsMediaLibraryOwner.php
  Contracts/                # Person-side contracts
    MediaLibraryOwner.php
  Console/Commands/
  Events/                   # Domain events (Laravel events)
  Exceptions/
  Notifications/            # Optional Laravel Notifications
  Policies/
  Providers/MediaLibraryServiceProvider.php
  Support/MediaLibrary.php  # Top-level facade
  Facades/MediaLibrary.php  # Thin facade
```

## 5. Data model

All tables: `bigIncrements` id, `softDeletes()` where domain rows, `timestamps()`. JSON for translatable text + flexible metadata. Money / count columns use `unsignedBigInteger` / `unsignedInteger`.

### 5.1 Catalog

```
media_library_folders                -- owner-scoped nested folder tree
  id, owner_type (string), owner_id (unsignedBigInteger),
  parent_id (nullable, self FK, restrictOnDelete),
  slug (string), name (json — translatable), description (json — translatable, nullable),
  path (string indexed),                                  -- e.g. /clients/acme/2026
  depth (unsignedInteger default 0),
  position (unsignedInteger default 0),
  visibility (string — enum: private|restricted|public, default 'private'),
  item_count (unsignedBigInteger default 0),              -- denormalised, direct items
  descendant_count (unsignedBigInteger default 0),        -- denormalised, recursive
  created_by (FK users nullable, nullOnDelete),
  created_at, updated_at, deleted_at
  unique(owner_type, owner_id, parent_id, slug)
  index(owner_type, owner_id, path)

media_library_items                  -- WordPress-style media row
  id, owner_type, owner_id,
  folder_id (nullable, FK media_library_folders, nullOnDelete),
  storage_id (FK media_library_storage, cascadeOnDelete),  -- thin HasMedia host (see §5.2)
  slug (string, owner-scoped unique),
  title (json — translatable), alt_text (json — translatable, nullable),
  caption (json — translatable, nullable), description (json — translatable, nullable),
  filename (string),                                       -- original filename for display + downloads
  mime_type (string),
  byte_size (unsignedBigInteger),
  width (unsignedInteger nullable), height (unsignedInteger nullable),
  duration_seconds (decimal(8,3) nullable),                -- audio/video
  focal_x (decimal(4,3) default 0.500),                    -- 0..1
  focal_y (decimal(4,3) default 0.500),
  dominant_color (string char(7) nullable),                -- '#rrggbb'
  palette (json nullable),                                 -- ['#hex', ...]
  blurhash (string nullable),
  exif (json nullable),
  ai_tags (json nullable),
  extracted_text (text nullable),                          -- OCR
  download_count (unsignedBigInteger default 0),
  view_count (unsignedBigInteger default 0),
  metadata (json nullable),
  created_by (FK users nullable, nullOnDelete),
  updated_by (FK users nullable, nullOnDelete),
  created_at, updated_at, deleted_at
  unique(owner_type, owner_id, slug)
  index(owner_type, owner_id, folder_id)
  index(mime_type)

media_library_storage                -- one thin HasMedia host row per item; isolates our spatie collection from consumer's
  id, item_uid (uuid, unique),                             -- used for spatie collection routing
  created_at, updated_at

media_library_tags
  id, owner_type, owner_id,
  slug, name (json — translatable),
  color (string nullable),
  position (unsignedInteger default 0),
  created_at, updated_at
  unique(owner_type, owner_id, slug)

media_library_item_tag               -- pivot
  item_id, tag_id, primary(item_id, tag_id)

media_library_attachments            -- polymorphic many-to-many to consumer models
  id, item_id (FK media_library_items, cascadeOnDelete),
  attachable_type (string), attachable_id (unsignedBigInteger),
  role (string default 'attachment'),                      -- cover|gallery|social|attachment|...
  position (unsignedInteger default 0),
  created_at, updated_at
  index(attachable_type, attachable_id, role)
  index(item_id)

media_library_saved_searches
  id, user_id (FK users, cascadeOnDelete),
  name (string), filters (json),
  created_at, updated_at
```

### 5.2 Storage

```
media_library_versions               -- audit of file replacements per item
  id, item_id (FK media_library_items, cascadeOnDelete),
  spatie_media_id (unsignedBigInteger),                    -- points at the prior spatie media row; not constrained because spatie's row may be hard-deleted later
  filename (string), mime_type (string), byte_size (unsignedBigInteger),
  changelog (text nullable),
  created_by (FK users nullable, nullOnDelete),
  created_at, updated_at

media_library_variants               -- ad-hoc derived images cached for reuse
  id, item_id (FK media_library_items, cascadeOnDelete),
  key (string),                                            -- canonical key from spec, e.g. '300x200-crop-focal'
  spec (json),                                             -- width, height, fit, focal, format, quality
  path (string),                                           -- relative path on disk
  mime_type (string), byte_size (unsignedBigInteger),
  last_used_at (timestamp nullable),
  generated_at (timestamp),
  created_at, updated_at
  unique(item_id, key)

media_library_pending_uploads        -- presigned-S3 in-flight uploads
  id, owner_type, owner_id,
  upload_id (uuid, unique),
  filename (string), mime_type (string), byte_size (unsignedBigInteger nullable),
  driver (string default 's3'),
  driver_payload (json),                                   -- presigned URL + fields + key
  status (string — enum: pending|completed|cancelled|expired),
  completed_at (timestamp nullable), expires_at (timestamp),
  created_by (FK users nullable, nullOnDelete),
  created_at, updated_at
  index(status, expires_at)
```

### 5.3 Sharing

```
media_library_share_links
  id, item_id (nullable, FK media_library_items, cascadeOnDelete),
  folder_id (nullable, FK media_library_folders, cascadeOnDelete),  -- XOR with item_id
  token (string unique),                                  -- url-safe random 32 chars
  abilities (json),                                       -- ['view'] or ['view','download']
  invitee_email (string nullable),
  expires_at (timestamp nullable),                        -- null = no expiry
  revoked_at (timestamp nullable),
  access_count (unsignedBigInteger default 0),
  last_accessed_at (timestamp nullable),
  last_accessed_ip (string nullable),
  created_by (FK users nullable, nullOnDelete),
  created_at, updated_at, deleted_at

media_library_access_log
  id, item_id (nullable, FK media_library_items, cascadeOnDelete),
  share_link_id (nullable, FK media_library_share_links, nullOnDelete),
  user_id (nullable, FK users, nullOnDelete),
  action (string — enum: view|download|upload|replace|delete),
  ip (string nullable), user_agent (string nullable),
  occurred_at (timestamp indexed),
  created_at, updated_at
```

### 5.4 Access (folder ACL)

```
media_library_folder_permissions     -- same shape as resource library
  id, folder_id (FK media_library_folders, cascadeOnDelete),
  subject_type (string — enum: user|role|everyone),
  subject_value (string nullable),                        -- user id (string), role name, or null for 'everyone'
  capability (string — enum: view|download|manage),
  cascade (boolean default true),
  created_at, updated_at
  index(folder_id, subject_type, subject_value)
```

## 6. Enums

```php
namespace Kurt\Modules\MediaLibrary\Catalog\Enums;
enum Visibility: string { case Private='private'; case Restricted='restricted'; case Public='public'; }
enum ItemKind: string { case Image='image'; case Video='video'; case Audio='audio'; case Document='document'; case Archive='archive'; case Other='other'; }
enum AttachmentRole: string {
    case Cover='cover'; case Social='social'; case Gallery='gallery'; case Thumbnail='thumbnail';
    case Attachment='attachment'; case Hero='hero'; case Logo='logo'; case Favicon='favicon';
}

namespace Kurt\Modules\MediaLibrary\Storage\Enums;
enum PendingUploadStatus: string { case Pending='pending'; case Completed='completed'; case Cancelled='cancelled'; case Expired='expired'; }

namespace Kurt\Modules\MediaLibrary\Sharing\Enums;
enum ShareAbility: string { case View='view'; case Download='download'; }
enum AccessAction: string { case View='view'; case Download='download'; case Upload='upload'; case Replace='replace'; case Delete='delete'; }

namespace Kurt\Modules\MediaLibrary\Access\Enums;
enum Capability: string {
    case View='view'; case Download='download'; case Manage='manage';
    public function rank(): int { return match($this) { self::View => 1, self::Download => 2, self::Manage => 3 }; }
}
enum SubjectType: string { case User='user'; case Role='role'; case Everyone='everyone'; }
```

## 7. Spatie integration shape

### 7.1 `MediaLibraryStorage` host model

A tiny `HasMedia` model:

```php
namespace Kurt\Modules\MediaLibrary\Catalog\Models;

use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

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
            $conversion = $this->addMediaConversion($name)
                ->width((int) ($spec['width'] ?? 0))
                ->height((int) ($spec['height'] ?? 0));
            if (($spec['fit'] ?? 'fit') === 'crop') {
                $conversion->fit('crop');
            }
        }
    }
}
```

### 7.2 Why this design

- Isolates our spatie collection (`mli`) from any other `HasMedia` use the consumer has.
- Each `MediaLibraryItem` has exactly one `MediaLibraryStorage` host owning one spatie Media file.
- Polymorphic attachments link **to `MediaLibraryItem.id`**, not to the spatie Media. Replacing the file via `replaceWith()` swaps the spatie Media under a stable item id — every existing attachment continues to resolve.
- Versioning is captured in `media_library_versions` (one row per replacement). The current `MediaLibraryStorage` always points at the latest spatie Media; old spatie Media rows can be retained (configurable retention) and pruned via `media-library:prune-versions`.

### 7.3 Spatie collection naming

The single collection `mli` per host. The host carries a unique `item_uid` UUID column so that conversion paths land under deterministic prefixes (`mli/{item_uid}/...`).

## 8. Upload flow

### 8.1 Server-proxy upload

`MediaLibrary::upload(UploadedFile $file, MediaLibraryOwner $owner, ?Folder $folder = null, array $attributes = []): MediaLibraryItem`:

1. Persist `MediaLibraryStorage` host with a fresh `item_uid`.
2. Attach the spatie Media file to the `mli` collection on the host.
3. Persist `MediaLibraryItem` with file metadata + owner + folder.
4. Run post-processing pipeline (sync): dimensions, dominant_color, blurhash, palette. Optional async pipeline for EXIF / AI tagging / OCR when contracts are bound (dispatched as queued jobs).
5. Increment denormalised `folder.item_count` and ancestor `descendant_count`.
6. Dispatch `ItemUploaded(item)`.

### 8.2 Presigned direct-to-S3

`MediaLibrary::initiateUpload(MediaLibraryOwner $owner, array $filenameMeta): PendingUpload`:

1. Validate mime_type + byte_size against `config('media-library.uploads.max_size_kb')` and allowed mimes.
2. Generate `upload_id` (uuid).
3. Create presigned PUT URL via `Storage::disk('s3')->temporaryUploadUrl(...)`.
4. Persist `media_library_pending_uploads` row with status=Pending + expires_at = now + `config('media-library.uploads.presigned_ttl_seconds')`.
5. Return PendingUpload row (containing `upload_id` + presigned URL + headers + key).

Client uploads to S3 directly. On success, client calls:

`MediaLibrary::completeUpload(string $uploadId): MediaLibraryItem`:

1. Look up PendingUpload row; verify status Pending + not expired.
2. Verify the object exists on the S3 disk + read final byte_size + mime_type.
3. Persist `MediaLibraryStorage` host + attach the existing S3 object as the spatie Media file (using spatie's `addMediaFromDisk` or `addMediaFromUrl`).
4. Persist `MediaLibraryItem` + run sync post-processing.
5. Mark PendingUpload completed_at + status=Completed.

`MediaLibrary::cancelUpload(string $uploadId)`: marks PendingUpload Cancelled.

A scheduled `media-library:expire-pending-uploads` command marks Pending rows past expiry as Expired.

### 8.3 Default tenancy

When `owner` is omitted, default to the authed user resolved via the `MediaSubjectResolver` contract's `defaultOwner()` method. The DefaultSubjectResolver returns `auth()->user()` or throws `OwnerNotResolved` when unauthenticated.

## 9. Replace flow (`MediaLibraryItem::replaceWith`)

`MediaLibrary::replace(MediaLibraryItem $item, UploadedFile|PendingUpload $new, string $changelog): MediaLibraryItem` is the public entry point.

Internally `ReplaceCoordinator`:

1. Capture current spatie Media row id (will become the previous version reference).
2. Persist `media_library_versions` row pointing at the current spatie Media id + filename + mime + byte_size + changelog.
3. Attach the new file (server proxy) OR finalize the PendingUpload (S3 direct).
4. Swap the `MediaLibraryStorage` host's spatie Media — delete the old spatie Media row only if `config('media-library.versions.hard_delete_old_files')` is true; otherwise leave it for the prune command.
5. Recompute dimensions / dominant_color / blurhash / palette.
6. Regenerate named conversions for the new file.
7. Invalidate `media_library_variants` rows for this item (or queue regeneration).
8. Dispatch `ItemReplaced(item, previousSpatieMediaId)`.

The **item id stays the same**. All `media_library_attachments` rows continue to resolve.

`media-library:prune-versions {item?}` deletes spatie Media rows older than `config('media-library.versions.keep_old', 10)` newest, never deleting the current.

## 10. Conversions

### 10.1 Named presets

`config('media-library.conversions')`:

```php
return [
    'thumb' => ['width' => 320, 'height' => 320, 'fit' => 'crop'],
    'cover' => ['width' => 1200, 'height' => 630, 'fit' => 'crop'],
    'social' => ['width' => 1600, 'height' => 900, 'fit' => 'crop'],
];
```

`MediaLibraryItem::url(?string $conversion = null): string`:

- `null` → original.
- preset name → spatie conversion URL.
- ad-hoc → see §10.2.

When the conversion is `fit=crop` and item has non-default focal_x/y, `FocalPointCropper` applies a focal crop instead of a centered crop. Implemented by extending the conversion via spatie's `manipulations()->manualCrop(...)` calculated from focal point + target dimensions.

### 10.2 Ad-hoc variants

`MediaLibraryItem::variant(array $spec): MediaLibraryVariant`:

```php
$spec = ['width' => 600, 'height' => 400, 'fit' => 'fit', 'format' => 'webp', 'quality' => 80];
```

1. Canonicalize the spec into a deterministic `key` (e.g. `600x400-fit-webp-q80`).
2. Look up `media_library_variants(item_id, key)` — if present, update `last_used_at` and return.
3. Otherwise generate via `VariantGenerator` (uses Intervention). Persist a variant row pointing at the cached file on disk.
4. Dispatch `VariantGenerated(variant)`.

`media-library:prune-variants {item?}` removes variant rows + cache files where `last_used_at < now() - config('media-library.variants.unused_days')`.

## 11. Linking to consumer models

### 11.1 `HasMediaLibraryItems` trait

```php
trait HasMediaLibraryItems
{
    public function mediaItemAttachments(): MorphMany
    {
        return $this->morphMany(MediaLibraryAttachment::class, 'attachable');
    }

    public function mediaItems(?string $role = null): MorphToMany
    {
        $relation = $this->morphToMany(MediaLibraryItem::class, 'attachable', 'media_library_attachments', null, 'item_id')
            ->withPivot(['role', 'position'])
            ->withTimestamps()
            ->orderBy('media_library_attachments.position');

        if ($role !== null) {
            $relation->wherePivot('role', $role);
        }

        return $relation;
    }

    public function attachMediaItem(MediaLibraryItem $item, string $role = 'attachment', ?int $position = null): MediaLibraryAttachment
    {
        $position ??= $this->mediaItemAttachments()->where('role', $role)->max('position') + 1;
        return MediaLibraryAttachment::create([
            'item_id' => $item->id,
            'attachable_type' => $this->getMorphClass(),
            'attachable_id' => $this->getKey(),
            'role' => $role,
            'position' => $position,
        ]);
    }

    public function detachMediaItem(MediaLibraryItem $item, ?string $role = null): void
    {
        $q = $this->mediaItemAttachments()->where('item_id', $item->id);
        if ($role !== null) $q->where('role', $role);
        $q->delete();
    }

    public function coverItem(): ?MediaLibraryItem { return $this->mediaItems('cover')->first(); }
    public function socialItem(): ?MediaLibraryItem { return $this->mediaItems('social')->first(); }
}
```

### 11.2 Consumer-side stable identifier

Consumer code references **`MediaLibraryItem::id`** (or the cover/social/gallery role). Never a `media_library_item_id` foreign-key column. Replacements keep the id stable, so existing references continue to work.

## 12. Folder ACL (reuses ResourceLibrary pattern)

### 12.1 `MediaSubjectResolver` contract

```php
interface MediaSubjectResolver
{
    /** @return array<int, Subject> */
    public function subjects(?Authenticatable $user): array;

    public function defaultOwner(?Authenticatable $user): MediaLibraryOwner;
}
```

A single resolver class is bound in `config('media-library.subject_resolver')`. Default resolver returns `[Everyone, User($id)]` + treats authed user as the default owner.

Consumers integrating with ResourceLibrary can ship a single shared resolver that satisfies both modules' subject contracts.

### 12.2 `FolderPermissionResolver`

Identical algorithm to ResourceLibrary §7.2:

1. Walk ancestry from the folder upward.
2. For the nearest ancestor with a matching ACL row, pick the highest capability across matching subjects.
3. If no row matches, fall back to `Folder.visibility` (public → download for all; restricted → deny; private → owner only).

### 12.3 `MediaLibraryAccess` cached check

Per-request memoised wrapper. `MediaLibraryAccess::check(?Auth $u, Folder|MediaLibraryItem $target, Capability $needed): bool`. Items inherit their folder's permissions; orphan items (folder_id null) are owner-only.

## 13. Sharing

### 13.1 Share links

`MediaLibrary::shareItem(MediaLibraryItem $item, int $expiresInSeconds, array $abilities = ['view'], ?string $invitee = null): string` and `MediaLibrary::shareFolder(Folder $folder, …)`:

1. Generate url-safe `token` (32 chars).
2. Persist `media_library_share_links` row.
3. Return full URL using the route registered when `config('media-library.routes.share_enabled')` is true.

Route: `/media-library/share/{token}`. Controller:

1. Look up token; reject if missing / revoked / past expiry.
2. Verify requested ability is in stored abilities.
3. Increment `access_count`, set `last_accessed_at`, persist `media_library_access_log` row.
4. For items: stream the file (`view` → display; `download` → attachment header). For folders: render a minimal JSON manifest of contained items (consumer wires UI on top).

`MediaLibrary::revokeShare(string $token)`. `MediaLibraryItem::activeShares(): Collection`.

### 13.2 Scheduled expiry

`media-library:expire-shares` marks share links past `expires_at` as effectively expired (no row deletion, just check on access). Optional GDPR-style hard-delete is `media-library:prune-shares` retaining expired rows for N days.

## 14. Search + tagging

### 14.1 Default scopes

```php
MediaLibraryItem::query()
    ->byOwner($owner)                 // matches owner_type + owner_id
    ->byFolder($folder, recursive: true)
    ->byTag($tagOrSlug)
    ->byMimeType('image/*')           // wildcard support
    ->byDateRange($from, $to)
    ->search('autumn beach');          // LIKE on title+alt+caption+description across translations
```

### 14.2 Scout adapter (optional)

```php
namespace Kurt\Modules\MediaLibrary\Search\Contracts;

interface ScoutAdapter
{
    public function index(MediaLibraryItem $item): void;
    public function unindex(MediaLibraryItem $item): void;
    /** @return Collection<int, MediaLibraryItem> */
    public function search(string $query, array $filters = [], int $limit = 50): Collection;
}
```

Consumer binds a `LaravelScoutAdapter` (a small wrapper around `laravel/scout`) if they want Scout-backed search. When bound, `MediaLibraryItem::search()` scope delegates to the adapter; otherwise falls back to LIKE.

### 14.3 Tags

`MediaLibrary::tag(MediaLibraryItem $item, string|MediaLibraryTag $tag): MediaLibraryTag` — find-or-create tag (owner-scoped), attach. `MediaLibrary::untag($item, $tag)`. Tag colors + position support a faceted filter UI.

### 14.4 Saved searches

`MediaLibrary::saveSearch(Model $user, string $name, array $filters): MediaLibrarySavedSearch`. Filters is an arbitrary JSON blob the consumer chose for their UI. Module never interprets fields beyond persistence.

## 15. Pluggable contracts

```
Kurt\Modules\MediaLibrary\Storage\Contracts\
  ExifExtractor       -- extract(string $path): array
  OcrExtractor        -- extract(string $path): string
  AiTagger            -- tag(string $path): array<int, string>
  BlurhashGenerator   -- generate(string $path): string
  PaletteExtractor    -- extract(string $path): array{dominant: string, palette: array<int, string>}

Kurt\Modules\MediaLibrary\Search\Contracts\
  ScoutAdapter

Kurt\Modules\MediaLibrary\Access\Contracts\
  MediaSubjectResolver
```

Defaults shipped: `DefaultExifExtractor` (PHP `exif_read_data` for JPEG/TIFF), `InterventionBlurhashGenerator`, `InterventionPaletteExtractor`, `DefaultSubjectResolver` (Everyone + User). No defaults for OCR / AI / Scout — consumer wires.

Bindings via `config('media-library.contracts.*')` FQCN map.

## 16. Top-level facade

```php
namespace Kurt\Modules\MediaLibrary\Support;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibrarySavedSearch;
use Kurt\Modules\MediaLibrary\Contracts\MediaLibraryOwner;
use Kurt\Modules\MediaLibrary\Storage\Models\MediaLibraryPendingUpload;

final class MediaLibrary
{
    // Uploads
    public function upload(UploadedFile $file, ?MediaLibraryOwner $owner = null, ?MediaLibraryFolder $folder = null, array $attributes = []): MediaLibraryItem;
    public function initiateUpload(?MediaLibraryOwner $owner, array $filenameMeta): MediaLibraryPendingUpload;
    public function completeUpload(string $uploadId): MediaLibraryItem;
    public function cancelUpload(string $uploadId): void;

    // Replace
    public function replace(MediaLibraryItem $item, UploadedFile|MediaLibraryPendingUpload $new, string $changelog): MediaLibraryItem;

    // Folders
    public function createFolder(MediaLibraryOwner $owner, string $name, ?MediaLibraryFolder $parent = null): MediaLibraryFolder;
    public function moveFolderTo(MediaLibraryFolder $folder, ?MediaLibraryFolder $newParent): MediaLibraryFolder;
    public function moveItems(array $itemIds, ?MediaLibraryFolder $newFolder): int;

    // Trash + restore
    public function trash(MediaLibraryItem|MediaLibraryFolder $target): void;
    public function restore(MediaLibraryItem|MediaLibraryFolder $target): void;

    // Sharing
    public function shareItem(MediaLibraryItem $item, int $expiresInSeconds, array $abilities = ['view'], ?string $invitee = null): string;
    public function shareFolder(MediaLibraryFolder $folder, int $expiresInSeconds, array $abilities = ['view'], ?string $invitee = null): string;
    public function revokeShare(string $token): void;

    // Tagging
    public function tag(MediaLibraryItem $item, string|\Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryTag $tag): \Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryTag;
    public function untag(MediaLibraryItem $item, string|\Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryTag $tag): void;

    // Saved searches
    public function saveSearch(Model $user, string $name, array $filters): MediaLibrarySavedSearch;
    public function runSearch(MediaLibrarySavedSearch $search): Collection;

    // Maintenance
    public function pruneVersions(MediaLibraryItem $item, int $keepNewest = 10): int;
    public function pruneVariants(MediaLibraryItem $item, int $unusedDays = 30): int;
    public function rebuildPaths(MediaLibraryOwner $owner): int;
    public function recountCounters(MediaLibraryOwner $owner): int;
}
```

## 17. Person-side contracts + traits

```
Kurt\Modules\MediaLibrary\Contracts\MediaLibraryOwner    -- interface getKey + getMorphClass
Kurt\Modules\MediaLibrary\Concerns\IsMediaLibraryOwner   -- adds mediaLibraryItems() / mediaLibraryFolders() helpers
Kurt\Modules\MediaLibrary\Concerns\HasMediaLibraryItems  -- consumer-model trait (see §11.1)
```

The `MediaLibraryOwner` interface is implemented by User/Team/Organization classes via `IsMediaLibraryOwner` trait. Polymorphic `owner_type + owner_id` columns store the relation.

## 18. Auth / policies

- `MediaLibraryItemPolicy` — view/download/manage delegate to `MediaLibraryAccess::check`.
- `MediaLibraryFolderPolicy` — same.
- `ShareLinkPolicy` — create requires `manage` on the target; revoke requires creator or manage.
- `SavedSearchPolicy` — user owns their saved searches.
- Global gate `canAdminMediaLibrary` bypasses for staff.

## 19. Config (`config/media-library.php`)

```php
return [
    'subject_resolver' => Kurt\Modules\MediaLibrary\Access\Support\DefaultSubjectResolver::class,

    'contracts' => [
        'exif' => Kurt\Modules\MediaLibrary\Storage\Extractors\DefaultExifExtractor::class,
        'blurhash' => Kurt\Modules\MediaLibrary\Storage\Extractors\InterventionBlurhashGenerator::class,
        'palette' => Kurt\Modules\MediaLibrary\Storage\Extractors\InterventionPaletteExtractor::class,
        'ocr' => null,
        'ai_tagger' => null,
        'scout' => null,
    ],

    'uploads' => [
        'disk' => env('MEDIA_LIBRARY_DISK', 'public'),
        'max_size_kb' => 100_000,
        'allowed_mimes' => ['image/*', 'video/*', 'audio/*', 'application/pdf', 'application/zip'],
        'presigned_ttl_seconds' => 900,
        'expire_pending_after_seconds' => 3600,
    ],

    'conversions' => [
        'thumb' => ['width' => 320, 'height' => 320, 'fit' => 'crop'],
        'cover' => ['width' => 1200, 'height' => 630, 'fit' => 'crop'],
        'social' => ['width' => 1600, 'height' => 900, 'fit' => 'crop'],
    ],

    'variants' => [
        'unused_days' => 30,
        'max_per_item' => 100,
    ],

    'versions' => [
        'keep_old' => 10,
        'hard_delete_old_files' => false,
    ],

    'routes' => [
        'share_enabled' => true,
        'share_prefix' => 'media-library/share',
    ],

    'extractors' => [
        'sync' => ['dimensions', 'palette', 'blurhash'],   // run in request
        'async' => ['exif', 'ai_tagger', 'ocr'],            // dispatched as jobs
    ],

    'access_log' => [
        'enabled' => true,
        'on_view' => true,
        'on_download' => true,
    ],

    'audit' => [
        'enabled' => true,
    ],

    'models' => [
        'item' => Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem::class,
        'folder' => Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder::class,
        'tag' => Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryTag::class,
        'attachment' => Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryAttachment::class,
        'share_link' => Kurt\Modules\MediaLibrary\Sharing\Models\ShareLink::class,
    ],
];
```

## 20. Console commands

- `media-library:prune-versions {item?}` — drop versions older than `keep_old`.
- `media-library:prune-variants {item?}` — drop unused variants past `unused_days`.
- `media-library:rebuild-paths {owner?}` — recompute folder `path` + `depth`.
- `media-library:recount {owner?}` — rebuild `item_count` + `descendant_count`.
- `media-library:expire-shares` — runs hourly. Closes expired share links.
- `media-library:prune-shares` — hard-delete expired share links beyond retention.
- `media-library:expire-pending-uploads` — runs every 5 minutes. Marks expired pending uploads.
- `media-library:reextract {item}` — re-run EXIF/blurhash/palette/AI tagger for one item.
- `media-library:reindex {owner?}` — push items to bound ScoutAdapter.
- `media-library:demo` — seed sample folders + items.

## 21. Optional Laravel Notifications

Under `src/Notifications/` (only registered when `config('media-library.notifications.enabled')`):

- `ShareLinkCreated` (to invitee_email when set)
- `ShareLinkAccessed` (to share creator on first access)
- `ItemReplaced` (to subscribers of the item via consumer-supplied mechanism)
- `LargeUploadCompleted` (to uploader)

Default Blade templates under `resources/views/notifications/*.blade.php`, publishable for branding.

## 22. Domain events

- Catalog: `FolderCreated`, `FolderUpdated`, `FolderMoved`, `FolderDeleted`, `FolderRestored`, `FolderPermissionChanged`.
- Items: `ItemUploaded`, `ItemUpdated`, `ItemReplaced(item, previousSpatieMediaId)`, `ItemAttached`, `ItemDetached`, `ItemTagged`, `ItemUntagged`, `ItemTrashed`, `ItemRestored`, `ItemDeleted`, `ItemViewed`, `ItemDownloaded`.
- Storage: `UploadInitiated`, `UploadCompleted`, `UploadCancelled`, `UploadExpired`, `VariantGenerated`, `VersionPruned`, `BlurhashComputed`, `PaletteComputed`, `ExifExtracted`, `AiTagsAssigned`, `TextExtracted`.
- Sharing: `ShareLinkCreated`, `ShareLinkAccessed`, `ShareLinkRevoked`, `ShareLinkExpired`.
- Audit: every state-changing facade method writes a row to `media_library_access_log` with the appropriate `AccessAction` (upload | replace | delete) plus actor/ip/user_agent. Cross-module audit aggregation is a v1.1 follow-up (see §24).

## 23. Filament admin (v1.1)

Resources planned:

- `MediaLibraryItemResource` — WordPress-grid view, file edit modal (alt/caption/description/focal/tags), version history, attachment manager.
- `MediaLibraryFolderResource` — tree view + ACL relation manager.
- `MediaLibraryTagResource`.
- `ShareLinkResource` — read-only access log + revoke action.

v1.0 ships **headless**. v1.1 adds the resources. Resource bodies live under `src/Filament/V{3,4,5}/Resources/…`.

## 24. Audit log

For v1.0, the access log (`media_library_access_log`) covers view/download/upload/replace/delete actions. A richer cross-module audit log was introduced in Events as `events_audit_log`. v1.1 may promote that to a shared `ozankurt/laravel-modules-audit` package — out of scope for v1.0 of Media Library.

## 25. Testing matrix

### Unit
- All enums (cases + values).
- `FocalPointCropper` math (focal at corners + center).
- `ShareLinkSigner::generate + verify` round-trip.
- `ItemSlugger` owner-scoped uniqueness.
- Variant key canonicalization.

### Feature (Pest + Testbench)
- Server-proxy upload happy path → MediaLibraryItem persisted + spatie file present + dimensions extracted.
- Replace flow: stable item id + version row created + attachments preserved.
- Folder moveTo: rewrites descendant paths in one query.
- Folder ACL matrix (cascade × visibility fallback × subject mismatch × inheritance).
- Polymorphic attachments: attach + detach + multi-role + position ordering.
- Tag attach/detach + owner-scoped uniqueness.
- Share link: creation + view + download + access log + revoke + expiry.
- Pending upload: initiate + complete + cancel + expire command.
- Conversion presets registered on the host model.
- Ad-hoc variant generation + cache hit on second call.
- Default search scopes.
- Scout adapter dispatch when bound.
- Notifications dispatched when enabled in config.
- Access log writes per `on_view` / `on_download` config knobs.
- GDPR-style trash + restore preserves attachments through soft-delete.

Coverage target: **70%** lines (v1.0 has heavy lifecycle code).

## 26. Repository setup

Create empty GitHub repo `https://github.com/OzanKurt/KurtModules-MediaLibrary`. Initial commit with `SECURITY.md` + `LICENSE.md`. Branch `v1.0`. Implementation plan references this spec.

## 27. Definition of done (v1.0)

- [ ] Pint + PHPStan level 8 + Pest (≥ 70% line coverage) all green.
- [ ] CI matrix green on Laravel 12.
- [ ] Upload (server-proxy AND presigned) happy paths covered.
- [ ] Replace flow proves stable item id + attachment preservation.
- [ ] Conversion presets + ad-hoc variants + focal-point cropping all tested.
- [ ] Folder ACL matrix exhaustive.
- [ ] Share-link end-to-end with access log.
- [ ] All commands behave per spec §20.
- [ ] README + CHANGELOG + UPGRADE-1.0 + GDPR notes in place.
- [ ] Tagged `v1.0.0` after merge to master.

## 28. Open follow-ups (not in v1.0)

- Filament v1.1 admin resources (planned).
- Promote cross-module audit log to a shared package (`ozankurt/laravel-modules-audit`).
- Multi-disk policies (auto-route per mime / size to S3 vs local).
- Image optimisation contract (sharp / cwebp / etc.).
- Bulk upload UX endpoints (chunked uploads).
- CDN signed-URL plugin for short-TTL cache.
- Video thumbnail / poster frame extraction via FFmpeg contract.

## 29. References

- [Umbrella v2 design](./2026-05-28-kurtmodules-v2-design.md)
- [Events v1 spec](./2026-05-29-kurtmodules-events-v1-spec.md) — refund / payout / facade conventions
- [Library rename spec](./2026-05-30-kurtmodules-resource-library-rename-spec.md)
- `spatie/laravel-medialibrary` v11 documentation for collection + conversion APIs.
