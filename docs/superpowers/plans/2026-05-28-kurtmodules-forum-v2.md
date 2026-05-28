# `ozankurt/laravel-modules-forum` v2.0 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build `ozankurt/laravel-modules-forum` v2.0.0 — a community forum module (nested boards → threads → posts, up/down voting, moderation queue, subscriptions, gamified badges). Greenfield package with Filament admin.

**Architecture:** Same scaffold as Core/Blog/Library. Voting + counters are denormalised (`Thread.score`, `Thread.reply_count`, `Board.thread_count`, `Board.post_count`) with a `forum:recount` command to rebuild. Badges are pluggable via `BadgeRule` classes; default rules ship + consumer can register their own. Subscriptions are polymorphic (Board or Thread).

**Tech Stack:** PHP 8.4, Laravel 12/13, Filament 3/4/5, Pest 3, Testbench, spatie/laravel-medialibrary, spatie/laravel-translatable, cviebrock/eloquent-sluggable.

**Spec:** [2026-05-28-kurtmodules-forum-v2-spec.md](../specs/2026-05-28-kurtmodules-forum-v2-spec.md)

**Prerequisite:** `ozankurt/laravel-modules-core` v2.0.0.

**Working directory:** `D:\Code\Projects\KurtModules-Forum`.

---

## Task 0–2: Scaffold

Same template as Library Tasks 0–2:
- Branch v2.0.
- composer.json with vendor `ozankurt/laravel-modules-forum`, namespace `Kurt\Modules\Forum\`, dependencies matching Library (sluggable, medialibrary, translatable, package-tools), require-dev matching Library (filament, larastan, pint, testbench ^10, pest, rector).
- Copy `pint.json`, `phpstan.neon`, `rector.php`, `.gitattributes`, `.gitignore`, `phpunit.xml.dist` from Core.

Commits per the Library plan.

---

## Task 3: Config

`config/forum.php`:

```php
<?php

declare(strict_types=1);

use Kurt\Modules\Forum\Badges\{FirstPostBadge, TenPostsBadge, HundredUpvotesBadge, FirstThreadBadge, WelcomeCommitterBadge};
use Kurt\Modules\Forum\Models\{Badge, Board, ModerationReport, Post, Subscription, Thread, UserBadge, Vote};

return [
    'edit_window_minutes' => 60,
    'thread_max_title_length' => 200,
    'post_max_body_length' => 30_000,
    'allow_self_vote' => false,

    'media' => [
        'disk' => env('FORUM_MEDIA_DISK', 'public'),
    ],

    'badges' => [
        'rules' => [
            FirstPostBadge::class,
            TenPostsBadge::class,
            HundredUpvotesBadge::class,
            FirstThreadBadge::class,
            WelcomeCommitterBadge::class,
        ],
    ],

    'models' => [
        'badge' => Badge::class,
        'board' => Board::class,
        'moderation_report' => ModerationReport::class,
        'post' => Post::class,
        'subscription' => Subscription::class,
        'thread' => Thread::class,
        'user_badge' => UserBadge::class,
        'vote' => Vote::class,
    ],
];
```

Commit:
```bash
git add config/forum.php
git commit -m "feat(forum): add config file"
```

---

## Task 4: Enums

Files: `src/Enums/{BoardState,Visibility,VoteValue,ReportState,BadgeRarity}.php` plus unit tests.

```php
enum BoardState: string { case Open='open'; case Locked='locked'; case Archived='archived'; }

enum Visibility: string { case Public='public'; case Unlisted='unlisted'; case Private='private'; }

enum VoteValue: int { case Down = -1; case Up = 1; }

enum ReportState: string { case Pending='pending'; case Resolved='resolved'; case Dismissed='dismissed'; }

