# `ozankurt/laravel-modules-forum` v2.0 — Spec

**Repo:** `KurtModules-Forum`
**Date:** 2026-05-28
**Status:** Draft → user review pending
**Umbrella:** [2026-05-28-kurtmodules-v2-design.md](./2026-05-28-kurtmodules-v2-design.md)

---

## 1. Purpose

A community forum module: nested **boards** containing **threads** of **replies**, with up/down **voting**, **moderation queue**, per-thread **subscriptions**, and gamified **badges**. Filament admin for moderators.

## 2. Status

KurtModules-Forum v1 is empty (`README.md` placeholder + `SECURITY.md`). v2.0 is the **initial release**.

## 3. Composer

```jsonc
{
  "name": "ozankurt/laravel-modules-forum",
  "description": "Community forum for Laravel: boards, threads, replies, voting, moderation, subscriptions, badges.",
  "keywords": ["laravel", "filament", "forum", "community", "moderation"],
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

- **Board** — top-level category (or nested via `parent_id`). Translatable name + description. Boards can be `locked` (no new threads), `archived` (read-only), or `open`.
- **Thread** — belongs to a board; has a title and a first **post**; can be pinned, locked, hidden.
- **Post** — a reply in a thread (the OP is also a Post row, marked `is_root=true`). Supports threaded replies one level (`parent_id` self-FK).
- **Vote** — a user's up/down on a post; unique per (post, user). The post stores a denormalised `score`.
- **Subscription** — a user follows a thread or a board; used for notifications.
- **ModerationReport** — a user-submitted report against a post; appears in a queue.
- **Badge** — earned achievement. Award rules are pluggable via `BadgeRule` classes; default rules include first-post, 10-posts, 100-upvotes, etc.

## 5. Tables

```
forum_boards
  id, slug, name (json — translatable), description (json — translatable, nullable),
  parent_id (nullable, self FK, restrictOnDelete),
  position (unsigned int default 0),
  state (string — open|locked|archived),
  visibility (string — public|unlisted|private),
  thread_count (unsigned int default 0),    -- denormalised
  post_count (unsigned int default 0),       -- denormalised
  last_post_at (timestamp nullable, indexed),
  created_at, updated_at, deleted_at

forum_threads
  id, slug, board_id (FK forum_boards.id, restrictOnDelete),
  user_id (FK users.id, cascadeOnDelete),
  title (string),
  is_pinned (bool default false),
  is_locked (bool default false),
  is_hidden (bool default false),
  views (unsigned int default 0),
  score (int default 0),                     -- sum of OP votes
  reply_count (unsigned int default 0),      -- replies only (excludes OP)
  last_post_id (FK forum_posts.id, nullable),
  last_post_at (timestamp nullable, indexed),
  created_at, updated_at, deleted_at
  index(board_id, is_pinned, last_post_at)

forum_posts
  id, thread_id (FK forum_threads.id, cascadeOnDelete),
  parent_id (nullable, self FK, cascadeOnDelete),
  user_id (FK users.id, cascadeOnDelete),
  body (text),
  is_root (bool default false),              -- true for OP
  edited_at (timestamp nullable),
  edited_by (FK users.id nullable),
  score (int default 0),
  reported_count (unsigned int default 0),
  created_at, updated_at, deleted_at
  index(thread_id, created_at)

forum_votes
  id, post_id (FK forum_posts.id, cascadeOnDelete),
  user_id (FK users.id, cascadeOnDelete),
  value (tinyint), -- -1 or 1
  created_at, updated_at,
  unique(post_id, user_id)

forum_subscriptions
  id, user_id (FK users.id, cascadeOnDelete),
  subscribable_type (string), -- Board or Thread morph
  subscribable_id (unsignedBigInt),
  created_at, updated_at,
  unique(user_id, subscribable_type, subscribable_id)

forum_moderation_reports
  id, post_id (FK forum_posts.id, cascadeOnDelete),
  reporter_id (FK users.id, cascadeOnDelete),
  reason (string),
  notes (text nullable),
  state (string — pending|resolved|dismissed),
  handled_at (timestamp nullable),
  handled_by (FK users.id nullable),
  created_at, updated_at

