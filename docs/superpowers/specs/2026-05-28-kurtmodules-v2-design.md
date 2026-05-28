# KurtModules v2 — Umbrella Design Spec

**Date:** 2026-05-28
**Owner:** Ozan Kurt (`ozankurt2@gmail.com`)
**Status:** Draft → user review pending
**Scope:** Renovation of all `KurtModules-*` repositories to a coherent v2.0 line targeting current-era Laravel.

---

## 1. Goals

1. Modernise every KurtModules-* repository to a unified v2.0 baseline (PHP 8.4+, Laravel 12/13, Filament 3/4/5).
2. Replace legacy patterns (string repositories, custom Model base, Faker via factory facade, monolithic service providers) with idiomatic Laravel 12/13 patterns.
3. Provide first-class Filament admin resources (versioned matrix v3/v4/v5).
4. Ship full implementations for the four previously-empty modules: Chat, Forum, Library, plus the upgraded Blog and the bootstrap kit Core.
5. Encode every convention from this spec into a reusable `kurtmodules` skill so future work auto-loads it.

## 2. Non-goals

- Backwards-compatible upgrade path for v1 consumers. v1 stays on `0.x`; v2 is a breaking redesign.
- Public/customer-facing Blade views. Modules ship headless data + Filament admin only.
- Repository pattern. v1 used `*RepositoryInterface` + `Eloquent*Repository` + cache decorator. v2 drops these — Eloquent models with scopes are sufficient. (The user explicitly approved this simplification.)
- An umbrella monorepo / split-package tooling. Each module keeps its standalone repo.
- A cross-module bridge package. Modules only depend on `ozankurt/laravel-modules-core`.

## 3. Modules in scope

| # | Repo | v1 vendor | v2 vendor | v1 status | v2 role |
|---|------|-----------|-----------|-----------|---------|
| 1 | KurtModules-Core | `ozankurt/modules-core` | `ozankurt/laravel-modules-core` | 3 traits + Links util | Module bootstrap kit |
| 2 | KurtModules-Blog | `ozankurt/modules-blog` | `ozankurt/laravel-modules-blog` | Functional with repos/observers | Full rewrite, drops repos, adds scheduled publish + SEO + medialibrary + translatable |
| 3 | KurtModules-Chat | (none) | `ozankurt/laravel-modules-chat` | Empty (SECURITY.md only) | New: rooms, DM, threads, presence, reactions, attachments, mentions |
| 4 | KurtModules-Forum | (none) | `ozankurt/laravel-modules-forum` | Empty (README + SECURITY) | New: boards, threads, replies, voting, moderation, subscriptions, badges |
| 5 | KurtModules-Library | (none) | `ozankurt/laravel-modules-library` | Empty (SECURITY.md only) | New: SaaS resource library — nested folders, mixed items (video link, file, document, external URL), per-folder Gate-based permissions, versioning |

## 4. Standards baseline (becomes the `kurtmodules` skill)

### 4.1 Stack

| Layer | Constraint |
|---|---|
| PHP | `^8.4` (property hooks, asymmetric visibility allowed) |
| Laravel | `^12.0 \|\| ^13.0` |
| Filament | `^3.0 \|\| ^4.0 \|\| ^5.0` (optional require-dev; per-version resources) |
| Test runner | `pestphp/pest ^3.0` |
| Static analyser | `larastan/larastan ^3.0` (PHPStan level 8) |
| Code style | `laravel/pint` |
| Refactoring | `rector/rector` (Laravel + Pest sets) for future upgrades |

### 4.2 Composer template

