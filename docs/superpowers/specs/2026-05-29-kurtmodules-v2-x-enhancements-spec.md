# KurtModules v2.x — Enhancements Roadmap

**Date:** 2026-05-29
**Status:** Draft → user review pending
**Family:** [KurtModules v2 umbrella](./2026-05-28-kurtmodules-v2-design.md)

---

## 1. Purpose

The existing five modules (Core, Blog, Chat, Forum, Library) shipped v2.0.0. Chat already advanced to v2.1.0 with musonza/cmgmyr-inspired features. This spec catalogues the next-up enhancements for each module across v2.x point releases.

Each item is sized to fit a single PR. Items are tagged with a target version. Filament admin (planned for every module) is the long-running thread tying versions together.

## 2. Core (`ozankurt/laravel-modules-core`)

### v2.1
- **`AuditedByUser` trait** — `audit_created_by`, `audit_updated_by` columns + observer wiring. Reused by Blog/Forum/Library/Events for tracking who last touched a record.
- **`HasUuids` opt-in trait** — wraps the bigint→UUID switch via module config. Satisfies the umbrella spec §4.5 ("UUID opt-in via config"). Modules use the trait when their config opts in.
- **`AsEnum` cast helper** — sugar for null-safe enum casts; saves boilerplate in modules.

### v2.2
- **`spatie/laravel-activitylog` adapter** — opt-in subscriber that maps shipped domain events from sibling modules to activity log rows. Off by default.
- **`SettingsBag` helper** — small abstraction for per-module settings stored in a `kurtmodules_settings` JSON table, useful when consumers want config writable at runtime.

## 3. Blog (`ozankurt/laravel-modules-blog`)

### v2.1
- **Reading-time accessor** — `Post::readingTime(): int` from body word count + config-driven WPM. Free.
- **Auto-related posts** — `Post::related(int $limit = 5)`. Same category or shared tags, scored.
- **Filament v3 resources** — `PostResource`, `CategoryResource`, `TagResource`, `CommentResource` for Filament 3 only. v4/v5 ship in v2.2.

### v2.2
- **Series/Collections** — `blog_series` table + `blog_post_series` pivot. `Series` model with translatable name + description; `Post::series()` BelongsToMany.
- **Co-authors** — `blog_post_authors` pivot replaces single `user_id` semantics. Backward-compatible accessor on Post returns first co-author when only one.
- **Comment threading > 1 level** — expose `blog.comment_max_depth` config and remove the hard cap.
- **Filament v4 + v5 resources** — parallel to v3.

### v2.3
- **Scout search adapter** — optional dep on `laravel/scout`. Indexes Post + Category + Tag.
- **RSS/Atom feed helper** — `Post::feed()` returns a `RssFeed` value object the consumer can render.

## 4. Library (`ozankurt/laravel-modules-library`)

### v2.1
- **Item ratings + reviews** — `library_ratings` (1-5 star) + `library_reviews` (text). Aggregate on Item: `avg_rating`, `ratings_count`. Recompute via observer.
- **Bookmarks / favorites** — `library_bookmarks` table; per-user. Trait `HasLibraryBookmarks` exposes `bookmarkedItems()`.
- **Shareable links with TTL** — `Item::shareableUrl(int $expiresInSeconds = 86400)` returns a signed URL referencing a tokenized download/view route. Module ships the route under an opt-in `library.share_links.route_enabled`.
- **Recently accessed / trending** — `Item::scopeRecentlyAccessed`, `Item::scopeTrending(int $days = 7)`. Read from `access_log`.
- **Filament v3 resources** — `FolderResource`, `ItemResource` (with version + permission relation managers), `TagResource`, `AccessLogResource`.

### v2.2
- **Full-text search via Scout** — Item title/description/tags indexed.
- **Item recommendations** — simple "users who viewed this also viewed" using access_log.
- **Filament v4 + v5 resources**.

## 5. Forum (`ozankurt/laravel-modules-forum`)