forum_badges
  id, slug (unique), name (json — translatable), description (json — translatable),
  icon (string nullable), rarity (string — common|uncommon|rare|legendary),
  is_active (bool default true),
  created_at, updated_at

forum_user_badges
  id, user_id (FK users.id, cascadeOnDelete),
  badge_id (FK forum_badges.id, cascadeOnDelete),
  awarded_at, context (json nullable),
  unique(user_id, badge_id)
```

## 6. Enums

```php
enum BoardState: string { case Open='open'; case Locked='locked'; case Archived='archived'; }
enum Visibility: string { case Public='public'; case Unlisted='unlisted'; case Private='private'; }
enum VoteValue: int { case Down=-1; case Up=1; }
enum ReportState: string { case Pending='pending'; case Resolved='resolved'; case Dismissed='dismissed'; }
enum BadgeRarity: string { case Common='common'; case Uncommon='uncommon'; case Rare='rare'; case Legendary='legendary'; }
```

## 7. Models

```
Kurt\Modules\Forum\Models\
  Board (translatable)
  Thread
  Post (HasMedia)
  Vote
  Subscription
  ModerationReport
  Badge (translatable)
  UserBadge
```

Notable scopes:

- `Thread::scopeForBoard(Board|int)`, `::scopePinnedFirst()`, `::scopeVisibleTo(User)`, `::scopeUnresolved()`.
- `Post::scopeReplies()`, `::scopeRoots()`, `::scopeHotIn(Thread)` (score-then-recency).
- `Board::scopeOpen()`, `::scopeRoots()` (parent_id null).
- `ModerationReport::scopePending()`.

Notable methods:

- `Thread::reply(User $user, string $body, ?Post $parent = null): Post` — increments counters atomically; dispatches `PostCreated` + `ThreadReplied`.
- `Post::vote(User $user, VoteValue $value): Vote` — idempotent; toggles to remove if `value` matches an existing vote with the same value.
- `Thread::subscribe(User)`, `::unsubscribe(User)` (same on Board).
- `Post::report(User, string $reason, ?string $notes): ModerationReport`.
- `BadgeAwarder::evaluate(User)` runs all registered `BadgeRule` instances after relevant events.

## 8. Events

```
ThreadCreated, ThreadLocked($thread, $moderator), ThreadPinned, ThreadHidden, ThreadMoved($thread, $fromBoard, $toBoard, $moderator)
PostCreated, PostEdited, PostDeleted, PostHidden, PostReported, PostScoreChanged
VoteCast, VoteRevoked
SubscriptionCreated, SubscriptionRemoved
BadgeAwarded(User, Badge)
ModerationReportSubmitted, ModerationReportResolved, ModerationReportDismissed
```

## 9. Policies

`BoardPolicy`, `ThreadPolicy`, `PostPolicy`, `ModerationReportPolicy`. Selected gates:

- `viewBoard` / `viewThread`: visibility check + auth.
- `createThread`: board state = open + auth + `canPostInForum` gate (default true).
- `replyToThread`: thread !locked + auth.
- `editPost` / `deletePost`: author within `forum.edit_window_minutes`, or moderator.
- `votePost`: auth + not self-vote (configurable).
- `moderate*`: `canModerateForum` gate.

## 10. Badge engine

```php
namespace Kurt\Modules\Forum\Badges;