enum BadgeRarity: string { case Common='common'; case Uncommon='uncommon'; case Rare='rare'; case Legendary='legendary'; }
```

Tests: cases-and-values per enum.

Commit:
```bash
git add src/Enums tests/Unit/Enums
git commit -m "feat(forum): add BoardState, Visibility, VoteValue, ReportState, BadgeRarity enums"
```

---

## Task 5: Migrations

Eight anonymous migrations under `database/migrations/` (timestamps `2026_05_28_020001`–`020008`):

### library_boards
```php
Schema::create('forum_boards', function (Blueprint $table) {
    $table->id();
    $table->foreignId('parent_id')->nullable()->constrained('forum_boards')->restrictOnDelete();
    $table->string('slug')->unique();
    $table->json('name');
    $table->json('description')->nullable();
    $table->unsignedInteger('position')->default(0);
    $table->string('state')->default('open');
    $table->string('visibility')->default('public');
    $table->unsignedBigInteger('thread_count')->default(0);
    $table->unsignedBigInteger('post_count')->default(0);
    $table->timestamp('last_post_at')->nullable()->index();
    $table->timestamps();
    $table->softDeletes();
});
```

### forum_threads
```php
Schema::create('forum_threads', function (Blueprint $table) {
    $table->id();
    $table->string('slug')->unique();
    $table->foreignId('board_id')->constrained('forum_boards')->restrictOnDelete();
    $table->foreignId('user_id')->constrained(config('auth.providers.users.table', 'users'))->cascadeOnDelete();
    $table->string('title');
    $table->boolean('is_pinned')->default(false);
    $table->boolean('is_locked')->default(false);
    $table->boolean('is_hidden')->default(false);
    $table->unsignedBigInteger('views')->default(0);
    $table->integer('score')->default(0);
    $table->unsignedBigInteger('reply_count')->default(0);
    $table->unsignedBigInteger('last_post_id')->nullable();
    $table->timestamp('last_post_at')->nullable()->index();
    $table->timestamps();
    $table->softDeletes();
    $table->index(['board_id', 'is_pinned', 'last_post_at']);
});
```

### forum_posts
```php
Schema::create('forum_posts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('thread_id')->constrained('forum_threads')->cascadeOnDelete();
    $table->foreignId('parent_id')->nullable()->constrained('forum_posts')->cascadeOnDelete();
    $table->foreignId('user_id')->constrained(config('auth.providers.users.table', 'users'))->cascadeOnDelete();
    $table->text('body');
    $table->boolean('is_root')->default(false);
    $table->timestamp('edited_at')->nullable();
    $table->foreignId('edited_by')->nullable()->constrained(config('auth.providers.users.table', 'users'))->nullOnDelete();
    $table->integer('score')->default(0);
    $table->unsignedBigInteger('reported_count')->default(0);
    $table->timestamps();
    $table->softDeletes();
    $table->index(['thread_id', 'created_at']);
});
```

Now add the deferred `last_post_id` FK on threads:
```php
Schema::table('forum_threads', function (Blueprint $table) {
    $table->foreign('last_post_id')->references('id')->on('forum_posts')->nullOnDelete();
});
```

(Place this in the posts migration's `up()` after `Schema::create('forum_posts', ...)` so the FK lands cleanly. Mirror in `down()`.)

### forum_votes
```php
Schema::create('forum_votes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('post_id')->constrained('forum_posts')->cascadeOnDelete();
    $table->foreignId('user_id')->constrained(config('auth.providers.users.table', 'users'))->cascadeOnDelete();
    $table->tinyInteger('value');
    $table->timestamps();
    $table->unique(['post_id', 'user_id']);
});
```

### forum_subscriptions
```php
Schema::create('forum_subscriptions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained(config('auth.providers.users.table', 'users'))->cascadeOnDelete();
    $table->morphs('subscribable');
    $table->timestamps();
    $table->unique(['user_id', 'subscribable_type', 'subscribable_id'], 'forum_subscriptions_unique');
});
```

### forum_moderation_reports
```php
Schema::create('forum_moderation_reports', function (Blueprint $table) {
    $table->id();
    $table->foreignId('post_id')->constrained('forum_posts')->cascadeOnDelete();
    $table->foreignId('reporter_id')->constrained(config('auth.providers.users.table', 'users'))->cascadeOnDelete();
    $table->string('reason');
    $table->text('notes')->nullable();
    $table->string('state')->default('pending');
    $table->timestamp('handled_at')->nullable();
    $table->foreignId('handled_by')->nullable()->constrained(config('auth.providers.users.table', 'users'))->nullOnDelete();
    $table->timestamps();
});
```

### forum_badges
```php
Schema::create('forum_badges', function (Blueprint $table) {
    $table->id();
    $table->string('slug')->unique();
    $table->json('name');
    $table->json('description');
    $table->string('icon')->nullable();
    $table->string('rarity')->default('common');
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

### forum_user_badges
```php
Schema::create('forum_user_badges', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained(config('auth.providers.users.table', 'users'))->cascadeOnDelete();
    $table->foreignId('badge_id')->constrained('forum_badges')->cascadeOnDelete();
    $table->timestamp('awarded_at');
    $table->json('context')->nullable();
    $table->timestamps();
    $table->unique(['user_id', 'badge_id']);
});
```

Commit:
```bash
git add database/migrations
git commit -m "feat(forum): add migrations"
```

---

## Task 6: Models + factories

Eight models. Highlights below; full bodies follow the same patterns as Blog/Library:

### Board (translatable, sluggable)
```php
class Board extends Model
{
    use HasFactory; use HasTranslations; use Sluggable; use SoftDeletes;
    protected $table = 'forum_boards';
    public array $translatable = ['name', 'description'];
    protected $fillable = ['parent_id','slug','name','description','position','state','visibility'];
    protected $casts = ['state' => BoardState::class, 'visibility' => Visibility::class, 'last_post_at' => 'datetime'];

    public function sluggable(): array { return ['slug' => ['source' => 'name', 'onUpdate' => true]]; }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id'); }
    public function threads(): HasMany { return $this->hasMany(Thread::class); }
    public function scopeOpen(Builder $q): Builder { return $q->where('state', BoardState::Open->value); }
    public function scopeRoots(Builder $q): Builder { return $q->whereNull('parent_id'); }
}
```

### Thread (sluggable)
```php
class Thread extends Model
{
    use HasFactory; use ResolvesUser; use Sluggable; use SoftDeletes;
    protected $table = 'forum_threads';
    protected $casts = ['is_pinned'=>'bool','is_locked'=>'bool','is_hidden'=>'bool','last_post_at'=>'datetime'];

    public function sluggable(): array { return ['slug' => ['source' => 'title']]; }
    public function board(): BelongsTo { return $this->belongsTo(Board::class); }
    public function user(): BelongsTo { return $this->userBelongsTo(); }
    public function posts(): HasMany { return $this->hasMany(Post::class); }
    public function rootPost(): HasOne { return $this->hasOne(Post::class)->where('is_root', true); }

    public function reply(\Illuminate\Database\Eloquent\Model $user, string $body, ?Post $parent = null): Post
    {
        return DB::transaction(function () use ($user, $body, $parent) {
            $post = $this->posts()->create([
                'parent_id' => $parent?->id,
                'user_id' => $user->getKey(),
                'body' => $body,
            ]);

            $this->forceFill([
                'reply_count' => $this->reply_count + 1,
                'last_post_id' => $post->id,
                'last_post_at' => now(),
            ])->save();

            $this->board()->update([
                'post_count' => DB::raw('post_count + 1'),
                'last_post_at' => now(),
            ]);

            ThreadReplied::dispatch($this->fresh(), $post);

            return $post;
        });
    }

    public function subscribe(\Illuminate\Database\Eloquent\Model $user): Subscription
    {
        return Subscription::firstOrCreate(['user_id' => $user->getKey(), 'subscribable_type' => self::class, 'subscribable_id' => $this->id]);
    }

    public function scopeForBoard(Builder $q, Board|int $board): Builder { return $q->where('board_id', $board instanceof Board ? $board->id : $board); }
    public function scopePinnedFirst(Builder $q): Builder { return $q->orderByDesc('is_pinned')->orderByDesc('last_post_at'); }
}
```

### Post (HasMedia)
```php
class Post extends Model implements HasMedia
{
    use HasFactory; use InteractsWithMedia; use ResolvesUser; use SoftDeletes;
    protected $table = 'forum_posts';
    protected $casts = ['is_root'=>'bool','edited_at'=>'datetime'];

    public function thread(): BelongsTo { return $this->belongsTo(Thread::class); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function replies(): HasMany { return $this->hasMany(self::class, 'parent_id'); }
    public function user(): BelongsTo { return $this->userBelongsTo(); }
    public function votes(): HasMany { return $this->hasMany(Vote::class); }

    public function vote(\Illuminate\Database\Eloquent\Model $user, VoteValue $value): ?Vote
    {
        if (! config('forum.allow_self_vote') && $this->user_id === $user->getKey()) {
            return null;
        }

        $existing = $this->votes()->where('user_id', $user->getKey())->first();

        if ($existing && $existing->value === $value->value) {
            // Toggle off: remove the vote.
            $existing->delete();
            $this->refreshScore();
            VoteRevoked::dispatch($this, $user);
            return null;
        }

        $vote = $this->votes()->updateOrCreate(
            ['user_id' => $user->getKey()],
            ['value' => $value->value],
        );

        $this->refreshScore();
        VoteCast::dispatch($vote);

        return $vote;
    }

    public function refreshScore(): void
    {
        $this->update(['score' => (int) $this->votes()->sum('value')]);
    }
}
```

### Vote
```php
class Vote extends Model
{
    use HasFactory;
    protected $table = 'forum_votes';
    protected $fillable = ['post_id','user_id','value'];
    protected $casts = ['value' => 'integer'];

    public function post(): BelongsTo { return $this->belongsTo(Post::class); }
}
```

### Subscription (polymorphic)
```php
class Subscription extends Model
{
    use HasFactory;
    protected $table = 'forum_subscriptions';
    protected $fillable = ['user_id','subscribable_type','subscribable_id'];

    public function subscribable(): MorphTo { return $this->morphTo(); }
}
```

### ModerationReport
```php
class ModerationReport extends Model
{
    use HasFactory;
    protected $table = 'forum_moderation_reports';
    protected $casts = ['state' => ReportState::class, 'handled_at' => 'datetime'];

    public function post(): BelongsTo { return $this->belongsTo(Post::class); }
    public function reporter(): BelongsTo { return $this->belongsTo(config('auth.providers.users.model'), 'reporter_id'); }
    public function handler(): BelongsTo { return $this->belongsTo(config('auth.providers.users.model'), 'handled_by'); }

    public function scopePending(Builder $q): Builder { return $q->where('state', ReportState::Pending->value); }
}
```

### Badge / UserBadge — straightforward.

Factories for each. Commit:
```bash
git add src/Models database/factories
git commit -m "feat(forum): add Board, Thread, Post, Vote, Subscription, ModerationReport, Badge, UserBadge models"
```

---

## Task 7: Events

`src/Events/*` — one per event listed in spec §8. Same `Dispatchable` skeleton as Blog.

Commit.

---

## Task 8: Badge engine

`src/Badges/BadgeRule.php`:
```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Forum\Badges;

use Illuminate\Database\Eloquent\Model;

interface BadgeRule
{
    public function badgeSlug(): string;

    /** @return array<int, class-string> */
    public function appliesAfter(): array;

    public function evaluate(Model $user, object $event): bool;
}
```

`src/Badges/BadgeAwarder.php`:
```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Forum\Badges;

use Illuminate\Database\Eloquent\Model;
use Kurt\Modules\Forum\Events\BadgeAwarded;
use Kurt\Modules\Forum\Models\Badge;
use Kurt\Modules\Forum\Models\UserBadge;

final class BadgeAwarder
{
    /** @var array<int, BadgeRule> */
    private array $rules = [];

    public function register(BadgeRule $rule): void
    {
        $this->rules[] = $rule;
    }

    public function handleEvent(object $event): void
    {
        foreach ($this->rules as $rule) {
            if (! in_array($event::class, $rule->appliesAfter(), true)) {
                continue;
            }

            $user = $event->user ?? ($event->post?->user ?? $event->thread?->user ?? null);
            if (! $user instanceof Model) {
                continue;
            }

            if (UserBadge::query()->where('user_id', $user->getKey())
                ->whereHas('badge', fn ($b) => $b->where('slug', $rule->badgeSlug()))
                ->exists()) {
                continue;
            }

            if (! $rule->evaluate($user, $event)) {
                continue;
            }

            $badge = Badge::query()->where('slug', $rule->badgeSlug())->first();
            if ($badge === null) {
                continue;
            }

            $award = UserBadge::create([
                'user_id' => $user->getKey(),
                'badge_id' => $badge->id,
                'awarded_at' => now(),
            ]);

            BadgeAwarded::dispatch($user, $badge, $award);
        }
    }
}
```

Default rules (each ≤25 lines):

```php
final class FirstPostBadge implements BadgeRule
{
    public function badgeSlug(): string { return 'first-post'; }
    public function appliesAfter(): array { return [\Kurt\Modules\Forum\Events\PostCreated::class]; }
    public function evaluate(Model $user, object $event): bool
    {
        return Post::query()->where('user_id', $user->getKey())->count() === 1;
    }
}
```

Repeat the same skeleton for `TenPostsBadge`, `HundredUpvotesBadge`, `FirstThreadBadge`, `WelcomeCommitterBadge`.

Tests in `tests/Feature/Badges/BadgeAwarderTest.php` cover:
- Rule fires exactly once per user.
- Awarder dispatches `BadgeAwarded`.
- Consumer-registered rule is picked up.

Commit:
```bash
git add src/Badges tests/Feature/Badges
git commit -m "feat(forum): add BadgeRule contract + BadgeAwarder + default rules"
```

---

## Task 9: Observers, listeners, policies, support classes

- Observers: `PostObserver`, `ThreadObserver` — wire counter increments via the listener pattern (or directly in observer).
- Listener: `RebuildThreadCountersListener` registered on `VoteCast`/`VoteRevoked` to keep `Thread.score` in sync (sum of root-post scores).
- Policies: `BoardPolicy`, `ThreadPolicy`, `PostPolicy`, `ModerationReportPolicy` per spec §9.
- Support: `ThreadCounters` service with a `recount(Thread)` method, called by `forum:recount`.

Commit:
```bash
git add src/Observers src/Listeners src/Policies src/Support
git commit -m "feat(forum): add observers, listeners, policies, counter helpers"
```

---

## Task 10: Console commands

- `forum:recount` — rebuilds counters from raw rows.
- `forum:award-badges {--user=}` — re-evaluates all rules for one or all users.
- `forum:demo` — seeds boards/threads/posts/votes.

Commit.

---

## Task 11: Service provider

```php
final class ForumServiceProvider extends PackageServiceProvider
{
    protected function module(): string { return 'forum'; }

    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-modules-forum')
            ->hasConfigFile('forum')
            ->hasTranslations()
            ->hasMigrations([...])
            ->hasCommands([RecountCommand::class, AwardBadgesCommand::class, DemoCommand::class]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(BadgeAwarder::class, function () {
            $awarder = new BadgeAwarder();
            foreach (config('forum.badges.rules', []) as $class) {
                $awarder->register($this->app->make($class));
            }
            return $awarder;
        });
    }

    public function packageBooted(): void
    {
        Post::observe(PostObserver::class);
        Thread::observe(ThreadObserver::class);

        Event::listen(['*'], function (string $name, array $payload) {
            $event = $payload[0] ?? null;
            if (is_object($event)) {
                app(BadgeAwarder::class)->handleEvent($event);
            }
        });

        $gate = $this->app['Illuminate\Contracts\Auth\Access\Gate'];
        $gate->policy(Board::class, BoardPolicy::class);
        $gate->policy(Thread::class, ThreadPolicy::class);
        $gate->policy(Post::class, PostPolicy::class);
        $gate->policy(ModerationReport::class, ModerationReportPolicy::class);
    }
}
```

Commit.

---

## Task 12: Filament resources V3/V4/V5

Mirror Library/Blog pattern. Resources: `BoardResource`, `ThreadResource`, `PostResource`, `ModerationReportResource`, `BadgeResource`, `UserBadgeResource`.

For V5: load `epic-skills:filament-v5` skill.

Commit per version.

---

## Task 13: Tests

`tests/TestCase.php` extends `PackageTestCase`, registers ForumServiceProvider, loads migrations.

Feature tests cover spec §15:
- Boards: tree creation + visibility scoping.
- Threads: create/pin/lock/move; counter atomicity (concurrent transaction test).
- Posts: reply; edit window; soft delete restores counters.
- Votes: idempotent + toggle removes; score denormalisation.
- Moderation flow.
- Each shipped badge rule fires.
- Subscriptions polymorphic + idempotent.
- `forum:recount` repairs corrupted counters.

Commit.

---

## Task 14: CI + docs

Copy `.github/workflows/tests.yml` from Library (Laravel 12 only + Filament axis). Add README/CHANGELOG/UPGRADE-2.0 (Initial release).

Commit.

---

## Task 15: Push + PR

```bash
git push -u origin v2.0
gh pr create --title "v2.0: initial release of ozankurt/laravel-modules-forum" --body ...
```

Tag after merge.

---

## Definition of done

- [ ] All migrations + tests green on SQLite in-memory.
- [ ] Pint + PHPStan + Pest pass.
- [ ] CI green.
- [ ] Concurrency test for counters passes.
- [ ] Vote toggle proven idempotent.
- [ ] Each badge rule fires exactly once per user.
- [ ] `forum:recount` reproduces counters identically to live increments.
- [ ] Filament resources V3/V4/V5 smoke green.
- [ ] Tagged `v2.0.0`.
