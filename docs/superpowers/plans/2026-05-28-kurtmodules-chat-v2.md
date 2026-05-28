# `ozankurt/laravel-modules-chat` v2.0 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build `ozankurt/laravel-modules-chat` v2.0.0 — a real-time chat module (rooms + DMs + threads + presence + reactions + attachments + @mentions) with Laravel broadcasting (Reverb-recommended, driver-agnostic). Filament admin for moderation. Greenfield package.

**Architecture:** Conversations have a `type` of `room` or `direct`. DMs are deterministic via a `dm_key` (`min_userid:max_userid`) so `directBetween(a, b)` always resolves to the same row. Messages are sent through `Conversation::send()` which dispatches `MessageSent` (broadcasted). Mentions are extracted at `Message::saving` and persisted to `chat_mentions` for fan-out + audit. Presence persists optional `heartbeat_at` rows; `chat:prune-presence` marks stale rows offline.

**Tech Stack:** PHP 8.4, Laravel 12/13, Filament 3/4/5, Pest 3, Testbench, spatie/laravel-medialibrary. Optional `laravel/reverb` as the recommended broadcaster (never started in tests — broadcasts asserted via `Event::fake()`).

**Spec:** [2026-05-28-kurtmodules-chat-v2-spec.md](../specs/2026-05-28-kurtmodules-chat-v2-spec.md)

**Prerequisite:** `ozankurt/laravel-modules-core` v2.0.0.

**Working directory:** `D:\Code\Projects\KurtModules-Chat`.

---

## Task 0–2: Scaffold

Same template as Library Tasks 0–2:
- Branch v2.0.
- `composer.json` with vendor `ozankurt/laravel-modules-chat`, namespace `Kurt\Modules\Chat\`. Add `illuminate/broadcasting: ^12.0 || ^13.0` to `require`. Add `laravel/reverb: ^1.0` to `require-dev` and `suggest`.
- Copy `pint.json`, `phpstan.neon`, `rector.php`, `.gitattributes`, `.gitignore`, `phpunit.xml.dist` from Core.

---

## Task 3: Config

`config/chat.php`:

```php
<?php

declare(strict_types=1);

use Kurt\Modules\Chat\Models\{Conversation, Mention, Message, Participant, Presence, Reaction};

return [
    'broadcasting' => [
        'enabled' => true,
        'connection' => env('CHAT_BROADCAST_CONNECTION'),
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
        'resolver' => null,
        'username_column' => 'username',
    ],
    'presence' => [
        'persist' => true,
        'heartbeat_seconds' => 30,
        'offline_after_seconds' => 90,
    ],
    'models' => [
        'conversation' => Conversation::class,
        'participant' => Participant::class,
        'message' => Message::class,
        'reaction' => Reaction::class,
        'mention' => Mention::class,
        'presence' => Presence::class,
    ],
];
```

Commit.

---

## Task 4: Enums

`src/Enums/{ConversationType,ConversationVisibility,ParticipantRole,ParticipantNotifications,PresenceStatus}.php` per spec §6. Trivial tests.

Commit.

---

## Task 5: Migrations

Six migrations (timestamps `2026_05_28_010001`–`010006`) under `database/migrations/`:

### chat_conversations
```php
Schema::create('chat_conversations', function (Blueprint $table) {
    $table->id();
    $table->string('type');
    $table->string('name')->nullable();
    $table->text('description')->nullable();
    $table->string('dm_key')->nullable()->unique();
    $table->string('visibility')->default('private');
    $table->foreignId('created_by')->constrained(config('auth.providers.users.table', 'users'))->restrictOnDelete();
    $table->timestamp('last_message_at')->nullable();
    $table->timestamps();
    $table->softDeletes();
    $table->index(['type', 'last_message_at']);
});
```

### chat_participants
```php
Schema::create('chat_participants', function (Blueprint $table) {
    $table->id();
    $table->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
    $table->foreignId('user_id')->constrained(config('auth.providers.users.table', 'users'))->cascadeOnDelete();
    $table->string('role')->default('member');
    $table->timestamp('joined_at');
    $table->timestamp('last_read_at')->nullable();
    $table->timestamp('muted_until')->nullable();
    $table->string('notifications')->default('all');
    $table->timestamps();
    $table->unique(['conversation_id', 'user_id']);
});
```

### chat_messages
```php
Schema::create('chat_messages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
    $table->foreignId('user_id')->constrained(config('auth.providers.users.table', 'users'))->cascadeOnDelete();
    $table->foreignId('parent_id')->nullable()->constrained('chat_messages')->cascadeOnDelete();
    $table->text('body');
    $table->timestamp('edited_at')->nullable();
    $table->timestamps();
    $table->softDeletes();
    $table->index(['conversation_id', 'created_at']);
    $table->index('parent_id');
});
```

### chat_reactions
```php
Schema::create('chat_reactions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('message_id')->constrained('chat_messages')->cascadeOnDelete();
    $table->foreignId('user_id')->constrained(config('auth.providers.users.table', 'users'))->cascadeOnDelete();
    $table->string('emoji');
    $table->timestamps();
    $table->unique(['message_id', 'user_id', 'emoji']);
});
```

### chat_mentions
```php
Schema::create('chat_mentions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('message_id')->constrained('chat_messages')->cascadeOnDelete();
    $table->foreignId('user_id')->constrained(config('auth.providers.users.table', 'users'))->cascadeOnDelete();
    $table->timestamp('seen_at')->nullable();
    $table->timestamps();
    $table->unique(['message_id', 'user_id']);
});
```

### chat_presence
```php
Schema::create('chat_presence', function (Blueprint $table) {
    $table->foreignId('user_id')->primary()->constrained(config('auth.providers.users.table', 'users'))->cascadeOnDelete();
    $table->string('status')->default('offline');
    $table->string('status_message')->nullable();
    $table->timestamp('heartbeat_at');
    $table->timestamps();
});
```

Commit.

---

## Task 6: ConversationKey

`src/Support/ConversationKey.php`:

```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Chat\Support;

