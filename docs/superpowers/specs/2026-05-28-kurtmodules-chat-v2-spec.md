# `ozankurt/laravel-modules-chat` v2.0 — Spec

**Repo:** `KurtModules-Chat`
**Date:** 2026-05-28
**Status:** Draft → user review pending
**Umbrella:** [2026-05-28-kurtmodules-v2-design.md](./2026-05-28-kurtmodules-v2-design.md)

---

## 1. Purpose

Real-time chat module for SaaS apps. Supports group **rooms**, **direct messages**, **threaded replies**, **presence**, **reactions**, **file attachments**, and **@mentions**, with broadcasting via Laravel Reverb (driver-agnostic). Ships a Filament admin for moderation.

## 2. Status

KurtModules-Chat v1 is empty (only `SECURITY.md`). v2.0 is the **initial release**.

## 3. Composer

```jsonc
{
  "name": "ozankurt/laravel-modules-chat",
  "description": "Real-time chat for Laravel: rooms, DMs, threads, presence, reactions, attachments, mentions.",
  "keywords": ["laravel", "filament", "chat", "messaging", "reverb"],
  "license": "MIT",
  "require": {
    "php": "^8.4",
    "illuminate/broadcasting": "^12.0 || ^13.0",
    "illuminate/contracts": "^12.0 || ^13.0",
    "illuminate/database": "^12.0 || ^13.0",
    "illuminate/support": "^12.0 || ^13.0",
    "ozankurt/laravel-modules-core": "^2.0",
    "spatie/laravel-medialibrary": "^11.0",
    "spatie/laravel-package-tools": "^1.92"
  },
  "require-dev": {
    "filament/filament": "^3.0 || ^4.0 || ^5.0",
    "laravel/reverb": "^1.0",
    "larastan/larastan": "^3.0",
    "laravel/pint": "^1.18",
    "orchestra/testbench": "^9.0 || ^10.0",
    "pestphp/pest": "^3.0",
    "pestphp/pest-plugin-laravel": "^3.0"
  },
  "suggest": {
    "laravel/reverb": "First-party WebSocket server for Laravel; recommended Chat transport."
  }
}
```

## 4. Concepts

- **Conversation** — abstract container. Two concrete kinds via `type` column:
  - `Room` (`type=room`) — named, multi-participant, joinable.
  - `Direct` (`type=direct`) — exactly two participants, surfaced as a private DM. Direct conversations have a deterministic `dm_key` so a (userA, userB) pair always maps to the same row.
- **Message** — belongs to a conversation; optional `parent_id` for **threaded** replies (one level deep — replies have no children).
- **Participant** — pivot between a user and a conversation, with `role` (`owner`, `admin`, `member`) and per-user state (`last_read_at`, `muted_until`, `notifications`).
- **Reaction** — `emoji` (string, unicode or `:shortcode:`) by a user on a message; unique per (message, user, emoji).
- **Attachment** — file uploaded via Spatie medialibrary, attached to a message; resolved by media collection `chat-attachments`.
- **Mention** — extracted from message body; stored as rows in `chat_mentions` for searchability + notification fan-out.

## 5. Tables

```
chat_conversations
  id, type (string — room|direct), name (string nullable),
  description (text nullable),
  dm_key (string nullable, unique), -- "min(user_a),max(user_b)" for type=direct
  visibility (string — enum), -- public|unlisted|private (rooms only)
  created_by (FK users.id, restrictOnDelete),
  last_message_at (timestamp nullable, indexed),
  created_at, updated_at, deleted_at
  index(type, last_message_at)

chat_participants
  id, conversation_id (FK, cascadeOnDelete), user_id (FK users.id, cascadeOnDelete),
  role (string — owner|admin|member),
  joined_at, last_read_at (timestamp nullable),
  muted_until (timestamp nullable),
  notifications (string — all|mentions|none),
  created_at, updated_at,
  unique(conversation_id, user_id)

chat_messages
  id, conversation_id (FK, cascadeOnDelete),
  user_id (FK users.id, cascadeOnDelete),
  parent_id (nullable, self FK, cascadeOnDelete),
  body (text),
  edited_at (timestamp nullable),
  created_at, updated_at, deleted_at
  index(conversation_id, created_at)
  index(parent_id)

chat_reactions
  id, message_id (FK, cascadeOnDelete), user_id (FK users.id, cascadeOnDelete),
  emoji (string),
  created_at, updated_at,
  unique(message_id, user_id, emoji)

chat_mentions
  id, message_id (FK, cascadeOnDelete),
  user_id (FK users.id, cascadeOnDelete),
  seen_at (timestamp nullable),
  created_at, updated_at,
  unique(message_id, user_id)

chat_presence  -- ephemeral, optional persistence
  user_id (PK, FK users.id, cascadeOnDelete),
  status (string — online|away|dnd|offline),
  status_message (string nullable),
  heartbeat_at (timestamp),
  created_at, updated_at
```