interface BadgeRule
{
    public function badgeSlug(): string;
    public function appliesAfter(): array;     // event class names
    public function evaluate(User $user, object $event): bool;
}
```

Default rules shipped:

- `FirstPostBadge` (awards `first-post` after `PostCreated`).
- `TenPostsBadge` (`ten-posts`).
- `HundredUpvotesBadge` (`hundred-upvotes`).
- `FirstThreadBadge` (`first-thread`).
- `WelcomeCommitterBadge` (one year on the forum — needs `created_at` on user, gated via contract method).

Consumer can register their own rules via `BadgeAwarder::register(Rule::class)` (e.g. in `AppServiceProvider::boot`).

## 11. Filament resources

- `BoardResource` (tree-aware).
- `ThreadResource` (filters: pinned, locked, hidden, board).
- `PostResource` (queue view — sortable by `reported_count`).
- `ModerationReportResource` (resolve / dismiss actions, bulk).
- `BadgeResource` + `UserBadgeResource`.

V3 / V4 / V5 parallel.

## 12. Trait + contract

```
Kurt\Modules\Forum\Concerns\IsForumMember
Kurt\Modules\Forum\Contracts\ForumMember
```

Methods: `forumThreads()`, `forumPosts()`, `forumVotes()`, `forumBadges()`, `forumSubscriptions()`, `getForumDisplayName(): string`, `getForumAvatarUrl(): ?string`.

## 13. Config (`config/forum.php`)

```php
return [
    'edit_window_minutes' => 60,
    'thread_max_title_length' => 200,
    'post_max_body_length' => 30_000,
    'allow_self_vote' => false,
    'media' => ['disk' => env('FORUM_MEDIA_DISK', 'public')],
    'badges' => [
        'rules' => [
            \Kurt\Modules\Forum\Badges\FirstPostBadge::class,
            \Kurt\Modules\Forum\Badges\TenPostsBadge::class,
            \Kurt\Modules\Forum\Badges\HundredUpvotesBadge::class,
            \Kurt\Modules\Forum\Badges\FirstThreadBadge::class,
            \Kurt\Modules\Forum\Badges\WelcomeCommitterBadge::class,
        ],
    ],
    'models' => [/* … */],
];
```

## 14. Console commands

- `forum:recount` — rebuilds all `thread_count`, `post_count`, `reply_count`, `score` columns from raw data. Idempotent.
- `forum:award-badges --user=<id>?` — re-evaluates all badge rules for one or all users.
- `forum:demo` — seeds boards/threads/posts/votes.

## 15. Test coverage targets

| Suite | Cases |
|---|---|
| Unit | Enum casts; `BadgeAwarder` orchestration with mocked rules; `VoteValue` toggling logic |
| Feature/Boards | Tree creation; visibility scoping |
| Feature/Threads | Create / pin / lock / move; counters update atomically (concurrent test via DB transaction) |
| Feature/Posts | Reply; edit window; soft delete restores counters |
| Feature/Votes | Idempotent vote; toggle removes; score denormalisation accurate; self-vote rejection when configured |
| Feature/Moderation | Report → queue → resolve/dismiss flow |
| Feature/Badges | Each default rule fires correctly and stops re-awarding |
| Feature/Subscriptions | Polymorphic morph; idempotent |
| Feature/Filament/V{3,4,5} | Resource smoke |
| Feature/Recount | `forum:recount` repairs intentionally corrupted counters |

## 16. Directory layout

```
src/
  Badges/{BadgeRule,BadgeAwarder,FirstPostBadge,…}.php
  Concerns/IsForumMember.php
  Console/Commands/{RecountCommand,AwardBadgesCommand,DemoCommand}.php
  Contracts/ForumMember.php
  Enums/{BoardState,Visibility,VoteValue,ReportState,BadgeRarity}.php
  Events/{…}
  Filament/{V3,V4,V5}/Resources/{Board,Thread,Post,ModerationReport,Badge,UserBadge}Resource.php
  Listeners/RebuildThreadCountersListener.php  -- internal; owns denormalisation
  Models/{Badge,Board,ModerationReport,Post,Subscription,Thread,UserBadge,Vote}.php
  Observers/{Post,Thread}Observer.php
  Policies/{Board,Thread,Post,ModerationReport}Policy.php
  Providers/{ForumServiceProvider,ForumFilamentServiceProvider}.php
  Support/ThreadCounters.php
config/forum.php
database/factories/…
database/migrations/
  2026_05_28_020001_create_forum_boards_table.php
  …020002_create_forum_threads_table.php
  …020003_create_forum_posts_table.php
  …020004_create_forum_votes_table.php
  …020005_create_forum_subscriptions_table.php
  …020006_create_forum_moderation_reports_table.php
  …020007_create_forum_badges_table.php
  …020008_create_forum_user_badges_table.php
lang/en/forum.php
lang/tr/forum.php
tests/…
```

## 17. Definition of done

- [ ] Matrix CI green.
- [ ] Concurrency test for counters passes (no double-increment).
- [ ] Vote toggle idempotency proven.
- [ ] Each shipped badge rule fires once per user.
- [ ] `forum:recount` rebuilds counters identically to the live increments.
- [ ] Filament resources work across V3/V4/V5.
- [ ] Tagged `v2.0.0`.