use Illuminate\Database\Eloquent\Model;

final class ConversationKey
{
    public static function forDirect(Model $a, Model $b): string
    {
        $ids = [(string) $a->getKey(), (string) $b->getKey()];
        sort($ids, SORT_NATURAL);

        return implode(':', $ids);
    }
}
```

Tests: derive same key regardless of arg order; different pairs → different keys.

Commit.

---

## Task 7: Mention extraction

`src/Contracts/MentionResolver.php`:

```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Chat\Contracts;

/** @return array<int, int|string>  Array of user keys that should be marked as mentioned. */
interface MentionResolver
{
    public function resolve(string $body): array;
}
```

`src/Support/UsernameMentionResolver.php` (default impl):

```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Chat\Support;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Kurt\Modules\Chat\Contracts\MentionResolver;
use Kurt\Modules\Core\Contracts\UserResolver;

final class UsernameMentionResolver implements MentionResolver
{
    public function __construct(
        private readonly UserResolver $users,
        private readonly Repository $config,
    ) {}

    public function resolve(string $body): array
    {
        $pattern = (string) $this->config->get('chat.mentions.pattern');
        $column = (string) $this->config->get('chat.mentions.username_column', 'username');

        if (! preg_match_all($pattern, $body, $matches)) {
            return [];
        }

        $usernames = array_values(array_unique($matches[1] ?? []));
        if ($usernames === []) {
            return [];
        }

        return DB::table($this->users->table())
            ->whereIn($column, $usernames)
            ->pluck($this->users->primaryKey())
            ->all();
    }
}
```

`src/Support/MentionExtractor.php` (wrapper picking resolver from config):

```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Chat\Support;

use Illuminate\Contracts\Container\Container;
use Kurt\Modules\Chat\Contracts\MentionResolver;

final class MentionExtractor
{
    public function __construct(private readonly Container $container) {}

    public function extract(string $body): array
    {
        $resolverClass = config('chat.mentions.resolver') ?: UsernameMentionResolver::class;

        /** @var MentionResolver $resolver */
        $resolver = $this->container->make($resolverClass);

        return $resolver->resolve($body);
    }
}
```

Tests: cover pattern matching, custom resolver via config swap.

Commit.

---

## Task 8: Models

Six models. Highlights:

### Conversation
```php
class Conversation extends Model
{
    use HasFactory; use SoftDeletes;
    protected $table = 'chat_conversations';
    protected $casts = ['type' => ConversationType::class, 'visibility' => ConversationVisibility::class, 'last_message_at' => 'datetime'];