## 6. Enums

```php
enum ConversationType: string { case Room='room'; case Direct='direct'; }
enum ConversationVisibility: string { case Public='public'; case Unlisted='unlisted'; case Private='private'; }
enum ParticipantRole: string { case Owner='owner'; case Admin='admin'; case Member='member'; }
enum ParticipantNotifications: string { case All='all'; case Mentions='mentions'; case None='none'; }
enum PresenceStatus: string { case Online='online'; case Away='away'; case Dnd='dnd'; case Offline='offline'; }
```

## 7. Models

```
Kurt\Modules\Chat\Models\
  Conversation
  Participant
  Message  (Spatie\MediaLibrary\HasMedia)
  Reaction
  Mention
  Presence
```

Selected scopes:

- `Conversation::scopeRooms()`, `::scopeDirect()`, `::scopeVisibleTo(User)`, `::scopeWithUnreadFor(User)`.
- `Message::scopeRoots()` (parent_id null), `::scopeInThreadOf(Message)`.

Selected methods:

- `Conversation::messagesPaginated(int $perPage=50, ?Carbon $before=null)` — cursor pagination.
- `Conversation::markRead(User $user)` — sets `participant.last_read_at = now()`.
- `Conversation::unreadCountFor(User $user): int`.
- `Message::reactWith(User $user, string $emoji): Reaction` — idempotent.
- `Message::unreactWith(User $user, string $emoji): void`.
- `Conversation::directBetween(User $a, User $b): Conversation` — get-or-create with `dm_key`.

Mentions are extracted at `Message::saving` via a `MentionExtractor` service. Default pattern: `@{username}`. Consumer can supply a different resolver in config.

## 8. Events (all broadcast)

| Event | Channels | Notes |
|---|---|---|
| `MessageSent(Message)` | `private-chat.room.{id}` or `private-chat.dm.{conversationId}` | `ShouldBroadcastNow`. |
| `MessageEdited(Message)` | same | |
| `MessageDeleted(int $messageId, int $conversationId)` | same | Lean payload. |
| `ReactionAdded(Reaction)` | same | |
| `ReactionRemoved(int $messageId, int $userId, string $emoji)` | same | |
| `UserStartedTyping(User, int $conversationId)` | `presence-chat.conversation.{id}` | client-only echo recommended. |
| `UserStoppedTyping(User, int $conversationId)` | same | |
| `MentionFired(Mention)` | private user channel `private-chat.user.{userId}` | for notification fan-out. |

## 9. Broadcasting

- `routes/channels.php` shipped, registered via `Broadcast::routes()` if `config('chat.broadcasting.enabled', true)`.
- Authorization callbacks check `Participant` membership.
- Module is **driver-agnostic** — works with Reverb (recommended), Pusher, Ably; consumer sets `BROADCAST_CONNECTION` in their app.

## 10. Policies

`ConversationPolicy`, `MessagePolicy`, `ReactionPolicy`. Gates:

- `viewConversation`: participant or staff.
- `sendMessage`: participant.
- `editMessage` / `deleteMessage`: author within `chat.edit_window_minutes`, or staff (`canModerateChat` gate).
- `react`: participant.
- `manageRoom` (rename, invite, kick): role >= `admin` of that conversation.

## 11. Filament resources

