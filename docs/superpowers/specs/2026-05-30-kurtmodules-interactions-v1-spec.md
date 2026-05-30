# KurtModules Interactions — v1 Spec

**Package:** `ozankurt/laravel-modules-interactions` · **Repo:** `KurtModules-Interactions`
**Date:** 2026-05-30 · **Status:** approved (build inline)

A full-featured, polymorphic social & engagement toolkit for the KurtModules
family: emoji reactions, comments, @mentions, the engagement primitives
(like/dislike/vote/rate/favorite/subscribe), and a social graph
(follows/friendships/groups). Any Eloquent model opts in via traits. Inspired by
multicaret/laravel-acquaintances (north star), overtrue's social suite
(trait conventions), qirolab/laravel-reactions (reaction API), and
CrixuAMG/Laravel-Mentions (mention parsing).

Headless-first with an optional Filament admin (v3/v4/v5). Core-only hard
dependency; **soft** dependency on MediaLibrary (only when custom emoji enabled).

## 1. Decisions (locked)

- **One module**, `Interactions`. Follows live in the unified interactions table
  (acquaintances pattern), not a dedicated table.
- **Reactions:** any unicode emoji **and** Discord-style custom uploaded emoji
  (via MediaLibrary); multiple distinct emoji per user per target.
- **Comments:** unlimited nested threads, markdown bodies (carry @mentions +
  emoji), edit history, soft-delete + moderation states.
- **Social graph:** follows + friendships (request/accept/deny/block) + friend
  groups.
- **Notifications:** fire framework events **and** ship optional, toggleable
  Laravel Notification classes that plug into the host's notifiable setup.
- **Counters:** denormalized `interactions_counters` table maintained by
  observers (host models need no schema change); driver configurable.
- **Retrofit:** full, as coordinated releases of the consuming repos after
  Interactions v1.0 ships.

## 2. Subdomains (`src/`)

```
Graph/        follows, friendships, friend groups
Engagement/   reactions (emoji), likes/dislikes, votes, ratings, favorites, subscriptions
Comments/     comments, threading, revisions, moderation
Mentions/     parser + polymorphic mention records
Emoji/        unicode shortcode dataset + custom-emoji registry
Support/      Interactions facade-service, counter observers, resolvers
Notifications/ optional notification classes
Filament/     V3 / V4 / V5 + dispatching InteractionsPlugin
Providers/    InteractionsServiceProvider
```

## 3. Data model (tables prefixed `interactions_`)

- **interactions_interactions** — unified engagement. Columns: `id`,
  `user_id` (actor), `subject_type` + `subject_id` (morph target), `type`
  (enum: `like`,`dislike`,`vote`,`favorite`,`subscribe`,`follow`), `value`
  (int, vote weight ±1; null otherwise), `created_at`. Unique
  `(user_id, subject_type, subject_id, type)` → idempotent toggles.
- **interactions_ratings** — one updatable score per user per subject.
  `user_id`, `subject` morph, `score` (unsigned tinyint), timestamps.
  Unique `(user_id, subject_type, subject_id)`.
- **interactions_reactions** — `user_id`, `reactable` morph, `emoji` (string:
  unicode char or `:shortcode:`), `custom_emoji_id` (nullable FK), `created_at`.
  Unique `(user_id, reactable_type, reactable_id, emoji)`.
- **interactions_friendships** — `sender_id`, `recipient_id`, `status` (enum:
  `pending`,`accepted`,`denied`,`blocked`), `accepted_at`, timestamps. Unique
  `(sender_id, recipient_id)`.
- **interactions_groups** — `user_id` (owner), `name`, `slug`. Unique
  `(user_id, slug)`.
- **interactions_group_members** — `group_id`, `member_id` (user). Unique
  `(group_id, member_id)`.
- **interactions_comments** — `user_id` (author), `commentable` morph,
  `parent_id` (nullable self-FK, nested), `body` (text, markdown), `status`
  (enum: `published`,`pending`,`spam`), `edited_at` (nullable), timestamps,
  soft-deletes. Indexed `(commentable_type, commentable_id, status)`.
- **interactions_comment_revisions** — `comment_id`, `body`, `edited_by`
  (user), `created_at` (append-only history).
- **interactions_mentions** — `mentionable` morph (the containing content: a
  comment, or any host model such as a chat message), `mentioned_user_id`,
  `created_at`. Unique `(mentionable_type, mentionable_id, mentioned_user_id)`.
- **interactions_emojis** — custom emoji registry: `shortcode` (unique),
  `name`, `media` (spatie media collection) or `url`, `is_active`, timestamps.
- **interactions_counters** — `subject` morph + `type` (string) + `count`
  (unsigned), unique `(subject_type, subject_id, type)`.

User FKs resolve `config('auth.providers.users.table','users')` and nullable-on-delete.

## 4. Public API (traits)