    public function participants(): HasMany { return $this->hasMany(Participant::class); }
    public function messages(): HasMany { return $this->hasMany(Message::class); }
    public function rootMessages(): HasMany { return $this->messages()->whereNull('parent_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(config('auth.providers.users.model'), 'created_by'); }

    public static function directBetween(\Illuminate\Database\Eloquent\Model $a, \Illuminate\Database\Eloquent\Model $b): self
    {
        $key = ConversationKey::forDirect($a, $b);
        $existing = static::query()->where('dm_key', $key)->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($a, $b, $key) {
            $convo = static::create([
                'type' => ConversationType::Direct,
                'dm_key' => $key,
                'created_by' => $a->getKey(),
                'visibility' => ConversationVisibility::Private,
            ]);

            $convo->participants()->createMany([
                ['user_id' => $a->getKey(), 'role' => ParticipantRole::Member, 'joined_at' => now(), 'notifications' => ParticipantNotifications::All],
                ['user_id' => $b->getKey(), 'role' => ParticipantRole::Member, 'joined_at' => now(), 'notifications' => ParticipantNotifications::All],
            ]);

            return $convo;
        });
    }

    public function send(\Illuminate\Database\Eloquent\Model $author, string $body, ?Message $parent = null): Message
    {
        $message = $this->messages()->create([
            'user_id' => $author->getKey(),
            'parent_id' => $parent?->id,
            'body' => $body,
        ]);

        $this->forceFill(['last_message_at' => now()])->save();

        MessageSent::dispatch($message->fresh(['user', 'mentions']));

        return $message;
    }

    public function markRead(\Illuminate\Database\Eloquent\Model $user): void
    {
        $this->participants()->where('user_id', $user->getKey())->update(['last_read_at' => now()]);
    }

    public function unreadCountFor(\Illuminate\Database\Eloquent\Model $user): int
    {
        $participant = $this->participants()->where('user_id', $user->getKey())->first();
        if (! $participant) {
            return 0;
        }

        return $this->messages()->where('created_at', '>', $participant->last_read_at ?? '1970-01-01')->count();
    }
}
```

### Message (HasMedia)
```php
class Message extends Model implements HasMedia
{
    use HasFactory; use InteractsWithMedia; use ResolvesUser; use SoftDeletes;
    protected $table = 'chat_messages';
    protected $casts = ['edited_at' => 'datetime'];

    public function conversation(): BelongsTo { return $this->belongsTo(Conversation::class); }
    public function user(): BelongsTo { return $this->userBelongsTo(); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function replies(): HasMany { return $this->hasMany(self::class, 'parent_id'); }
    public function reactions(): HasMany { return $this->hasMany(Reaction::class); }
    public function mentions(): HasMany { return $this->hasMany(Mention::class); }

    public function reactWith(\Illuminate\Database\Eloquent\Model $user, string $emoji): Reaction
    {
        return $this->reactions()->firstOrCreate(
            ['user_id' => $user->getKey(), 'emoji' => $emoji],
        );
    }

    public function unreactWith(\Illuminate\Database\Eloquent\Model $user, string $emoji): void
    {
        $this->reactions()->where(['user_id' => $user->getKey(), 'emoji' => $emoji])->delete();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('chat-attachments');
    }

    public function scopeRoots(Builder $q): Builder { return $q->whereNull('parent_id'); }
    public function scopeInThreadOf(Builder $q, self $root): Builder { return $q->where('parent_id', $root->id); }
}
```

### Participant, Reaction, Mention, Presence — straightforward as per spec.

Commit:
```bash
git add src/Models database/factories
git commit -m "feat(chat): add Conversation, Participant, Message, Reaction, Mention, Presence models"
```

---

## Task 9: Events + broadcasting

All events under `src/Events/` per spec §8.

`MessageSent`:
```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Chat\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Kurt\Modules\Chat\Models\Conversation;
use Kurt\Modules\Chat\Models\Message;

final class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable; use InteractsWithSockets; use SerializesModels;

    public function __construct(public readonly Message $message) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        $convo = $this->message->conversation;
        return [$convo->type->value === 'direct'
            ? new PrivateChannel("chat.dm.{$convo->id}")
            : new PrivateChannel("chat.room.{$convo->id}"),
        ];
    }
}
```

Other events follow the same skeleton — `MessageEdited`, `MessageDeleted`, `ReactionAdded`, `ReactionRemoved`, `UserStartedTyping`, `UserStoppedTyping`, `MentionFired`.

`routes/channels.php`:
```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Broadcast;
use Kurt\Modules\Chat\Models\Conversation;

Broadcast::channel('chat.room.{conversationId}', function ($user, int $conversationId) {
    return Conversation::query()
        ->where('id', $conversationId)
        ->whereHas('participants', fn ($q) => $q->where('user_id', $user->getAuthIdentifier()))
        ->exists();
});

Broadcast::channel('chat.dm.{conversationId}', function ($user, int $conversationId) {
    return Conversation::query()
        ->where('id', $conversationId)
        ->whereHas('participants', fn ($q) => $q->where('user_id', $user->getAuthIdentifier()))
        ->exists();
});