### v2.1
- **Best answer** — `forum_threads.accepted_answer_id` (FK forum_posts) + `Thread::markAccepted(Post)` + `Thread::clearAccepted()`. Awarder rule: `AcceptedAnswerBadge`.
- **Quote replies** — `Post::quoteOf(Post): self` helper. Renders excerpt prefix into body.
- **Mention extraction** — reuse Chat's `MentionExtractor` (move it to Core in v2.2 — see §2). v2.1 ships a local copy or vendor-installs Chat as an opt-in dep.
- **Filament v3 resources** — Board, Thread, Post, ModerationReport, Badge, UserBadge.

### v2.2
- **Reputation / karma** — `forum_user_reputation` table; events `VoteCast`/`VoteRevoked` adjust. Decay job optional. Badge rules pivot to reputation-based.
- **Polls in threads** — `forum_polls` + `forum_poll_options` + `forum_poll_votes`. `Thread::poll(): ?Poll`.
- **Thread tags** — `forum_tags` + `forum_thread_tag` pivot, distinct from boards.
- **Filament v4 + v5 resources**.

### v2.3
- **Email digest for subscriptions** — daily/weekly summaries via Mail; opt-in per subscription.

## 6. Chat (`ozankurt/laravel-modules-chat`)

v2.1 shipped enhancements (archive, system messages, flags, cursor pagination, encryption, ChatParticipant trait). The next slot is for items deferred from v2.1.

### v2.2
- **User-level blocks** — `chat_blocks` table (`blocker_id`, `blocked_id`). Conversation filtering scope `excludeBlockedBy(User)`.
- **Message edit history** — `chat_message_edits` audit table (`message_id`, `old_body`, `edited_by`, `edited_at`).
- **Pinned messages per conversation** — `chat_message_pins` table. Helper `Conversation::pin(Message, by: User)`.
- **Scheduled messages** — `chat_messages.send_at` column. `chat:dispatch-scheduled` command releases due rows + dispatches `MessageSent`.
- **Read-receipts helper** — `Message::readBy()` returns participants whose `last_read_at >= $this->created_at`.
- **Filament v3 resources** — Conversation, Message (moderation queue), Presence.

### v2.3
- **Filament v4 + v5 resources**.
- **Reaction analytics** — top reactions per conversation, per period.

## 7. Cross-module follow-up: Packagist publishing

Currently all six packages (Core/Blog/Chat/Forum/Library/Events) install from GitHub via VCS repository declarations. Move each to Packagist:

1. Submit `https://github.com/OzanKurt/KurtModules-Core` at https://packagist.org/packages/submit.
2. Configure the GitHub webhook so future tags auto-update.
3. Repeat for Blog/Chat/Forum/Library/Events.
4. Each downstream module drops its `"repositories": [{ "type": "vcs", "url": "..." }]` entry in a follow-up PR.

This is one user-action per repo. Document in CONTRIBUTING.md.

## 8. Coordination

- Each enhancement is a separate PR on the module's repo, branching from `master`. Tag at the end with the bump (`v2.1.0`, `v2.2.0`).
- Cross-cutting items (Mention extractor moving to Core, Activity log adapter) happen in **Core first**, then downstream modules upgrade their Core constraint and consume it.
- Filament v3 lands per module ahead of v4/v5 because v3 is most mature and most documented; v4 and v5 ship together in the next slot.

## 9. Out of scope for v2.x

- Major API redesigns (would be v3.x).
- Cross-module bridges (Blog ↔ Forum comments etc.). Spec §3 of the umbrella forbids cross-module deps.
- Public-facing Blade views in any module. Headless + Filament admin only.
- `spatie/laravel-permission` requirement anywhere. Stays Gates/Policies.

## 10. Definition of done per module bump

- [ ] All shipped tests still pass.
- [ ] New tests cover every added public method/scope.
- [ ] Pint + PHPStan level 8 clean.
- [ ] CHANGELOG entry under the new version.
- [ ] UPGRADE notes when migrations introduce schema changes.
- [ ] Tag + GitHub release.

## 11. References

- [Umbrella v2 design](./2026-05-28-kurtmodules-v2-design.md)
- [Events v1 spec](./2026-05-29-kurtmodules-events-v1-spec.md)
- Per-module v2 specs in the same `specs/` directory.