```jsonc
{
  "name": "ozankurt/laravel-modules-{name}",
  "description": "{One-line description}",
  "keywords": ["laravel", "filament", "kurtmodules", "{name}"],
  "license": "MIT",
  "authors": [{ "name": "Ozan Kurt", "email": "ozankurt2@gmail.com" }],
  "require": {
    "php": "^8.4",
    "illuminate/contracts": "^12.0 || ^13.0",
    "illuminate/database": "^12.0 || ^13.0",
    "illuminate/support": "^12.0 || ^13.0",
    "ozankurt/laravel-modules-core": "^2.0",
    "spatie/laravel-package-tools": "^1.92",
    "spatie/laravel-translatable": "^6.11"
    // module-specific extras
  },
  "require-dev": {
    "pestphp/pest": "^3.0",
    "pestphp/pest-plugin-laravel": "^3.0",
    "orchestra/testbench": "^9.0 || ^10.0",
    "larastan/larastan": "^3.0",
    "laravel/pint": "^1.18",
    "rector/rector": "^2.0",
    "filament/filament": "^3.0 || ^4.0 || ^5.0"
  },
  "autoload": {
    "psr-4": { "Kurt\\Modules\\{Name}\\": "src/" }
  },
  "autoload-dev": {
    "psr-4": { "Kurt\\Modules\\{Name}\\Tests\\": "tests/" }
  },
  "extra": {
    "laravel": {
      "providers": ["Kurt\\Modules\\{Name}\\{Name}ServiceProvider"]
    }
  },
  "config": { "sort-packages": true, "allow-plugins": { "pestphp/pest-plugin": true } },
  "minimum-stability": "stable",
  "prefer-stable": true
}
```

### 4.3 Directory layout

```
src/
  Concerns/                # Traits (HasComments, IsAuthored, …)
  Console/Commands/        # Artisan commands
  Contracts/               # User-side interfaces (BlogAuthor, ChatParticipant, …)
  Enums/                   # PHP 8.1+ enums (PostStatus, RoomKind, …)
  Events/                  # Domain events (PostPublished, MessageSent, …)
  Exceptions/
  Filament/
    Concerns/              # Shared resource traits
    V3/                    # Filament 3 resources, pages, widgets
    V4/                    # Filament 4 …
    V5/                    # Filament 5 …
  Listeners/
  Models/
  Observers/
  Policies/
  Providers/
    {Name}ServiceProvider.php   # extends Spatie\LaravelPackageTools\PackageServiceProvider
    {Name}FilamentServiceProvider.php  # registered conditionally per Filament version
  Scopes/                  # Global scopes when needed
  Support/                 # Utility classes (e.g. video URL parser)
config/{name}.php
database/
  factories/
  migrations/              # Anonymous publishable migrations
  seeders/
lang/
  en/
  tr/
resources/views/filament/  # Only if Filament resources need custom Blade
routes/                    # Only if module ships routes (Chat broadcasting auth, etc.)
tests/
  Feature/
  Unit/
  Pest.php
  TestCase.php             # extends Orchestra\Testbench\TestCase
.github/workflows/tests.yml
phpstan.neon
pint.json
rector.php
README.md
CHANGELOG.md
UPGRADE-2.0.md
LICENSE.md
SECURITY.md
composer.json
```

### 4.4 Service provider pattern

Use Spatie's `PackageServiceProvider`. Every module:

```php
namespace Kurt\Modules\{Name}\Providers;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class {Name}ServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-modules-{name}')
            ->hasConfigFile()
            ->hasTranslations()
            ->hasMigrations([ /* anonymous migrations registered */ ])
            ->hasCommands([ /* commands */ ]);
    }

    public function packageBooted(): void
    {
        $this->registerObservers();
        $this->registerPolicies();
        $this->registerFilament(); // see §4.6
    }
}
```

### 4.5 Migrations

- Anonymous classes (`return new class extends Migration { … };`).
- Filename `YYYY_MM_DD_HHMMSS_create_{table}_table.php`.
- Tables prefixed with the module short-name: `blog_posts`, `chat_rooms`, `forum_threads`, `library_folders`. (Same as v1 for Blog; new for the rest.)
- `id` → `uuid` is **opt-in via config**, not the default. Default stays `bigIncrements` for ecosystem parity. Config key `{name}.keys.morph` controls polymorphic targets.
- All FK columns to user use `foreignId('user_id')->constrained(config('auth.providers.users.table', 'users'))->cascadeOnDelete()`.
- Soft deletes on every domain row.
- `created_at`, `updated_at` on every row.

### 4.6 Filament version matrix

Filament is in **flux** across v3 → v4 → v5. Strategy:

1. `composer.json` declares `filament/filament` as a soft dev requirement with the OR-constraint above.
2. Production consumers install whichever Filament version they're on.
3. Module ships **three parallel resource sets** under `src/Filament/V3`, `src/Filament/V4`, `src/Filament/V5`.
4. `{Name}FilamentServiceProvider` detects installed version via `Composer\InstalledVersions::getVersion('filament/filament')` and registers the matching set.
5. Resource class names are identical across versions (e.g. `PostResource`); only namespace differs.
6. CI runs the Pest suite once per Filament major version using `composer require filament/filament:^X.0 --no-update` matrix step.
7. Filament v5 work follows the `epic-skills:filament-v5` skill or the equivalent `filament-v5` user-skill — load that skill when touching V5 resources.

### 4.7 Domain authoring contract

App-supplied User model must implement the module's `Contracts/{Module}Author` interface (or a more specific role contract like `ChatParticipant`). Module never references `App\User` directly. Default contract methods rely on the user's primary key + display name.

```php
namespace Kurt\Modules\Blog\Contracts;

interface BlogAuthor
{
    public function getKey(): int|string;
    public function getAuthorDisplayName(): string;
}
```

A `Concerns/IsBlogAuthor` trait provides a default implementation that the app can `use`.

### 4.8 Events

Every state-changing action dispatches a domain event under `src/Events/`. Examples:

- `Kurt\Modules\Blog\Events\PostPublished($post)`
- `Kurt\Modules\Chat\Events\MessageSent($message)` (also `ShouldBroadcast`)
- `Kurt\Modules\Forum\Events\ThreadLocked($thread, $moderator)`
- `Kurt\Modules\Library\Events\FolderPermissionChanged($folder)`

Consumers wire their own listeners. Modules ship no listeners by default (broadcast channels excepted in Chat).

### 4.9 Auth & policies

- Gates/Policies only — **no** `spatie/laravel-permission` requirement.
- Each module ships a default Policy class auto-registered in `packageBooted()`.
- Policies accept the authenticated user (any model implementing the relevant author contract).
- Consumer can override by binding a custom policy in `AuthServiceProvider::$policies`.

### 4.10 Translatable content

- Use `spatie/laravel-translatable`.
- Columns that hold translatable text are `json` in the migration and listed in `$translatable = [...]` on the model.
- Translatable per module:
  - **Blog**: `Post.title`, `Post.excerpt`, `Post.body`, `Category.name`, `Tag.name`, `Tag.description`.
  - **Forum**: `Board.name`, `Board.description`, `Badge.name`, `Badge.description`.
  - **Library**: `Folder.name`, `Folder.description`, `Item.title`, `Item.description`.
  - **Chat**: not translatable (user-generated, real-time).

### 4.11 Media

- Use `spatie/laravel-medialibrary` for all uploaded files (Post images, Chat attachments, Library file/document items).
- Define media collections + conversions in the model (`registerMediaCollections`, `registerMediaConversions`).
- Disk is consumer-configured; modules read from `config('{name}.media.disk', 'public')`.

### 4.12 Real-time (Chat only)

- `Kurt\Modules\Chat\Events\MessageSent` implements `ShouldBroadcastNow`.
- Channel: `chat.room.{roomId}` (private) or `chat.dm.{conversationId}`.
- Presence channel: `presence-chat.room.{roomId}`.
- Module ships a `routes/channels.php` and merges it via `Broadcast::routes()` opt-in in the provider.
- Consumer chooses Reverb/Pusher/Ably driver.

### 4.13 Testing

- **Pest 3** with `pest-plugin-laravel` and **Orchestra Testbench** for package context.
- `tests/TestCase.php` boots the module provider + an in-memory SQLite database.
- Factories live in `database/factories/` and are PSR-4-loaded.
- Coverage target: **80% lines** per module, enforced in CI.
- Required suites per module:
  - **Unit**: scopes, accessors, enums, support utilities.
  - **Feature**: full happy path per public model (create + key relations + events fired).
  - **Filament**: each resource list/create/edit page renders without error (via `livewire/livewire` test helpers, mirrored across V3/V4/V5).

### 4.14 CI (.github/workflows/tests.yml)

Matrix: PHP `[8.4, 8.5]` × Laravel `[12.*, 13.*]` × Filament `[3.*, 4.*, 5.*]`. Steps:

1. Checkout, setup-php, cache composer.
2. `composer update --prefer-stable --prefer-dist --no-interaction` with `--with` overrides per matrix slot.
3. `vendor/bin/pint --test`
4. `vendor/bin/phpstan analyse --memory-limit=2G`
5. `vendor/bin/pest --coverage --min=80`

Plus a separate job:
- `vendor/bin/rector --dry-run` (informational only, doesn't block).
- Composer-validate, security-checker (`local-php-security-checker`).

### 4.15 Versioning & release

- SemVer; v2.0.0 cuts on each module's `master` once tests are green.
- Tag and GitHub release on merge to `master`.
- Each repo carries an `UPGRADE-2.0.md` describing migration from v1 (or "Initial release" for the three new modules).

## 5. Module dependency graph

```
laravel-modules-blog ─┐
laravel-modules-chat ─┤
laravel-modules-forum ┼──> laravel-modules-core
laravel-modules-library┘
```

No cross-module dependencies. App can install any subset.

## 6. Execution sequence

1. **Specs phase** (this PR): write umbrella spec + 5 per-module specs, create `kurtmodules` skill, commit.
2. **User review gate**: user reviews specs; this brainstorming session ends.
3. **Planning phase** (next session via `writing-plans`): produce per-module implementation plan files in `docs/superpowers/plans/`.
4. **Implementation phase** (per-module, in this order):
   1. `Core` — everything depends on it.
   2. `Blog` — best v1 reference; biggest rewrite signal.
   3. `Library` — greenfield, simplest of the three new modules, exercises folder/permission patterns.
   4. `Forum` — greenfield, exercises voting/moderation/subscription patterns.
   5. `Chat` — greenfield, exercises real-time / broadcasting / presence — most complex.
5. Each module gets its own branch + PR + tag `v2.0.0` on its own repo.

## 7. Skill: `kurtmodules`

Authored via `skill-creator:writing-skills` (or `superpowers:writing-skills`).

- **Location**: user-level skill (`C:\Users\Ozan\.claude\skills\kurtmodules\SKILL.md` or wherever the active personal-skills root lives).
- **Trigger description**: "Use when working on any `KurtModules-*` / `ozankurt/laravel-modules-*` repository — including Core, Blog, Chat, Forum, Library. Also when the user mentions kurtmodules, KurtModule, modules-blog, modules-chat, modules-forum, modules-library, or modules-core."
- **Content**: a concise reference of every standard in §4 — stack, composer template, directory layout, service provider pattern, migration rules, Filament matrix, author contracts, events, auth, translatable, media, real-time, testing, CI, versioning.
- **Cross-references**: link out to `filament-v5` for Filament 5 work; defer to it for any V5-specific guidance.

## 8. Risks

| Risk | Mitigation |
|---|---|
| Filament 3/4/5 simultaneous support balloons code triple | Resource bodies are usually small — duplication is acceptable, and Filament version-jumps will retire older sets quickly. |
| Reverb infra needed for Chat tests | Use Laravel's `Event::fake()` + assert `MessageSent` dispatched; do not spin Reverb in CI. |
| PHP 8.4 not yet on all hosts | Modules target current era; legacy stays on v1. |
| Library permission model is custom (no spatie/permission) | Ship a clean Gate contract + opinionated default that maps role → capability; document override path. |
| spatie/laravel-translatable adds JSON column overhead | Acceptable; Laravel JSON column support is mature. Non-translatable fields stay string. |

## 9. Open decisions

None remaining at design time. All deferred questions converted to per-module spec items.

## 10. References

- v1 source paths inspected: `src/Models/*`, `src/Providers/BlogServiceProvider.php`, `src/Repositories/**`, `src/Traits/BlogUser.php`, `src/video_functions.php`, `src/Http/blogRoutes.php`, `src/Http/Controllers/BlogController.php`.
- Per-module specs:
  - [Core v2 spec](./2026-05-28-kurtmodules-core-v2-spec.md)
  - [Blog v2 spec](./2026-05-28-kurtmodules-blog-v2-spec.md)
  - [Chat v2 spec](./2026-05-28-kurtmodules-chat-v2-spec.md)
  - [Forum v2 spec](./2026-05-28-kurtmodules-forum-v2-spec.md)
  - [Library v2 spec](./2026-05-28-kurtmodules-library-v2-spec.md)