Broadcast::channel('chat.user.{userId}', function ($user, int $userId) {
    return (int) $user->getAuthIdentifier() === $userId;
});

Broadcast::channel('chat.conversation.{conversationId}', function ($user, int $conversationId) {
    if (! Conversation::query()
        ->where('id', $conversationId)
        ->whereHas('participants', fn ($q) => $q->where('user_id', $user->getAuthIdentifier()))
        ->exists()) {
        return false;
    }

    return [
        'id' => $user->getAuthIdentifier(),
        'name' => $user->name ?? $user->email,
    ];
});
```

Commit:
```bash
git add src/Events routes
git commit -m "feat(chat): add domain events + broadcast channels"
```

---

## Task 10: Observers, policies

- `MessageObserver`:
  - `creating`: enforce `message_max_length`.
  - `saving`: extract mentions via `MentionExtractor` → store on hidden `_pending_mention_user_ids` attribute.
  - `created`: persist mentions to `chat_mentions`; dispatch `MentionFired` per mention; dispatch `MessageSent`.
  - `updated`: dispatch `MessageEdited`.
  - `deleted`: dispatch `MessageDeleted($id, $conversationId)`.

- Policies per spec §10.

Commit.

---

## Task 11: Console commands

- `chat:prune-presence` — `chat_presence` rows older than `offline_after_seconds` → status=offline.
- `chat:demo` — seeds users + a room + a DM.

Commit.

---

## Task 12: Service provider

```php
final class ChatServiceProvider extends PackageServiceProvider
{
    protected function module(): string { return 'chat'; }

    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-modules-chat')
            ->hasConfigFile('chat')
            ->hasTranslations()
            ->hasMigrations([...])
            ->hasCommands([PrunePresenceCommand::class, DemoCommand::class]);
    }

    public function packageBooted(): void
    {
        Message::observe(MessageObserver::class);

        if (config('chat.broadcasting.enabled', true)) {
            Broadcast::routes();
            require __DIR__.'/../../routes/channels.php';
        }

        if ($this->app->runningInConsole()) {
            $this->app->booted(function () {
                $schedule = $this->app->make(\Illuminate\Console\Scheduling\Schedule::class);
                $schedule->command(PrunePresenceCommand::class)->everyMinute();
            });
        }

        $gate = $this->app['Illuminate\Contracts\Auth\Access\Gate'];
        $gate->policy(Conversation::class, ConversationPolicy::class);
        $gate->policy(Message::class, MessagePolicy::class);
        $gate->policy(Reaction::class, ReactionPolicy::class);
    }
}
```

Commit.

---

## Task 13: Filament resources V3/V4/V5

Resources: `ConversationResource`, `MessageResource` (moderation queue), `PresenceResource` (read-only widget).

For V5: load `epic-skills:filament-v5` skill.

Commit per version.

---

## Task 14: Tests

`tests/TestCase.php` extends `PackageTestCase`, registers ChatServiceProvider + migrations. Disables actual broadcasting in test bootstrap (`config()->set('chat.broadcasting.enabled', false)`).

Feature tests cover spec §15:
- DM idempotency (`directBetween` deterministic).
- Room create / join / leave / visibility scopes.
- `Conversation::send()` dispatches `MessageSent`.
- Edit window enforcement.
- Idempotent `reactWith` / `unreactWith`.
- Mention extraction + `MentionFired` per mention; custom resolver via config swap.
- Channel auth callbacks pass for participants, fail for non-participants.
- `chat:prune-presence`.

Commit.

---

## Task 15: CI + docs

Copy `.github/workflows/tests.yml` from Forum/Library (Laravel 12 + Filament axis). Add README/CHANGELOG/UPGRADE-2.0 (Initial release).

Commit.

---

## Task 16: Push + PR

```bash
git push -u origin v2.0
gh pr create --title "v2.0: initial release of ozankurt/laravel-modules-chat" --body ...
```

Tag after merge.

---

## Definition of done

- [ ] Pint + PHPStan + Pest green.
- [ ] CI matrix green.
- [ ] DM `directBetween` deterministic under concurrent calls (DB-level unique key test).
- [ ] `MessageSent` broadcast asserted via `Event::fake()`.
- [ ] Mention extraction works with default and custom resolver.
- [ ] Presence prune command moves stale rows offline.
- [ ] Filament resources V3/V4/V5 smoke green.
- [ ] Tagged `v2.0.0`.