- `ConversationResource` (list rooms + DMs; toggle filter).
- `MessageResource` (moderation queue: filter by reported/flagged in a future iteration; v2.0 has soft-delete + restore actions).
- `PresenceResource` (read-only widget).

Parallel V3 / V4 / V5 namespaces as per the umbrella spec.

## 12. Trait + contract

```
Kurt\Modules\Chat\Concerns\IsChatParticipant
Kurt\Modules\Chat\Contracts\ChatParticipant
```

Methods: `chatConversations()`, `chatMessages()`, `chatPresence()`, `getChatDisplayName(): string`, `getChatAvatarUrl(): ?string`.

## 13. Config (`config/chat.php`)

```php
return [
    'broadcasting' => [
        'enabled' => true,
        'connection' => env('CHAT_BROADCAST_CONNECTION'), // null = default
    ],
    'edit_window_minutes' => 15,
    'message_max_length' => 4000,
    'attachments' => [
        'disk' => env('CHAT_MEDIA_DISK', 'public'),
        'max_size_kb' => 25_000,
        'allowed_mimes' => ['image/*', 'video/mp4', 'application/pdf'],
    ],
    'mentions' => [
        'pattern' => '/@([a-zA-Z0-9_.-]{2,40})/',
        'resolver' => null, // FQCN of class implementing MentionResolver; null = look up by username column
        'username_column' => 'username',
    ],
    'presence' => [
        'persist' => true,
        'heartbeat_seconds' => 30,
        'offline_after_seconds' => 90,
    ],
    'models' => [/* same pattern as Blog */],
];
```

## 14. Console commands

- `chat:prune-presence` — marks stale heartbeats as `offline`. Scheduled every minute.
- `chat:demo` — seeds users + a room + a DM with sample messages and reactions.

## 15. Test coverage targets

| Suite | Cases |
|---|---|
| Unit | `MentionExtractor`, enum casts, DM `dm_key` derivation |
| Feature/Conversation | DM get-or-create, room create/join/leave, visibility scopes |
| Feature/Messaging | Send + edit + delete, edit window enforcement |
| Feature/Reactions | Idempotent add/remove |
| Feature/Mentions | Extraction + `MentionFired` event, custom resolver via config |
| Feature/Broadcast | `Event::fake()` + `Event::assertDispatched(MessageSent::class)` and channel-name assertions |
| Feature/Presence | Heartbeat updates, `prune-presence` command |
| Feature/Filament/V{3,4,5} | Resource smoke |

Reverb is **never** booted in tests; broadcasting is asserted via `Event::fake()`.

## 16. Directory layout

```
src/
  Broadcasting/ -- channel auth classes
  Concerns/IsChatParticipant.php
  Console/Commands/{PrunePresenceCommand,DemoCommand}.php
  Contracts/{ChatParticipant,MentionResolver}.php
  Enums/{ConversationType,ConversationVisibility,ParticipantRole,ParticipantNotifications,PresenceStatus}.php
  Events/{…as §8}
  Filament/{V3,V4,V5}/Resources/{Conversation,Message,Presence}Resource.php
  Models/{Conversation,Message,Mention,Participant,Presence,Reaction}.php
  Observers/MessageObserver.php
  Policies/{Conversation,Message,Reaction}Policy.php
  Providers/{ChatServiceProvider,ChatFilamentServiceProvider}.php
  Support/{ConversationKey,MentionExtractor,UsernameMentionResolver}.php
config/chat.php
database/factories/…
database/migrations/
  2026_05_28_010001_create_chat_conversations_table.php
  …010002_create_chat_participants_table.php
  …010003_create_chat_messages_table.php
  …010004_create_chat_reactions_table.php
  …010005_create_chat_mentions_table.php
  …010006_create_chat_presence_table.php
lang/en/chat.php
lang/tr/chat.php
routes/channels.php
tests/…
```

## 17. Definition of done

- [ ] Matrix CI green.
- [ ] `MessageSent` broadcast asserted in tests.
- [ ] DM idempotency: 100 concurrent calls to `directBetween` produce exactly one row (DB-level unique index test).
- [ ] Presence prune command moves stale rows.
- [ ] Filament resources render across V3/V4/V5.
- [ ] Tagged `v2.0.0`.