**Actor (User) — `Interactor`:**
`follow/unfollow/isFollowing`, `befriend/acceptFriendRequest/denyFriendRequest/unfriend/blockFriend/isFriendWith`,
`like/dislike/unlike/hasLiked`, `upvote/downvote/cancelVote/hasVoted`,
`rate/hasRated`, `favorite/unfavorite/hasFavorited`,
`subscribe/unsubscribe/hasSubscribed`,
`reactWith/toggleReaction/unreact/hasReactedWith`, `comment`.

**Target (any model) — granular** `Likeable`, `Voteable`, `Rateable`,
`Favoritable`, `Subscribable`, `Followable`, `Reactable`, `Commentable`,
`Mentionable`; **aggregate** `Interactable` composes them. Receipts:
`hasBeenLikedBy($user)`, `reactionSummary()` → `['🎉'=>5, ':party:'=>2]`,
`averageRating()`, `commentsCount()`, plus `withCount` relations.

`Interactions` facade exposes one-liners over the managers.

## 5. Services

- **MentionParser** — regex-parse `@handle` (configurable pattern, 1–N chars)
  against a configurable model pool (default User by `username`), rewrite body
  to links, record `interactions_mentions`, fire `UserMentioned`. Anti-dupe per
  target user.
- **EmojiResolver** — bundled unicode shortcode↔char dataset + custom-emoji
  lookup; validates a reaction emoji is allowed (unicode/custom per config).
- **CommentManager** — create/edit (revision)/moderate/soft-delete; resolves
  mentions on write.
- **ReactionManager** — react/toggle/unreact; updates counters.
- **CounterSync** observers — maintain `interactions_counters` on
  create/delete of interactions, reactions, comments.

## 6. Events & Notifications

Events: `Followed`, `Unfollowed`, `FriendRequested`, `FriendRequestAccepted`,
`FriendRequestDenied`, `Liked`, `Voted`, `Rated`, `Reacted`, `Commented`,
`CommentReplied`, `UserMentioned`.

Optional Notification classes (config `interactions.notifications.enabled`,
default false): `NewFollowerNotification`, `FriendRequestNotification`,
`MentionedNotification`, `CommentReplyNotification`. Channels configurable;
designed to plug into the host's existing notifiable users.

## 7. Filament (v3/v4/v5)

Version-dispatching `InteractionsPlugin::make()` → `V{3,4,5}\InteractionsPlugin`.
Resources:
- **CommentResource** — moderation: list/edit, approve / mark-spam / delete row
  actions, filter by status, show target + author.
- **CustomEmojiResource** — full CRUD, upload custom emoji.
- **FriendshipResource** — read-only admin overview (status filter).

Per-version PHPStan configs + Filament-major-guarded introspection tests + CI
matrix axis (mirrors the rest of the family).

## 8. Config (`config/interactions.php`)

- `user_model`, mentions `pool` (models + columns), mention `pattern`.
- `reactions`: `allow_unicode` (true), `allow_custom` (true),
  `max_per_user` (null = unlimited).
- `comments`: `nesting` (true), `markdown` (true), `default_status`
  (`published`|`pending`), `revisions` (true).
- `graph`: `friendships` (true), `groups` (true).
- `counters`: `driver` (`table`|`none`).
- `notifications`: `enabled` (false), `channels` (`['database']`), class map.
- `models`: overridable model bindings.

## 9. Retrofit plan (coordinated, after Interactions v1.0.0)

- **Forum v2.2.0** — `Forum\Models\Vote` → engagement votes; Forum models use
  `Voteable`; data migration `forum_votes` → `interactions_interactions`
  (type=vote); deprecation shim on `Vote`.
- **Blog v2.2.0** — `blog_comments` → `interactions_comments`
  (commentable = Post); Blog uses `Commentable`; data migration + shim.
- **Chat v2.3.0** — `Chat\Models\Reaction` → `interactions_reactions`,
  `Chat\Models\Mention` → `interactions_mentions`; Chat uses traits; data
  migrations + shims.

Each retrofit is its own repo release so the data migration is isolated and
independently testable.

## 10. Standards & build order

Family standards: `PackageServiceProvider`, `->discoversMigrations()`,
spatie/laravel-package-tools, Pest 3 + Testbench 10, PHPStan level 8 (larastan),
Pint, Rector; Filament v3/4/5 parallel dirs + per-version PHPStan + CI matrix
(PHP 8.4 / Laravel 12 / Filament 3·4·5). VCS composer repo for Core. Publish
tags `modules-interactions-{config,migrations}`.

**Internal build phases (each green before the next):**
1. Scaffold (composer, configs, provider, CI, TestCase).
2. Engagement core: migrations + models + `Interactor`/target traits +
   managers + counters + tests.
3. Reactions + Emoji (unicode + custom via MediaLibrary) + tests.
4. Comments + revisions + moderation + Mentions parser + tests.
5. Graph: follows + friendships + groups + tests.
6. Events + optional Notifications + facade + tests.
7. Filament v3/4/5 + per-version PHPStan + guarded tests.
8. CI green + README + CHANGELOG + release v1.0.0.
9. Retrofits: Forum v2.2.0, Blog v2.2.0, Chat v2.3.0 (separate releases).
