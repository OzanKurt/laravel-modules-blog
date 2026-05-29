# `ozankurt/laravel-modules-events` v1.0 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build `ozankurt/laravel-modules-events` v1.0.0 — a payment-agnostic event management module covering events, sessions, tickets, applications, requirements, sale queue, waitlist, refunds, transfers, add-ons, price tiers, discount codes, referrals, sponsors, announcements, audit log, templates, GDPR helpers, optional Notifications. Headless. Filament admin lands in v1.1.

**Architecture:** Five sub-aggregates (Catalog, Ticketing, Attendance, Eligibility, Flow) under `Kurt\Modules\Events\`. Top-level `Events` facade exposes ergonomic API. Same scaffold as Core/Blog/Library/Forum/Chat (Spatie's `PackageServiceProvider`, Pest 3, PHPStan 8, Pint, GH Actions matrix). 32 tables.

**Tech Stack:** PHP 8.4, Laravel 12/13, `ozankurt/laravel-modules-core ^2.0`, `spatie/laravel-medialibrary ^11`, `spatie/laravel-translatable ^6.11`, `cviebrock/eloquent-sluggable ^11|^12`, Pest 3, Testbench 10, Larastan 3.

**Spec:** [2026-05-29-kurtmodules-events-v1-spec.md](../specs/2026-05-29-kurtmodules-events-v1-spec.md)

**Working directory:** `D:\Code\Projects\KurtModules-Events`. The repo does NOT yet exist on GitHub; Task 0 creates it.

**Defer to v1.1:** Filament resources (Task 21 in spec), Scout integration. Document in CHANGELOG.

---

## File structure

Code goes under `src/` organised by sub-aggregate plus cross-cutting folders:

```
src/
  Catalog/
    Contracts/EventChatBridge.php
    Enums/{EventStatus, EventVisibility, RecurrenceFrequency, OrganizerRole, AttendeeListVisibility}.php
    Models/{Event, EventCategory, EventTag, EventOrganizer, EventTemplate, Session}.php
    Observers/{EventObserver, SessionObserver}.php
    Support/{IcsExporter, RecurrenceExpander, EventCloner}.php
  Ticketing/
    Enums/{TicketTypeMode, OrderStatus, TicketStatus, DiscountKind, DiscountApplicationScope, DiscountScope, AddOnPurchaseStatus}.php
    Models/{TicketType, PriceTier, Order, OrderItem, OrderItemAssignment, Ticket, TicketAddOn, TicketAddOnPurchase, ReferralLink, DiscountCode, DiscountCodeUsage}.php
    Observers/{OrderObserver, TicketObserver}.php
    Support/{PriceCalculator, QrTokenSigner, TicketIssuer, TransferEngine, ReferralAttributor}.php
  Attendance/
    Enums/{ApplicationStatus, AttendeeStatus, AnnouncementAudience, AnnouncementRecipientStatus}.php
    Models/{Attendee, Application, AttendanceForm, AttendanceResponse, Announcement, AnnouncementRecipient}.php
    Observers/{AttendeeObserver, ApplicationObserver}.php
    Support/AnnouncementDispatcher.php
  Eligibility/
    Contracts/{DocumentVerifier, RequirementEvaluator, GroupResolver}.php
    Engine/{RequirementEngine, CheckResult, EvaluationOutcome}.php
    Enums/{RequirementType, CheckStatus, VerificationStatus}.php
    Evaluators/{AgeMinEvaluator, AgeMaxEvaluator, DocumentEvaluator, GroupMembershipEvaluator, GenderEvaluator, FreeFormEvaluator, CustomRuleEvaluator}.php
    Models/{Requirement, RequirementCheck, DocumentUpload, DocumentVerification}.php
  Flow/
    Contracts/QueueChallengeProvider.php
    Enums/{QueueStatus, WaitlistStatus, RefundStatus, RefundReason, SponsorStatus, PayoutStatus}.php
    Models/{SaleQueueEntry, WaitlistEntry, Refund, Sponsor, SponsorTier, SponsorCompTicket, PayoutLedgerEntry, CheckInAttempt, AuditLogEntry}.php
    Support/{QueueReleaser, WaitlistPromoter, RefundCoordinator, PayoutAccruer, AuditLogWriter}.php
  Concerns/{IsEventOrganizer, IsEventAttendee}.php
  Contracts/{EventOrganizer, EventAttendee}.php
  Events/  -- domain events; many files
  Exceptions/  -- typed exceptions
  Notifications/  -- optional Laravel Notifications
  Policies/{EventPolicy, TicketTypePolicy, OrderPolicy, ApplicationPolicy, RefundPolicy, QueuePolicy, WaitlistPolicy}.php
  Providers/EventsServiceProvider.php
  Support/Events.php  -- top-level facade
```

---

## Task 0: Create GitHub repo + local scaffold

**Files:**
- New: `D:\Code\Projects\KurtModules-Events\` (entire directory)

- [ ] **Step 1: Create GitHub repo + clone**

```bash
gh repo create OzanKurt/KurtModules-Events --public --description "Payment-agnostic event management for Laravel: events, tickets, applications, queue, waitlist, refunds, transfers, requirements, sponsors, announcements, GDPR helpers." --license=mit
cd D:/Code/Projects
git clone https://github.com/OzanKurt/KurtModules-Events.git
cd KurtModules-Events
```

- [ ] **Step 2: Copy `SECURITY.md` from `../KurtModules-Core/`**

```bash
cp ../KurtModules-Core/SECURITY.md .
```

- [ ] **Step 3: Commit initial files + create v1.0 branch**

```bash
git add SECURITY.md
git commit -m "chore: bootstrap repo with SECURITY.md"
git push origin master
git switch -c v1.0
```

---

## Task 1: composer.json

**Files:**
- Create: `composer.json`

- [ ] **Step 1: Write composer.json**

```json
{
  "name": "ozankurt/laravel-modules-events",
  "description": "Payment-agnostic event management for Laravel: events, tickets, applications, queue, waitlist, refunds, transfers, requirements, sponsors, announcements.",
  "keywords": ["laravel", "filament", "kurtmodules", "events", "ticketing"],
  "license": "MIT",
  "authors": [{ "name": "Ozan Kurt", "email": "ozankurt2@gmail.com" }],
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
    "mockery/mockery": "^1.6",
    "orchestra/testbench": "^10.0",
    "pestphp/pest": "^3.0",
    "pestphp/pest-plugin-laravel": "^3.0",
    "rector/rector": "^2.0"
  },
  "autoload": { "psr-4": { "Kurt\\Modules\\Events\\": "src/" } },
  "autoload-dev": {
    "psr-4": {
      "Kurt\\Modules\\Events\\Tests\\": "tests/",
      "Database\\Factories\\Kurt\\Modules\\Events\\": "database/factories/"
    }
  },
  "extra": { "laravel": { "providers": ["Kurt\\Modules\\Events\\Providers\\EventsServiceProvider"] } },
  "repositories": [
    { "type": "vcs", "url": "https://github.com/OzanKurt/KurtModules-Core" }
  ],
  "config": { "sort-packages": true, "allow-plugins": { "pestphp/pest-plugin": true } },
  "scripts": {
    "test": "vendor/bin/pest",
    "lint": "vendor/bin/pint --test",
    "format": "vendor/bin/pint",
    "stan": "vendor/bin/phpstan analyse --memory-limit=2G"
  },
  "minimum-stability": "stable",
  "prefer-stable": true
}
```

- [ ] **Step 2: composer install**

```bash
C:/laragon/bin/php/php-8.4.5-nts-Win32-vs17-x64/php.exe composer.phar install --no-interaction
```
Expected: clean install with `ozankurt/laravel-modules-core v2.0.0` resolved from VCS.

- [ ] **Step 3: Commit**

```bash
git add composer.json composer.lock
git commit -m "feat: composer scaffold for laravel-modules-events v1"
```

---

## Task 2: Dev configs

**Files:**
- Copy from `../KurtModules-Chat/`: `pint.json`, `phpstan.neon`, `rector.php`, `.gitattributes`, `.gitignore`, `phpunit.xml.dist`.

- [ ] **Step 1: Copy + commit**

```bash
cp ../KurtModules-Chat/pint.json .
cp ../KurtModules-Chat/phpstan.neon .
cp ../KurtModules-Chat/rector.php .
cp ../KurtModules-Chat/.gitattributes .
cp ../KurtModules-Chat/.gitignore .
cp ../KurtModules-Chat/phpunit.xml.dist .

git add pint.json phpstan.neon rector.php .gitattributes .gitignore phpunit.xml.dist
git commit -m "chore: add pint, phpstan, rector, phpunit configs"
```

- [ ] **Step 2: Verify**

```bash
C:/laragon/bin/php/php-8.4.5-nts-Win32-vs17-x64/php.exe vendor/bin/pint --test
```
Expected: pass.

---

## Task 3: config/events.php

**Files:**
- Create: `config/events.php`

- [ ] **Step 1: Write config**

(Use the full block from spec §19. Reproduced abbreviated below — engineer should copy verbatim from spec.)

```php
<?php

declare(strict_types=1);

return [
    'currency' => env('EVENTS_DEFAULT_CURRENCY', 'USD'),

    'queue' => [
        'enabled' => true,
        'active_concurrency' => 100,
        'active_window_seconds' => 300,
        'heartbeat_timeout_seconds' => 60,
    ],

    'waitlist' => [
        'enabled' => true,
        'claim_window_seconds' => 600,
    ],

    'recurrence' => [
        'enabled' => true,
        'window_days' => 90,
    ],

    'refunds' => [
        'consumer_protection_window_days' => 14,
    ],

    'transfers' => [
        'allowed_by_default' => true,
    ],

    'tax' => [
        'enabled' => true,
    ],

    'publishing' => [
        'require_approval' => false,
    ],

    'audit' => [
        'enabled' => true,
        'capture_context' => true,
    ],

    'anti_bot' => [
        'queue_challenge' => null,
    ],

    'chat_bridge' => [
        'provider' => null,
    ],

    'gdpr' => [
        'retention_days' => null,
        'anonymize_audit_log_actor' => true,
    ],

    'payouts' => [
        'auto_accrue_on_order_paid' => true,
    ],

    'documents' => [
        'disk' => env('EVENTS_DOCUMENT_DISK', 'private'),
        'verifier' => null,
    ],

    'requirements' => [
        'evaluators' => [
            'age_min' => \Kurt\Modules\Events\Eligibility\Evaluators\AgeMinEvaluator::class,
            'age_max' => \Kurt\Modules\Events\Eligibility\Evaluators\AgeMaxEvaluator::class,
            'document' => \Kurt\Modules\Events\Eligibility\Evaluators\DocumentEvaluator::class,
            'group_membership' => \Kurt\Modules\Events\Eligibility\Evaluators\GroupMembershipEvaluator::class,
            'gender' => \Kurt\Modules\Events\Eligibility\Evaluators\GenderEvaluator::class,
            'free_form_question' => \Kurt\Modules\Events\Eligibility\Evaluators\FreeFormEvaluator::class,
            'custom_rule' => \Kurt\Modules\Events\Eligibility\Evaluators\CustomRuleEvaluator::class,
        ],
        'group_resolver' => null,
    ],

    'notifications' => [
        'enabled' => false,
        'channels' => ['mail', 'database'],
    ],

    'broadcasting' => [
        'enabled' => true,
    ],

    'reminders' => [
        'enabled' => true,
        'before_hours' => [24, 1],
    ],

    'orders' => [
        'pending_timeout_minutes' => 15,
    ],

    'check_in' => [
        'token_lifetime_minutes' => 0,
        'replay_protection' => true,
    ],

    'search' => [
        'geo' => [
            'enabled' => false,
            'distance_unit' => 'km',
        ],
    ],

    'models' => [
        // overrides for downstream model swaps
    ],
];
```

- [ ] **Step 2: Commit**

```bash
git add config/events.php
git commit -m "feat(events): add config file"
```

---

## Task 4: Enums

**Files:** all under `src/{Catalog,Ticketing,Attendance,Eligibility,Flow}/Enums/`. Plus unit tests under `tests/Unit/Enums/`.

Strict TDD for each enum: failing test (class not found) → implementation → green.

- [ ] **Step 1: Catalog enums**

`src/Catalog/Enums/EventStatus.php`:
```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Events\Catalog\Enums;

enum EventStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Published = 'published';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
}
```

Repeat for `EventVisibility` (Public/Unlisted/Private), `RecurrenceFrequency` (None/Daily/Weekly/Monthly/Yearly), `OrganizerRole` (Owner/Manager/Scanner), `AttendeeListVisibility` (Private/OrganizerOnly/AttendeesOnly/Public). Cases-and-values test per enum.

- [ ] **Step 2: Ticketing enums**

`TicketTypeMode` (Open/Application/Rsvp), `OrderStatus` (Pending/Paid/Cancelled/Refunded/PartiallyRefunded), `TicketStatus` (Issued/Cancelled/Refunded/CheckedIn/Transferred), `DiscountKind` (Percent/FlatAmount), `DiscountApplicationScope` (Order/PerTicket), `DiscountScope` (Global/EventsSubset), `AddOnPurchaseStatus` (Pending/Paid/Cancelled/Refunded). Cases-and-values test per enum.

- [ ] **Step 3: Attendance enums**

`ApplicationStatus` (Pending/Approved/Rejected/Withdrawn/Expired), `AttendeeStatus` (Registered/Cancelled/CheckedIn/NoShow), `AnnouncementAudience` (All/Registered/CheckedIn/ByTicketType/BySession), `AnnouncementRecipientStatus` (Pending/Sent/Failed/Opened). Cases-and-values test per enum.

- [ ] **Step 4: Eligibility enums**

`RequirementType` (AgeMin=age_min/AgeMax=age_max/Document/GroupMembership=group_membership/Gender/FreeFormQuestion=free_form_question/CustomRule=custom_rule), `CheckStatus` (Pending/Passed/Failed/Waived), `VerificationStatus` (Pending/Verified/Rejected). Cases-and-values test per enum.

- [ ] **Step 5: Flow enums**

`QueueStatus` (Waiting/Active/Expired/Completed/Abandoned), `WaitlistStatus` (Waiting/Offered/Claimed/Expired), `RefundStatus` (Pending/Processed/Failed), `RefundReason` (Rejection/CancelledEvent=cancelled_event/AttendeeRequest=attendee_request/OrganizerInitiated=organizer_initiated/Other), `SponsorStatus` (Pending/Active/Cancelled), `PayoutStatus` (Accrued/PaidOut=paid_out/Reversed). Cases-and-values test per enum.

- [ ] **Step 6: Run + commit**

```bash
vendor/bin/pest tests/Unit/Enums
git add src/{Catalog,Ticketing,Attendance,Eligibility,Flow}/Enums tests/Unit/Enums
git commit -m "feat(events): add enums across sub-aggregates"
```
Expected: ~30 enum tests pass.

---

## Task 5: Migrations

32 anonymous migration files under `database/migrations/`, timestamped `2026_05_29_NNNNNN_*.php` (NNNNNN reserves order across sub-aggregates). Use exact column definitions from spec §5.

Order is significant — tables with FKs come after their referents. Sequence:

1. `…_000010_create_events_categories_table.php`
2. `…_000020_create_events_tags_table.php`
3. `…_000030_create_events_events_table.php` (FK categories)
4. `…_000040_create_events_event_tag_table.php`
5. `…_000050_create_events_event_organizers_table.php` (FK events)
6. `…_000055_create_events_event_templates_table.php`
7. `…_000060_create_events_sessions_table.php` (FK events)
8. `…_000070_create_events_attendance_forms_table.php` (FK events)
9. `…_000080_create_events_ticket_types_table.php` (FK events + attendance_forms)
10. `…_000085_create_events_ticket_type_session_table.php` (pivot)
11. `…_000090_create_events_price_tiers_table.php` (FK ticket_types)
12. `…_000100_create_events_ticket_add_ons_table.php` (FK events)
13. `…_000105_create_events_sponsor_tiers_table.php` (FK events + ticket_types)
14. `…_000110_create_events_referral_links_table.php` (FK events)
15. `…_000115_create_events_discount_codes_table.php`
16. `…_000118_create_events_discount_code_event_table.php` (pivot)
17. `…_000120_create_events_orders_table.php` (FK events + referral_links + discount_codes)
18. `…_000130_create_events_order_items_table.php` (FK orders + ticket_types + price_tiers)
19. `…_000135_create_events_order_item_assignments_table.php` (FK order_items)
20. `…_000140_create_events_tickets_table.php` (FK order_items + assignments + ticket_types + events)
21. `…_000145_create_events_session_check_ins_table.php` (FK sessions + tickets)
22. `…_000150_create_events_ticket_add_on_purchases_table.php` (FK tickets + add_ons + order_items)
23. `…_000160_create_events_applications_table.php` (FK events + ticket_types + orders)
24. `…_000170_create_events_attendees_table.php` (FK tickets)
25. `…_000175_create_events_attendance_responses_table.php` (FK attendees + forms)
26. `…_000180_create_events_announcements_table.php` (FK events)
27. `…_000185_create_events_announcement_recipients_table.php` (FK announcements + attendees)
28. `…_000190_create_events_requirements_table.php` (FK events + ticket_types)
29. `…_000200_create_events_requirement_checks_table.php` (FK attendees + applications + requirements)
30. `…_000210_create_events_document_uploads_table.php` (FK attendees + requirements)
31. `…_000220_create_events_document_verifications_table.php` (FK uploads)
32. `…_000230_create_events_discount_code_usages_table.php` (FK discount_codes + orders)
33. `…_000240_create_events_sponsors_table.php` (FK events + sponsor_tiers + orders)
34. `…_000245_create_events_sponsor_comp_tickets_table.php` (FK sponsors + tickets)
35. `…_000250_create_events_sale_queue_entries_table.php` (FK events)
36. `…_000260_create_events_waitlist_entries_table.php` (FK ticket_types)
37. `…_000270_create_events_refunds_table.php` (FK orders + tickets)
38. `…_000280_create_events_payout_ledger_table.php` (FK orders)
39. `…_000290_create_events_check_in_attempts_table.php` (FK tickets)
40. `…_000300_create_events_audit_log_table.php`

Each migration is an anonymous class `extends Migration` with `up()` and `down()`. Copy column definitions from spec §5 exactly.

- [ ] **Step 1–40: Write each migration in order**

For each migration: write the file, do not run yet (some tables FK to later ones — but ordering above resolves that). Verify per file.

- [ ] **Step 41: Run + verify**

```bash
vendor/bin/pest tests/Feature/MigrationsTest.php
```

Add a smoke test under `tests/Feature/MigrationsTest.php`:
```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('creates every events_* table', function () {
    foreach ([
        'events_categories', 'events_tags', 'events_events', 'events_event_tag',
        'events_event_organizers', 'events_event_templates', 'events_sessions',
        'events_attendance_forms', 'events_ticket_types', 'events_ticket_type_session',
        'events_price_tiers', 'events_ticket_add_ons', 'events_sponsor_tiers',
        'events_referral_links', 'events_discount_codes', 'events_discount_code_event',
        'events_orders', 'events_order_items', 'events_order_item_assignments',
        'events_tickets', 'events_session_check_ins', 'events_ticket_add_on_purchases',
        'events_applications', 'events_attendees', 'events_attendance_responses',
        'events_announcements', 'events_announcement_recipients', 'events_requirements',
        'events_requirement_checks', 'events_document_uploads', 'events_document_verifications',
        'events_discount_code_usages', 'events_sponsors', 'events_sponsor_comp_tickets',
        'events_sale_queue_entries', 'events_waitlist_entries', 'events_refunds',
        'events_payout_ledger', 'events_check_in_attempts', 'events_audit_log',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue("missing {$table}");
    }
});
```

- [ ] **Step 42: Commit**

```bash
git add database/migrations tests/Feature/MigrationsTest.php
git commit -m "feat(events): add all v1 migrations across sub-aggregates"
```

---

## Task 6: Catalog models + factories

**Files:**
- `src/Catalog/Models/{Event,EventCategory,EventTag,EventOrganizer,EventTemplate,Session}.php`
- `database/factories/Catalog/{EventFactory,EventCategoryFactory,EventTagFactory,EventOrganizerFactory,EventTemplateFactory,SessionFactory}.php`

For each model use `HasFactory` + `newFactory()` override (as in Blog/Library/Forum/Chat). Translatable strings via `HasTranslations`. Sluggable on Event + Session + EventTemplate + EventCategory + EventTag.

- [ ] **Step 1: EventCategory model + factory**

```php
namespace Kurt\Modules\Events\Catalog\Models;

class EventCategory extends Model
{
    use HasFactory, HasTranslations, Sluggable, SoftDeletes;
    protected $table = 'events_categories';
    public array $translatable = ['name', 'description'];
    protected $fillable = ['parent_id','slug','name','description','position'];
    public function sluggable(): array { return ['slug' => ['source' => 'name', 'onUpdate' => true]]; }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id'); }
    public function events(): HasMany { return $this->hasMany(Event::class, 'category_id'); }
}
```

Factory definition + commit per model.

- [ ] **Step 2: EventTag**

Same pattern: HasTranslations + Sluggable, no SoftDeletes, slug + name + position.

- [ ] **Step 3: Event** (largest)

Translatable: `title`, `description`. Cast: `status` → EventStatus, `visibility` → EventVisibility, `attendee_list_visibility` → AttendeeListVisibility, `starts_at`, `ends_at`, `sale_starts_at`, `sale_ends_at`, `cancelled_at` → datetime, `recurrence_rule` → array, `reminder_intervals` → array.

Relations:
```php
public function category(): BelongsTo { return $this->belongsTo(EventCategory::class); }
public function tags(): BelongsToMany { return $this->belongsToMany(EventTag::class, 'events_event_tag')->withTimestamps(); }
public function organizers(): HasMany { return $this->hasMany(EventOrganizer::class); }
public function sessions(): HasMany { return $this->hasMany(Session::class); }
public function ticketTypes(): HasMany { return $this->hasMany(TicketType::class); }
public function orders(): HasMany { return $this->hasMany(Order::class); }
public function attendees(): HasManyThrough { return $this->hasManyThrough(Attendee::class, Ticket::class); }
public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_event_id'); }
public function occurrences(): HasMany { return $this->hasMany(self::class, 'parent_event_id'); }
```

Scopes (per spec §16.9):
```php
public function scopePublished(Builder $q): Builder { return $q->where('status', EventStatus::Published->value)->whereNull('cancelled_at'); }
public function scopeUpcoming(Builder $q): Builder { return $q->where('starts_at', '>=', now()); }
public function scopePast(Builder $q): Builder { return $q->where('starts_at', '<', now()); }
public function scopeInCategory(Builder $q, EventCategory|int $cat): Builder { return $q->where('category_id', $cat instanceof EventCategory ? $cat->id : $cat); }
public function scopeWithTags(Builder $q, array|int $tagIds, bool $matchAll = false): Builder { /* whereHas pattern */ }
public function scopeOrganizedBy(Builder $q, Model $user): Builder { return $q->whereHas('organizers', fn ($o) => $o->where('user_id', $user->getKey())); }
public function scopeNearLocation(Builder $q, float $lat, float $lng, float $radius): Builder
{
    if (! config('events.search.geo.enabled')) {
        return $q->whereRaw('1=0'); // disabled
    }
    $unit = config('events.search.geo.distance_unit') === 'mi' ? 3959 : 6371;
    return $q->whereNotNull('latitude')->whereNotNull('longitude')
        ->selectRaw("*, ({$unit} * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance", [$lat, $lng, $lat])
        ->having('distance', '<=', $radius);
}
```

Method:
```php
public function chatRoomId(): ?string
{
    $bridge = config('events.chat_bridge.provider');
    if ($bridge === null) return null;
    return app($bridge)->roomIdFor($this);
}
```

Test (in `tests/Feature/Catalog/EventTest.php`):
```php
it('persists translatable title across locales', function () {
    $user = StubUser::create(['email' => 'a@b.c']);
    $event = Event::factory()->create(['title' => ['en' => 'Concert', 'tr' => 'Konser']]);
    expect($event->getTranslation('title', 'tr'))->toBe('Konser');
});

it('filters published + upcoming via scopes', function () {
    Event::factory()->create(['status' => EventStatus::Draft]);
    Event::factory()->published()->upcoming()->create();
    expect(Event::query()->published()->upcoming()->count())->toBe(1);
});
```

Factory:
```php
public function definition(): array
{
    $title = $this->faker->unique()->sentence(4);
    return [
        'slug' => str($title)->slug(),
        'title' => ['en' => $title],
        'status' => EventStatus::Draft,
        'visibility' => EventVisibility::Public,
        'attendee_list_visibility' => AttendeeListVisibility::OrganizerOnly,
        'starts_at' => now()->addDays(30),
        'ends_at' => now()->addDays(30)->addHours(2),
        'timezone' => 'UTC',
    ];
}
public function published(): static { return $this->state(fn () => ['status' => EventStatus::Published]); }
public function upcoming(): static { return $this->state(fn () => ['starts_at' => now()->addDays(7), 'ends_at' => now()->addDays(7)->addHours(2)]); }
```

- [ ] **Step 4: EventOrganizer pivot model**

```php
class EventOrganizer extends Model
{
    use HasFactory;
    protected $table = 'events_event_organizers';
    protected $fillable = ['event_id','user_id','role','commission_basis_points'];
    protected $casts = ['role' => OrganizerRole::class, 'commission_basis_points' => 'integer'];
    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
    public function user(): BelongsTo { return $this->belongsTo(config('auth.providers.users.model'), 'user_id'); }
}
```

- [ ] **Step 5: Session**

Translatable `title`, `description`. Sluggable. Casts `starts_at`/`ends_at` → datetime.

Relations: `event()`, `ticketTypes()` belongsToMany via `events_ticket_type_session`, `checkIns()` HasMany.

- [ ] **Step 6: EventTemplate**

```php
class EventTemplate extends Model
{
    use HasFactory, Sluggable, SoftDeletes;
    protected $table = 'events_event_templates';
    protected $fillable = ['owner_id','slug','name','description','payload','is_public'];
    protected $casts = ['payload' => 'array', 'is_public' => 'boolean'];
    public function sluggable(): array { return ['slug' => ['source' => 'name']]; }
    public function owner(): BelongsTo { return $this->belongsTo(config('auth.providers.users.model'), 'owner_id'); }
}
```

- [ ] **Step 7: Run + commit**

```bash
vendor/bin/pint
vendor/bin/phpstan analyse --memory-limit=2G
vendor/bin/pest tests/Feature/Catalog tests/Unit
git add src/Catalog database/factories/Catalog tests
git commit -m "feat(events): add Catalog models, factories, scopes, chatRoomId bridge"
```

---

## Task 7: Ticketing models + factories

**Files:**
- `src/Ticketing/Models/{TicketType,PriceTier,Order,OrderItem,OrderItemAssignment,Ticket,TicketAddOn,TicketAddOnPurchase,ReferralLink,DiscountCode,DiscountCodeUsage}.php`
- Matching factories under `database/factories/Ticketing/`.

Highlights per model (each gets a small TDD test):

- [ ] **Step 1: TicketType**

Translatable `name`, `description`. Casts `mode` → TicketTypeMode, `refundable`/`transferable`/`consumer_protection_exempt` → bool, `sale_starts_at`/`sale_ends_at` → datetime. Fillable includes all transfer/EU columns from spec §5.2.

Relations:
```php
public function event(): BelongsTo { return $this->belongsTo(Event::class); }
public function priceTiers(): HasMany { return $this->hasMany(PriceTier::class); }
public function sessions(): BelongsToMany { return $this->belongsToMany(Session::class, 'events_ticket_type_session')->withTimestamps(); }
public function tickets(): HasMany { return $this->hasMany(Ticket::class); }
public function attendanceForm(): BelongsTo { return $this->belongsTo(AttendanceForm::class); }
```

Method:
```php
public function activePriceTier(?Carbon $at = null): ?PriceTier
{
    $when = $at ?? now();
    return $this->priceTiers()->orderBy('position')->get()
        ->first(fn (PriceTier $t) =>
            ($t->starts_at === null || $t->starts_at <= $when) &&
            ($t->ends_at === null || $t->ends_at > $when)
        );
}

public function currentUnitPriceMinor(?Carbon $at = null): int
{
    return $this->activePriceTier($at)?->price_minor ?? $this->price_minor;
}
```

- [ ] **Step 2: PriceTier**

```php
protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'price_minor' => 'integer'];
protected $fillable = ['ticket_type_id','name','starts_at','ends_at','price_minor','capacity','position'];
public function ticketType(): BelongsTo { return $this->belongsTo(TicketType::class); }
```

- [ ] **Step 3: Order**

```php
protected $casts = ['status' => OrderStatus::class, 'paid_at' => 'datetime', 'assignment_completed_at' => 'datetime'];
protected $fillable = ['event_id','user_id','status','subtotal_minor','discount_minor','tax_minor','total_minor','tax_rate_basis_points','currency','discount_code_id','referral_link_id','processor','processor_reference','metadata'];
public function event(): BelongsTo { return $this->belongsTo(Event::class); }
public function buyer(): BelongsTo { return $this->belongsTo(config('auth.providers.users.model'), 'user_id'); }
public function items(): HasMany { return $this->hasMany(OrderItem::class); }
public function tickets(): HasManyThrough { return $this->hasManyThrough(Ticket::class, OrderItem::class); }
public function discountCode(): BelongsTo { return $this->belongsTo(DiscountCode::class); }
public function referralLink(): BelongsTo { return $this->belongsTo(ReferralLink::class); }
public function refunds(): HasMany { return $this->hasMany(Refund::class); }
public function payoutEntries(): HasMany { return $this->hasMany(PayoutLedgerEntry::class); }

public function recomputeTotalsAfterRefund(): void
{
    $refundedMinor = $this->refunds()->where('status', RefundStatus::Processed->value)->sum('amount_minor');
    if ($refundedMinor >= $this->total_minor) {
        $this->status = OrderStatus::Refunded;
    } elseif ($refundedMinor > 0) {
        $this->status = OrderStatus::PartiallyRefunded;
    }
    $this->save();
}
```

- [ ] **Step 4: OrderItem + OrderItemAssignment**

OrderItem fillable includes `price_tier_id`. Relations: order, ticketType, priceTier, assignments (HasMany), tickets.

OrderItemAssignment fillable: order_item_id, seat_index, holder_user_id, holder_name, holder_email, holder_metadata. Cast holder_metadata→array.

- [ ] **Step 5: Ticket**

Casts: `status` → TicketStatus, `checked_in_at`/`transferred_at` → datetime, `metadata` → array.
Fillable: holder_id, holder_name, holder_email, status, qr_token, etc.
Relations: orderItem, ticketType, event, holder (config user model), transferredFrom (config user model), addOnPurchases, transferFeeOrder.
Methods:
```php
public function isCheckedIn(): bool { return $this->status === TicketStatus::CheckedIn; }
public function transferable(): bool
{
    if (! $this->ticketType->transferable) return false;
    if ($this->ticketType->transfer_deadline_hours_before_event !== null) {
        $cutoff = $this->event->starts_at->copy()->subHours($this->ticketType->transfer_deadline_hours_before_event);
        if (now()->greaterThanOrEqualTo($cutoff)) return false;
    }
    return true;
}
```

- [ ] **Step 6: TicketAddOn + TicketAddOnPurchase**

Translatable name + description on TicketAddOn. Cast status → AddOnPurchaseStatus on TicketAddOnPurchase.

- [ ] **Step 7: ReferralLink**

Fillable: event_id, organizer_id, code, landing_path, commission_basis_points, max_uses, uses_count, expires_at, active. Cast expires_at→datetime, active→bool.

- [ ] **Step 8: DiscountCode + DiscountCodeUsage**

DiscountCode casts: kind → DiscountKind, applies_to → DiscountScope, application_scope → DiscountApplicationScope, starts_at/expires_at → datetime, active → bool.

Methods:
```php
public function isActive(?Carbon $at = null): bool
{
    $when = $at ?? now();
    if (! $this->active) return false;
    if ($this->starts_at && $when->lt($this->starts_at)) return false;
    if ($this->expires_at && $when->gt($this->expires_at)) return false;
    if ($this->max_uses_total && $this->uses_count >= $this->max_uses_total) return false;
    return true;
}

public function usedByUserCount(Model $user): int
{
    return $this->usages()->where('user_id', $user->getKey())->count();
}

public function appliesToEvent(Event $event): bool
{
    if ($this->applies_to === DiscountScope::Global) return true;
    return $this->events()->where('events_events.id', $event->id)->exists();
}
```

Relations: events() BelongsToMany via `events_discount_code_event`, usages() HasMany DiscountCodeUsage.

- [ ] **Step 9: Run + commit**

```bash
vendor/bin/pint
vendor/bin/phpstan analyse
vendor/bin/pest tests/Feature/Ticketing tests/Unit/Ticketing
git add src/Ticketing database/factories/Ticketing tests
git commit -m "feat(events): add Ticketing models, factories, basic methods"
```

Feature tests per model (CRUD + relations + simple methods). At least 10 new tests.

---

## Task 8: Attendance + Eligibility models + factories

**Files:** under `src/Attendance/Models/`, `src/Eligibility/Models/`, factories under same trees.

- [ ] **Step 1: Attendee**

Casts status → AttendeeStatus, profile → array.
Fillable: event_id, ticket_id, user_id, status, profile.
Relations: event, ticket, user (config), responses (HasMany AttendanceResponse), checkIns (HasMany SessionCheckIn).

Method:
```php
public function listVisibility(): AttendeeListVisibility
{
    $eventLevel = $this->event->attendee_list_visibility;
    $selfLevel = ($this->profile['list_visibility'] ?? 'public') === 'private'
        ? AttendeeListVisibility::Private
        : AttendeeListVisibility::Public;

    // Most restrictive wins; ordered Private(0) < OrganizerOnly(1) < AttendeesOnly(2) < Public(3)
    return $eventLevel->isMoreRestrictiveThan($selfLevel) ? $eventLevel : $selfLevel;
}
```

Add helper on enum:
```php
enum AttendeeListVisibility: string {
    // ... cases
    public function rank(): int { return match($this) {
        self::Private => 0, self::OrganizerOnly => 1, self::AttendeesOnly => 2, self::Public => 3,
    }; }
    public function isMoreRestrictiveThan(self $other): bool { return $this->rank() < $other->rank(); }
}
```

- [ ] **Step 2: Application**

Cast status → ApplicationStatus, submitted_at/decided_at → datetime. Fillable: event_id, ticket_type_id, applicant_id, status, decision_note, reservation_order_id, metadata.
Relations: event, ticketType, applicant (user), reservationOrder (Order), requirementChecks (HasMany RequirementCheck).

- [ ] **Step 3: AttendanceForm + AttendanceResponse**

AttendanceForm fillable: event_id, name, schema. Cast schema→array. Method `validate(array $answers): array<string,string>` returning errors.

AttendanceResponse fillable: attendee_id, attendance_form_id, answers. Cast answers→array.

- [ ] **Step 4: Announcement + AnnouncementRecipient**

Announcement: cast audience → AnnouncementAudience, audience_filter → array, scheduled_for/sent_at → datetime.
Relations: event, author (user), recipients (HasMany), attendees (HasManyThrough).

AnnouncementRecipient: cast status → AnnouncementRecipientStatus, sent_at/opened_at → datetime.

- [ ] **Step 5: Eligibility models**

Requirement: cast type → RequirementType, payload → array, strict → bool. Belongs to event OR ticketType (one of two).

RequirementCheck: cast status → CheckStatus, result → array. Belongs to attendee OR application (one of two), and requirement.

DocumentUpload: HasMedia (Spatie). Fillable: attendee_id, requirement_id, kind, filename, mime_type, byte_size, metadata.

DocumentVerification: cast status → VerificationStatus, decided_at → datetime. Belongs to documentUpload.

- [ ] **Step 6: Run + commit**

```bash
vendor/bin/pint
vendor/bin/phpstan analyse
vendor/bin/pest tests/Feature/Attendance tests/Feature/Eligibility
git add src/Attendance src/Eligibility database/factories tests
git commit -m "feat(events): add Attendance + Eligibility models with relations"
```

---

## Task 9: Flow models + factories

**Files:** under `src/Flow/Models/`.

- [ ] **Step 1: SaleQueueEntry**

Cast status → QueueStatus, joined_at/released_at/expires_at/last_heartbeat_at → datetime. Fillable: event_id, user_id, joined_at, position, last_heartbeat_at, status.

- [ ] **Step 2: WaitlistEntry**

Cast status → WaitlistStatus, offered_at/claim_expires_at → datetime.

- [ ] **Step 3: Refund**

Cast status → RefundStatus, reason → RefundReason, processed_at → datetime, metadata → array. Relations: order, ticket, requester (user), processedBy (user).

- [ ] **Step 4: Sponsor + SponsorTier + SponsorCompTicket**

SponsorTier: translatable name (not declared — keep flat string for sponsor tier names; spec §5.x says `name (string)` so no translation). Fillable per spec.

Sponsor: HasMedia for logo. Cast status → SponsorStatus.

SponsorCompTicket: fillable sponsor_id, ticket_id, issued_at.

- [ ] **Step 5: PayoutLedgerEntry**

Table name = `events_payout_ledger`. Cast status → PayoutStatus, paid_out_at → datetime.

- [ ] **Step 6: CheckInAttempt + AuditLogEntry**

CheckInAttempt: cast occurred_at → datetime; succeeded → bool. Fillable: ticket_id, scanner_user_id, nonce, ip, user_agent, succeeded, failure_reason, occurred_at.

AuditLogEntry: table `events_audit_log`. Cast changes/context → array, occurred_at → datetime. Fillable: event_id, actor_id, actor_type, action, subject_type, subject_id, changes, context, occurred_at.

- [ ] **Step 7: Run + commit**

```bash
vendor/bin/pint && vendor/bin/phpstan analyse && vendor/bin/pest
git add src/Flow database/factories tests
git commit -m "feat(events): add Flow models (queue, waitlist, refund, sponsor, payout, audit log)"
```

---

## Task 10: Contracts + value objects

**Files:** under `src/{Catalog,Ticketing,Eligibility,Flow}/Contracts/`, `src/Contracts/`, `src/Eligibility/Engine/`.

- [ ] **Step 1: Eligibility contracts**

`src/Eligibility/Contracts/RequirementEvaluator.php`:
```php
namespace Kurt\Modules\Events\Eligibility\Contracts;

use Illuminate\Database\Eloquent\Model;
use Kurt\Modules\Events\Eligibility\Engine\CheckResult;

interface RequirementEvaluator
{
    /** @param array<string, mixed> $payload */
    public function evaluate(Model $attendee, array $payload, array $context = []): CheckResult;
}
```

`src/Eligibility/Contracts/DocumentVerifier.php` — optional, FQCN binding.
`src/Eligibility/Contracts/GroupResolver.php` — `groupsFor(Model $user): array`.

- [ ] **Step 2: Engine value objects**

`src/Eligibility/Engine/CheckResult.php`:
```php
namespace Kurt\Modules\Events\Eligibility\Engine;

use Kurt\Modules\Events\Eligibility\Enums\CheckStatus;

final readonly class CheckResult
{
    public function __construct(
        public CheckStatus $status,
        public ?string $message = null,
        public array $data = [],
    ) {}

    public static function pass(array $data = []): self { return new self(CheckStatus::Passed, null, $data); }
    public static function fail(string $message, array $data = []): self { return new self(CheckStatus::Failed, $message, $data); }
    public static function pending(string $message = 'Awaiting review'): self { return new self(CheckStatus::Pending, $message); }
}
```

`src/Eligibility/Engine/EvaluationOutcome.php` — readonly with `bool $allPassed`, `bool $anyStrictFailed`, `array<int, RequirementCheck> $checks`.

- [ ] **Step 3: Flow contracts**

`src/Flow/Contracts/QueueChallengeProvider.php` per spec §13a.

- [ ] **Step 4: Catalog contracts**

`src/Catalog/Contracts/EventChatBridge.php` per spec §13a.

- [ ] **Step 5: Person-side contracts + traits**

`src/Contracts/EventOrganizer.php`:
```php
interface EventOrganizer
{
    public function getKey(): int|string;
    public function getEventOrganizerDisplayName(): string;
}
```

`src/Contracts/EventAttendee.php`:
```php
interface EventAttendee
{
    public function getKey(): int|string;
    public function getEventAttendeeDisplayName(): string;
    public function getEventAttendeeEmail(): string;
}
```

`src/Concerns/IsEventOrganizer.php` — relations `eventsOrganized(): BelongsToMany`, `eventOrders(): HasMany`, etc.

`src/Concerns/IsEventAttendee.php` — `eventTickets(): HasMany`, `eventApplications(): HasMany`, `eventAttendances(): HasMany`.

- [ ] **Step 6: Run + commit**

```bash
vendor/bin/pint && vendor/bin/phpstan analyse
git add src/Eligibility/Contracts src/Eligibility/Engine src/Flow/Contracts src/Catalog/Contracts src/Contracts src/Concerns
git commit -m "feat(events): add contracts + engine value objects"
```

---

## Task 11: Default requirement evaluators

**Files:** `src/Eligibility/Evaluators/{AgeMinEvaluator,AgeMaxEvaluator,DocumentEvaluator,GroupMembershipEvaluator,GenderEvaluator,FreeFormEvaluator,CustomRuleEvaluator}.php`.

- [ ] **Step 1: AgeMinEvaluator**

Strict TDD. Test:
```php
it('passes when attendee is at least min age', function () {
    $event = Event::factory()->create();
    $ticket = Ticket::factory()->forEvent($event)->create();
    $attendee = Attendee::factory()->create([
        'event_id' => $event->id, 'ticket_id' => $ticket->id,
        'profile' => ['date_of_birth' => now()->subYears(20)->toDateString()],
    ]);
    $result = (new AgeMinEvaluator())->evaluate($attendee, ['min' => 18]);
    expect($result->status)->toBe(CheckStatus::Passed);
});

it('fails when attendee is younger than min age', function () {
    /* analogous, age 16 vs min 18, expect Failed */
});

it('returns pending when DOB missing', function () { /* expect Pending with message */ });
```

Implementation:
```php
final class AgeMinEvaluator implements RequirementEvaluator
{
    public function evaluate(Model $attendee, array $payload, array $context = []): CheckResult
    {
        $dob = $attendee->profile['date_of_birth'] ?? null;
        if (! is_string($dob)) {
            return CheckResult::pending('Date of birth not provided');
        }
        $age = Carbon::parse($dob)->age;
        $min = (int) ($payload['min'] ?? 0);
        return $age >= $min
            ? CheckResult::pass(['age' => $age])
            : CheckResult::fail("Minimum age is {$min}", ['age' => $age]);
    }
}
```

- [ ] **Step 2: AgeMaxEvaluator**

Symmetric.

- [ ] **Step 3: DocumentEvaluator**

Returns `pending` until a `DocumentVerification` exists with `status=verified`. Looks up via `DocumentUpload` for `(attendee, requirement)`.

```php
public function evaluate(Model $attendee, array $payload, array $context = []): CheckResult
{
    $requirementId = $context['requirement_id'] ?? null;
    if (! $requirementId) return CheckResult::pending('Awaiting upload');
    $upload = DocumentUpload::query()->where('attendee_id', $attendee->id)->where('requirement_id', $requirementId)->latest()->first();
    if (! $upload) return CheckResult::pending('No document uploaded');
    $verification = $upload->verifications()->latest()->first();
    if (! $verification || $verification->status === VerificationStatus::Pending) return CheckResult::pending('Awaiting review');
    return $verification->status === VerificationStatus::Verified
        ? CheckResult::pass(['document_upload_id' => $upload->id])
        : CheckResult::fail('Document rejected', ['document_upload_id' => $upload->id]);
}
```

- [ ] **Step 4: GroupMembershipEvaluator**

Uses bound `GroupResolver`:
```php
$resolverClass = config('events.requirements.group_resolver');
if (! $resolverClass) return CheckResult::pending('No group resolver configured');
$resolver = app($resolverClass);
$userGroups = $resolver->groupsFor($attendee->user);
$required = (array) ($payload['group'] ?? []);
$matched = array_intersect($userGroups, is_array($required) ? $required : [$required]);
return ! empty($matched)
    ? CheckResult::pass(['matched_groups' => array_values($matched)])
    : CheckResult::fail('Not a member of required group');
```

- [ ] **Step 5: GenderEvaluator**

Reads `attendee.profile.gender`. Passes when in `payload.allowed` array.

- [ ] **Step 6: FreeFormEvaluator**

Always returns `CheckResult::pending('Awaiting reviewer')`. Manual organizer review flips the check status outside the engine.

- [ ] **Step 7: CustomRuleEvaluator**

```php
public function evaluate(Model $attendee, array $payload, array $context = []): CheckResult
{
    $fqcn = $payload['evaluator'] ?? null;
    if (! $fqcn || ! class_exists($fqcn)) return CheckResult::fail('Invalid custom evaluator FQCN');
    $impl = app($fqcn);
    if (! $impl instanceof RequirementEvaluator) return CheckResult::fail('Class does not implement RequirementEvaluator');
    return $impl->evaluate($attendee, $payload['config'] ?? [], $context);
}
```

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint && vendor/bin/phpstan analyse && vendor/bin/pest tests/Feature/Eligibility/Evaluators
git add src/Eligibility/Evaluators tests/Feature/Eligibility/Evaluators
git commit -m "feat(events): add default requirement evaluators (age, doc, group, gender, free-form, custom)"
```

---

## Task 12: RequirementEngine

**Files:** `src/Eligibility/Engine/RequirementEngine.php`, `tests/Feature/Eligibility/RequirementEngineTest.php`.

- [ ] **Step 1: Failing test**

```php
it('evaluates all requirements attached to a ticket type and persists checks', function () {
    config()->set('events.requirements.evaluators.age_min', AgeMinEvaluator::class);

    $event = Event::factory()->create();
    $type = TicketType::factory()->forEvent($event)->create();
    Requirement::factory()->forTicketType($type)->create([
        'type' => RequirementType::AgeMin, 'payload' => ['min' => 18], 'strict' => true,
    ]);

    $attendee = Attendee::factory()->forEvent($event)->create([
        'profile' => ['date_of_birth' => now()->subYears(25)->toDateString()],
    ]);

    $engine = app(RequirementEngine::class);
    $outcome = $engine->evaluateFor($attendee, $type);

    expect($outcome->allPassed)->toBeTrue();
    expect($outcome->anyStrictFailed)->toBeFalse();
    expect(RequirementCheck::query()->where('attendee_id', $attendee->id)->count())->toBe(1);
});

it('flags strict failures and persists Failed status', function () { /* age 16 vs min 18, strict=true */ });
```

- [ ] **Step 2: Implementation**

```php
final class RequirementEngine
{
    public function __construct(private readonly Container $container, private readonly Repository $config) {}

    public function evaluateFor(Attendee $attendee, TicketType $ticketType): EvaluationOutcome
    {
        $requirements = Requirement::query()
            ->where(fn ($q) => $q->where('event_id', $ticketType->event_id)->orWhere('ticket_type_id', $ticketType->id))
            ->get();

        $checks = [];
        $allPassed = true;
        $anyStrictFailed = false;

        foreach ($requirements as $requirement) {
            $evaluatorClass = $this->config->get("events.requirements.evaluators.{$requirement->type->value}");
            if (! $evaluatorClass) continue;

            $evaluator = $this->container->make($evaluatorClass);
            $result = $evaluator->evaluate($attendee, $requirement->payload ?? [], ['requirement_id' => $requirement->id]);

            $check = RequirementCheck::query()->updateOrCreate(
                ['attendee_id' => $attendee->id, 'requirement_id' => $requirement->id],
                ['status' => $result->status, 'result' => array_merge($result->data, ['message' => $result->message])],
            );

            $checks[] = $check;
            if ($result->status !== CheckStatus::Passed) $allPassed = false;
            if ($result->status === CheckStatus::Failed && $requirement->strict) $anyStrictFailed = true;
        }

        return new EvaluationOutcome($allPassed, $anyStrictFailed, $checks);
    }
}
```

- [ ] **Step 3: Run + commit**

```bash
vendor/bin/pest tests/Feature/Eligibility/RequirementEngineTest.php
git add src/Eligibility/Engine/RequirementEngine.php tests
git commit -m "feat(events): add RequirementEngine"
```

---

## Task 13: Pricing engine + discount codes

**Files:** `src/Ticketing/Support/{PriceCalculator,PriceBreakdown,DiscountCodeNotApplicable}.php`, tests under `tests/Feature/Ticketing/PriceCalculatorTest.php`.

- [ ] **Step 1: PriceBreakdown value object**

```php
final readonly class PriceBreakdown
{
    public function __construct(
        public int $subtotalMinor,
        public int $discountMinor,
        public int $totalMinor,
        public string $currency,
    ) {}
}
```

- [ ] **Step 2: DiscountCodeNotApplicable typed exception**

```php
final class DiscountCodeNotApplicable extends RuntimeException
{
    public static function code(string $reason): self { return new self($reason); }
}
```

- [ ] **Step 3: PriceCalculator tests**

```php
it('applies a percent discount', function () {
    $code = DiscountCode::factory()->create([
        'kind' => DiscountKind::Percent, 'amount_minor' => 1000, // 10%
        'applies_to' => DiscountScope::Global, 'active' => true,
    ]);
    $type = TicketType::factory()->create(['price_minor' => 10_000, 'currency' => 'USD']);

    $draftOrder = $this->draftOrderWith($type, quantity: 2);
    $breakdown = (new PriceCalculator())->apply($draftOrder, $code);

    expect($breakdown->subtotalMinor)->toBe(20_000);
    expect($breakdown->discountMinor)->toBe(2_000);
    expect($breakdown->totalMinor)->toBe(18_000);
});

it('rejects code when currency mismatches', function () { /* expect DiscountCodeNotApplicable */ });
it('rejects expired code', function () { /* … */ });
it('rejects when per-user limit reached', function () { /* … */ });
it('applies flat-amount per-ticket scope', function () { /* … */ });
it('clamps discount to subtotal so total >= 0', function () { /* … */ });
```

- [ ] **Step 4: PriceCalculator implementation**

```php
final class PriceCalculator
{
    public function apply(DraftOrder $draft, ?DiscountCode $code = null): PriceBreakdown
    {
        $subtotal = $draft->lineTotals()->sum();
        $currency = $draft->currency;
        $discount = 0;

        if ($code !== null) {
            $this->guardApplicable($code, $draft);
            $discount = match ($code->kind) {
                DiscountKind::Percent => intdiv($subtotal * $code->amount_minor, 10_000),
                DiscountKind::FlatAmount => $code->application_scope === DiscountApplicationScope::Order
                    ? $code->amount_minor
                    : $code->amount_minor * $draft->totalQuantity(),
            };
            $discount = min($discount, $subtotal);
        }

        $total = max(0, $subtotal - $discount);

        return new PriceBreakdown($subtotal, $discount, $total, $currency);
    }

    private function guardApplicable(DiscountCode $code, DraftOrder $draft): void
    {
        if (! $code->isActive()) throw DiscountCodeNotApplicable::code('inactive');
        if ($code->kind === DiscountKind::FlatAmount && $code->currency !== $draft->currency) {
            throw DiscountCodeNotApplicable::code('currency_mismatch');
        }
        if ($code->max_uses_per_user && $code->usedByUserCount($draft->buyer) >= $code->max_uses_per_user) {
            throw DiscountCodeNotApplicable::code('per_user_limit');
        }
        if (! $code->appliesToEvent($draft->event)) throw DiscountCodeNotApplicable::code('scope_mismatch');
    }
}
```

`DraftOrder` is a small value object holding pending OrderItems + event + buyer + currency. Define alongside.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pest tests/Feature/Ticketing/PriceCalculatorTest.php
git add src/Ticketing/Support tests
git commit -m "feat(events): add PriceCalculator + DiscountCodeNotApplicable"
```

---

## Task 14: QR token signer + check-in helpers

**Files:** `src/Ticketing/Support/QrTokenSigner.php`, `src/Ticketing/Support/TicketCheckIn.php`, tests.

- [ ] **Step 1: QrTokenSigner**

```php
final class QrTokenSigner
{
    public function __construct(private readonly string $appKey) {}

    public function sign(int $ticketId, int $eventId, ?int $issuedAt = null): string
    {
        $payload = json_encode([
            'ticket_id' => $ticketId, 'event_id' => $eventId,
            'issued_at' => $issuedAt ?? time(),
            'nonce' => Str::random(16),
        ]);
        $b64 = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        $sig = hash_hmac('sha256', $payload, $this->appKey);
        return "{$b64}.{$sig}";
    }

    /** @return array{ticket_id:int,event_id:int,issued_at:int,nonce:string} */
    public function verify(string $token): array
    {
        [$b64, $sig] = explode('.', $token, 2) + [null, null];
        if ($b64 === null || $sig === null) throw new InvalidArgumentException('Malformed token');
        $payload = base64_decode(strtr($b64, '-_', '+/'));
        if (! hash_equals(hash_hmac('sha256', $payload, $this->appKey), $sig)) {
            throw new InvalidArgumentException('Signature mismatch');
        }
        $data = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        return $data;
    }
}
```

Bind in service provider with `config('app.key')` as the constructor arg.

- [ ] **Step 2: Tests**

`tests/Unit/Ticketing/QrTokenSignerTest.php` — round-trip; signature mismatch throws; tampering caught.

- [ ] **Step 3: Commit**

```bash
git add src/Ticketing/Support/QrTokenSigner.php tests/Unit/Ticketing/QrTokenSignerTest.php
git commit -m "feat(events): add QrTokenSigner with HMAC signature"
```

---

## Task 15: Transfer engine + observer-driven counter denormalisation

**Files:** `src/Ticketing/Support/TransferEngine.php`, `src/Ticketing/Observers/{TicketObserver,OrderObserver}.php`.

- [ ] **Step 1: TransferEngine** — encapsulates spec §11.3 rules.

```php
final class TransferEngine
{
    public function attemptTransfer(Ticket $ticket, Model $newHolder): Ticket
    {
        if (! $ticket->ticketType->transferable) throw new TransferNotAllowed('Type not transferable');
        if (! $ticket->transferable()) throw new TransferNotAllowed('Deadline passed');

        $fee = $ticket->ticketType->transfer_fee_minor;
        if ($fee !== null && $fee > 0) {
            // Create fee Order in pending; do NOT swap holder yet.
            $feeOrder = $this->createFeeOrder($ticket, $newHolder, $fee, $ticket->ticketType->transfer_fee_currency);
            $ticket->forceFill(['transfer_fee_order_id' => $feeOrder->id])->save();
            TicketTransferRequested::dispatch($ticket, $newHolder);
            return $ticket;
        }

        // Free transfer: complete immediately.
        return $this->completeTransfer($ticket, $newHolder);
    }

    public function completeTransfer(Ticket $ticket, Model $newHolder): Ticket
    {
        $oldHolderId = $ticket->holder_id;
        $ticket->forceFill([
            'holder_id' => $newHolder->getKey(),
            'holder_name' => $newHolder->name ?? $newHolder->email,
            'holder_email' => $newHolder->email,
            'transferred_from' => $oldHolderId,
            'transferred_at' => now(),
        ])->save();

        TicketTransferred::dispatch($ticket, $oldHolderId, $newHolder);
        return $ticket;
    }

    private function createFeeOrder(Ticket $ticket, Model $newHolder, int $amountMinor, string $currency): Order
    {
        return Order::create([
            'event_id' => $ticket->event_id,
            'user_id' => $newHolder->getKey(),
            'status' => OrderStatus::Pending,
            'subtotal_minor' => $amountMinor, 'discount_minor' => 0, 'tax_minor' => 0, 'total_minor' => $amountMinor,
            'currency' => $currency,
            'metadata' => ['transfer_for_ticket_id' => $ticket->id],
        ]);
    }
}
```

- [ ] **Step 2: OrderObserver** — completes fee transfers, fires payout accrual.

```php
final class OrderObserver
{
    public function __construct(private readonly TransferEngine $transferEngine, private readonly PayoutAccruer $payoutAccruer) {}

    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status')) return;

        if ($order->status === OrderStatus::Paid) {
            // Check if this is a transfer fee order: complete the transfer.
            $ticketId = $order->metadata['transfer_for_ticket_id'] ?? null;
            if ($ticketId) {
                $ticket = Ticket::find($ticketId);
                if ($ticket) {
                    $newHolder = $order->buyer;
                    $this->transferEngine->completeTransfer($ticket, $newHolder);
                }
            }

            if (config('events.payouts.auto_accrue_on_order_paid')) {
                $this->payoutAccruer->accrueFor($order);
            }

            OrderPaid::dispatch($order);
        }
    }
}
```

- [ ] **Step 3: TicketObserver** — increments `tickets_sold_count` on event when created, decrements on cancel/refund. Also denormalises holder fields from `OrderItemAssignment` when one is linked.

- [ ] **Step 4: Tests**

Free transfer happy path; transfer with fee creates pending order; pending order paid → holder flips.

- [ ] **Step 5: Commit**

```bash
git add src/Ticketing/Support/TransferEngine.php src/Ticketing/Observers tests
git commit -m "feat(events): add TransferEngine + Order/Ticket observers"
```

---

## Task 16: PayoutAccruer + audit log writer + sponsor coordinator

**Files:** `src/Flow/Support/{PayoutAccruer,AuditLogWriter}.php`, `src/Flow/Support/SponsorCoordinator.php`.

- [ ] **Step 1: PayoutAccruer**

```php
final class PayoutAccruer
{
    public function accrueFor(Order $order): void
    {
        $organizers = EventOrganizer::query()->where('event_id', $order->event_id)->whereNotNull('commission_basis_points')->get();
        foreach ($organizers as $organizer) {
            $amount = intdiv($order->total_minor * $organizer->commission_basis_points, 10_000);
            PayoutLedgerEntry::create([
                'order_id' => $order->id,
                'organizer_user_id' => $organizer->user_id,
                'share_basis_points' => $organizer->commission_basis_points,
                'amount_minor' => $amount,
                'currency' => $order->currency,
                'status' => PayoutStatus::Accrued,
            ]);
        }
    }
}
```

- [ ] **Step 2: AuditLogWriter**

```php
final class AuditLogWriter
{
    public function write(
        string $action,
        ?Model $subject = null,
        ?Model $actor = null,
        ?int $eventId = null,
        ?array $changes = null,
    ): void {
        if (! config('events.audit.enabled')) return;

        AuditLogEntry::create([
            'event_id' => $eventId,
            'actor_id' => $actor?->getKey(),
            'actor_type' => $actor ? $this->classifyActor($actor) : 'system',
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'changes' => $changes,
            'context' => $this->captureContext(),
            'occurred_at' => now(),
        ]);
    }

    private function captureContext(): ?array
    {
        if (! config('events.audit.capture_context')) return null;
        $req = request();
        return ['ip' => $req?->ip(), 'user_agent' => (string) $req?->userAgent()];
    }

    private function classifyActor(Model $actor): string { /* … */ return 'user'; }
}
```

- [ ] **Step 3: SponsorCoordinator**

```php
final class SponsorCoordinator
{
    public function purchaseSponsorship(Event $event, SponsorTier $tier, Model $contactUser, array $data): Sponsor
    {
        return DB::transaction(function () use ($event, $tier, $contactUser, $data) {
            // B2B billing order for the sponsorship itself
            $order = Order::create([
                'event_id' => $event->id, 'user_id' => $contactUser->getKey(),
                'status' => OrderStatus::Pending,
                'subtotal_minor' => $tier->price_minor, 'discount_minor' => 0, 'tax_minor' => 0,
                'total_minor' => $tier->price_minor, 'currency' => $tier->currency,
                'metadata' => ['sponsorship_for_tier_id' => $tier->id],
            ]);
            $sponsor = Sponsor::create([
                'event_id' => $event->id, 'sponsor_tier_id' => $tier->id,
                'name' => $data['name'], 'contact_user_id' => $contactUser->getKey(),
                'logo_path' => $data['logo_path'] ?? null,
                'website_url' => $data['website_url'] ?? null,
                'blurb' => $data['blurb'] ?? null,
                'status' => SponsorStatus::Pending,
                'order_id' => $order->id,
            ]);
            return $sponsor;
        });
    }

    public function issueCompTicket(Sponsor $sponsor, Model $holder, array $assignmentData): Ticket
    {
        $tier = $sponsor->tier;
        $issuedCount = SponsorCompTicket::query()->where('sponsor_id', $sponsor->id)->count();
        if ($tier->comp_ticket_quota === 0 || $issuedCount >= $tier->comp_ticket_quota) {
            throw new RuntimeException('Comp quota exhausted');
        }
        if ($tier->comp_ticket_type_id === null) {
            throw new RuntimeException('Tier has no comp ticket type configured');
        }

        $ticket = Ticket::create([
            'order_item_id' => null, // comp tickets bypass order_items; flag via metadata
            'ticket_type_id' => $tier->comp_ticket_type_id,
            'event_id' => $sponsor->event_id,
            'holder_id' => $holder->getKey(),
            'holder_name' => $assignmentData['name'] ?? ($holder->name ?? 'Sponsor Comp'),
            'holder_email' => $assignmentData['email'] ?? ($holder->email ?? ''),
            'status' => TicketStatus::Issued,
            'qr_token' => app(QrTokenSigner::class)->sign(/* will be filled after persist */ 0, $sponsor->event_id),
            'metadata' => ['comp_for_sponsor_id' => $sponsor->id],
        ]);

        SponsorCompTicket::create([
            'sponsor_id' => $sponsor->id,
            'ticket_id' => $ticket->id,
            'issued_at' => now(),
        ]);

        TicketIssued::dispatch($ticket);
        return $ticket;
    }
}
```

The `OrderObserver` (Task 15) detects sponsorship orders by `metadata.sponsorship_for_tier_id` and flips `Sponsor.status` to `Active` when the order is paid.

- [ ] **Step 4: Tests**

PayoutAccruer: paid order with 2 organizers (60/40 split) → 2 ledger rows summing to order total.
AuditLogWriter: writes row with captured ip; honours `audit.enabled=false`.
SponsorCoordinator: purchase creates pending order + pending sponsor; paying flips both to Paid + Active; comp-ticket issuance respects quota.

- [ ] **Step 5: Commit**

```bash
git add src/Flow/Support/PayoutAccruer.php src/Flow/Support/AuditLogWriter.php src/Flow/Support/SponsorCoordinator.php tests
git commit -m "feat(events): add PayoutAccruer + AuditLogWriter + SponsorCoordinator"
```

---

## Task 17: Sale queue mechanics

**Files:** `src/Flow/Support/{QueueReleaser,QueuePruner}.php`, `src/Flow/Events/{QueueJoined,QueueReleased,QueueExpired}.php`, `src/Flow/Exceptions/QueueChallengeFailed.php`.

- [ ] **Step 1: QueueReleaser**

```php
final class QueueReleaser
{
    public function releaseFor(Event $event): int
    {
        $concurrency = (int) config('events.queue.active_concurrency');
        $windowSeconds = (int) config('events.queue.active_window_seconds');

        return DB::transaction(function () use ($event, $concurrency, $windowSeconds) {
            $activeCount = SaleQueueEntry::query()->where('event_id', $event->id)
                ->where('status', QueueStatus::Active->value)->lockForUpdate()->count();
            $promote = max(0, $concurrency - $activeCount);
            if ($promote === 0) return 0;

            $waiting = SaleQueueEntry::query()->where('event_id', $event->id)
                ->where('status', QueueStatus::Waiting->value)
                ->orderBy('position')->limit($promote)->lockForUpdate()->get();

            foreach ($waiting as $entry) {
                $entry->forceFill([
                    'status' => QueueStatus::Active,
                    'released_at' => now(),
                    'expires_at' => now()->addSeconds($windowSeconds),
                ])->save();
                QueueReleased::dispatch($entry);
            }
            return $waiting->count();
        });
    }
}
```

- [ ] **Step 2: QueuePruner**

```php
final class QueuePruner
{
    public function pruneFor(Event $event): int
    {
        $cutoff = now()->subSeconds((int) config('events.queue.heartbeat_timeout_seconds'));
        return SaleQueueEntry::query()->where('event_id', $event->id)
            ->where('status', QueueStatus::Waiting->value)
            ->where('last_heartbeat_at', '<', $cutoff)
            ->update(['status' => QueueStatus::Abandoned]);
    }
}
```

- [ ] **Step 3: Anti-bot challenge check**

When `config('events.anti_bot.queue_challenge')` is set, `Events::joinQueue` calls the provider's `verify()`. Failed verify throws `QueueChallengeFailed`.

- [ ] **Step 4: Tests**

Sequential release preserves position order; abandoned heartbeats marked; expired actives free up next promotion.

- [ ] **Step 5: Commit**

```bash
git add src/Flow/Support/{QueueReleaser,QueuePruner}.php src/Flow/Events src/Flow/Exceptions tests
git commit -m "feat(events): add sale queue release + prune mechanics"
```

---

## Task 18: Waitlist + refund coordinator

**Files:** `src/Flow/Support/{WaitlistPromoter,RefundCoordinator}.php`.

- [ ] **Step 1: WaitlistPromoter**

When a ticket cancels: find next `WaitlistEntry`, set `status=offered + offered_at + claim_expires_at`, dispatch `WaitlistPromoted`.

```php
final class WaitlistPromoter
{
    public function promoteNextFor(TicketType $type): ?WaitlistEntry
    {
        $next = WaitlistEntry::query()->where('ticket_type_id', $type->id)
            ->where('status', WaitlistStatus::Waiting->value)
            ->orderBy('created_at')->lockForUpdate()->first();
        if (! $next) return null;
        $next->forceFill([
            'status' => WaitlistStatus::Offered,
            'offered_at' => now(),
            'claim_expires_at' => now()->addSeconds((int) config('events.waitlist.claim_window_seconds')),
        ])->save();
        WaitlistPromoted::dispatch($next);
        return $next;
    }
}
```

- [ ] **Step 2: RefundCoordinator (with EU consumer-protection window + buyer self-cancel)**

```php
final class RefundCoordinator
{
    public function request(Order|Ticket $target, Model $requester, RefundReason $reason, ?string $note = null, ?int $amountMinor = null): Refund
    {
        $order = $target instanceof Ticket ? $target->orderItem->order : $target;
        $amount = $amountMinor ?? ($target instanceof Ticket ? $target->orderItem->unit_price_minor : $order->total_minor);
        $refund = Refund::create([
            'order_id' => $order->id,
            'ticket_id' => $target instanceof Ticket ? $target->id : null,
            'amount_minor' => $amount,
            'currency' => $order->currency,
            'reason' => $reason,
            'reason_note' => $note,
            'status' => RefundStatus::Pending,
            'requested_by' => $requester->getKey(),
        ]);
        RefundRequested::dispatch($refund);
        return $refund;
    }

    public function markProcessed(Refund $refund, string $processorReference): void
    {
        $refund->forceFill([
            'status' => RefundStatus::Processed, 'processor_reference' => $processorReference, 'processed_at' => now(),
        ])->save();
        $refund->order->recomputeTotalsAfterRefund();
        RefundProcessed::dispatch($refund);
    }

    /**
     * Buyer self-cancellation per spec §16.4.
     * Honours EU consumer-protection window + per-type self-cancel deadline.
     */
    public function cancelOrderByBuyer(Order $order, Model $buyer): Refund
    {
        if ($order->user_id !== $buyer->getKey()) {
            throw new RuntimeException('Not the order buyer');
        }
        if ($order->status !== OrderStatus::Paid) {
            throw new RuntimeException('Only paid orders can be self-cancelled');
        }
        if ($order->tickets->some(fn ($t) => $t->status === TicketStatus::CheckedIn)) {
            throw new RuntimeException('Cannot self-cancel after check-in');
        }

        $allowedByEu = $this->isInConsumerProtectionWindow($order);
        $allowedBySelfCancelDeadline = $order->items->every(function (OrderItem $item) {
            $hours = $item->ticketType->self_cancel_deadline_hours_before_event;
            if ($hours === null) return false;
            $cutoff = $item->order->event->starts_at->copy()->subHours($hours);
            return now()->lt($cutoff);
        });

        if (! $allowedByEu && ! $allowedBySelfCancelDeadline) {
            throw new SelfCancellationNotPermitted();
        }

        $refund = $this->request($order, $buyer, RefundReason::AttendeeRequest, 'Buyer self-cancellation');
        $refund->forceFill(['metadata' => ['consumer_protection_eligible' => $allowedByEu]])->save();
        return $refund;
    }

    private function isInConsumerProtectionWindow(Order $order): bool
    {
        $windowDays = (int) config('events.refunds.consumer_protection_window_days');
        if ($windowDays === 0) return false;
        if (! $order->paid_at || $order->paid_at->lt(now()->subDays($windowDays))) return false;
        // any consumer_protection_exempt ticket disqualifies the entire order
        return $order->items->every(fn ($i) => ! $i->ticketType->consumer_protection_exempt);
    }
}
```

Add typed exception `src/Flow/Exceptions/SelfCancellationNotPermitted.php`:
```php
namespace Kurt\Modules\Events\Flow\Exceptions;
final class SelfCancellationNotPermitted extends \RuntimeException {}
```

- [ ] **Step 3: Tests**

Waitlist sequential promotion; refund pending → processed → order recompute → status.
EU consumer-protection window: paid 5 days ago + non-exempt + uncheckedin → `cancelOrderByBuyer` returns Refund with `consumer_protection_eligible=true`. Paid 20 days ago → throws `SelfCancellationNotPermitted`. Per-type deadline passes → throws. Exempt ticket type → throws.

- [ ] **Step 4: Commit**

```bash
git add src/Flow/Support tests
git commit -m "feat(events): add WaitlistPromoter + RefundCoordinator"
```

---

## Task 19: AnnouncementDispatcher + reminder scheduling

**Files:** `src/Attendance/Support/AnnouncementDispatcher.php`, `src/Notifications/{EventAnnouncementPosted,EventReminderDue,TicketIssued,…}.php`.

- [ ] **Step 1: AnnouncementDispatcher**

```php
final class AnnouncementDispatcher
{
    public function dispatch(Announcement $announcement): int
    {
        $attendees = $this->audienceFor($announcement);
        $count = 0;
        foreach ($attendees as $attendee) {
            $recipient = AnnouncementRecipient::firstOrCreate(
                ['announcement_id' => $announcement->id, 'attendee_id' => $attendee->id],
                ['status' => AnnouncementRecipientStatus::Pending],
            );
            // Send via Laravel Notification when enabled
            if (config('events.notifications.enabled')) {
                $attendee->user?->notify(new EventAnnouncementPosted($announcement, $recipient));
            }
            $recipient->forceFill(['status' => AnnouncementRecipientStatus::Sent, 'sent_at' => now()])->save();
            $count++;
        }
        $announcement->forceFill(['sent_at' => now(), 'recipient_count' => $count])->save();
        AnnouncementSent::dispatch($announcement);
        return $count;
    }

    private function audienceFor(Announcement $a): Collection
    {
        $query = Attendee::query()->where('event_id', $a->event_id);
        return match ($a->audience) {
            AnnouncementAudience::All => $query->get(),
            AnnouncementAudience::Registered => $query->where('status', AttendeeStatus::Registered->value)->get(),
            AnnouncementAudience::CheckedIn => $query->where('status', AttendeeStatus::CheckedIn->value)->get(),
            AnnouncementAudience::ByTicketType => $query->whereHas('ticket', fn ($q) => $q->whereIn('ticket_type_id', $a->audience_filter['ticket_type_ids'] ?? []))->get(),
            AnnouncementAudience::BySession => $query->whereHas('checkIns', fn ($q) => $q->whereIn('session_id', $a->audience_filter['session_ids'] ?? []))->get(),
        };
    }
}
```

- [ ] **Step 2: Notification classes**

`EventAnnouncementPosted`, `TicketIssued`, `TicketTransferred`, `EventReminderDue`, `SessionReminderDue`, `ApplicationApproved`, `ApplicationRejected`, `OrderPaid`, `RefundProcessed`, `WaitlistOffer`.

Default blade templates under `resources/views/notifications/*.blade.php` (5–8 lines each, plain text). Reuse `MailMessage`.

- [ ] **Step 3: Tests**

Mail::fake; assertSentTo expected attendees; recipient count denormalised.

- [ ] **Step 4: Commit**

```bash
git add src/Attendance/Support src/Notifications resources/views/notifications tests
git commit -m "feat(events): add AnnouncementDispatcher + default Notification classes"
```

---

## Task 20: EventCloner + IcsExporter + templates + GDPR helpers

**Files:** `src/Catalog/Support/{EventCloner,IcsExporter,RecurrenceExpander,TemplateManager}.php`, `src/Flow/Support/{GdprExporter,GdprAnonymizer}.php`.

- [ ] **Step 1: EventCloner**

```php
final class EventCloner
{
    public function clone(Event $source, array $overrides = []): Event
    {
        return DB::transaction(function () use ($source, $overrides) {
            $new = $source->replicate(['slug','tickets_sold_count','attendees_count','applications_pending_count'])
                ->fill($overrides);
            $new->status = EventStatus::Draft;
            $new->save();
            foreach ($source->sessions as $session) {
                $new->sessions()->create($session->replicate()->toArray());
            }
            foreach ($source->ticketTypes as $type) {
                $cloneType = $new->ticketTypes()->create($type->replicate()->toArray());
                foreach ($type->priceTiers as $tier) {
                    $cloneType->priceTiers()->create($tier->replicate()->toArray());
                }
            }
            // similar for sponsor tiers, add-ons, requirements, attendance forms
            EventClonedFrom::dispatch($source, $new);
            return $new;
        });
    }
}
```

- [ ] **Step 2: IcsExporter**

`src/Catalog/Support/IcsExporter.php`:
```php
final class IcsExporter
{
    public function forEvent(Event $event): string
    {
        $uid = "event-{$event->id}@kurtmodules-events";
        $now = now()->utc()->format('Ymd\THis\Z');
        $start = $event->starts_at->copy()->utc()->format('Ymd\THis\Z');
        $end = $event->ends_at->copy()->utc()->format('Ymd\THis\Z');
        $title = $event->getTranslation('title', app()->getLocale());
        $description = strip_tags((string) $event->getTranslation('description', app()->getLocale(), false));
        $location = $event->location_name ?? $event->location_address ?? '';

        $rrule = '';
        if (! empty($event->recurrence_rule)) {
            $rrule = "RRULE:" . $this->buildRrule($event->recurrence_rule) . "\r\n";
        }

        return implode("\r\n", [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//KurtModules//Events//EN', 'CALSCALE:GREGORIAN',
            'BEGIN:VEVENT',
            "UID:{$uid}", "DTSTAMP:{$now}", "DTSTART:{$start}", "DTEND:{$end}",
            "SUMMARY:" . $this->escape($title),
            "DESCRIPTION:" . $this->escape($description),
            "LOCATION:" . $this->escape($location),
            rtrim($rrule, "\r\n"),
            'END:VEVENT', 'END:VCALENDAR',
        ]);
    }

    private function buildRrule(array $rule): string
    {
        $parts = ['FREQ=' . strtoupper((string) ($rule['frequency'] ?? 'WEEKLY'))];
        if (isset($rule['interval'])) $parts[] = 'INTERVAL=' . $rule['interval'];
        if (isset($rule['count'])) $parts[] = 'COUNT=' . $rule['count'];
        if (isset($rule['until'])) $parts[] = 'UNTIL=' . \Carbon\Carbon::parse($rule['until'])->utc()->format('Ymd\THis\Z');
        if (isset($rule['byDay'])) $parts[] = 'BYDAY=' . implode(',', $rule['byDay']);
        if (isset($rule['byMonthDay'])) $parts[] = 'BYMONTHDAY=' . implode(',', $rule['byMonthDay']);
        return implode(';', $parts);
    }

    private function escape(string $value): string
    {
        return str_replace(["\\", "\n", ",", ";"], ["\\\\", "\\n", "\\,", "\\;"], $value);
    }
}
```

`Event::ics()` returns `response($exporter->forEvent($this), 200, ['Content-Type' => 'text/calendar'])`.

Test: ICS string includes `BEGIN:VEVENT` + correct DTSTART; recurring event includes `RRULE:FREQ=WEEKLY;BYDAY=MO,WE`.

- [ ] **Step 3: RecurrenceExpander**

Materialises occurrence Event rows from `recurrence_rule` for the next `events.recurrence.window_days`. Called by `events:generate-occurrences` command.

- [ ] **Step 4: TemplateManager**

```php
final class TemplateManager
{
    public function __construct(private readonly EventCloner $cloner) {}

    public function saveAs(Event $source, Model $owner, string $name, ?string $slug = null, bool $public = false): EventTemplate
    {
        return EventTemplate::create([
            'owner_id' => $owner->getKey(),
            'slug' => $slug ?? str($name)->slug(),
            'name' => $name,
            'is_public' => $public,
            'payload' => $this->snapshot($source),
        ]);
    }

    public function spawn(EventTemplate $template, Model $organizer, array $overrides = []): Event
    {
        return DB::transaction(function () use ($template, $organizer, $overrides) {
            $event = Event::create(array_merge($template->payload['event'], $overrides, [
                'status' => config('events.publishing.require_approval') ? EventStatus::PendingApproval : EventStatus::Draft,
            ]));
            $event->organizers()->create([
                'user_id' => $organizer->getKey(), 'role' => OrganizerRole::Owner,
            ]);
            foreach ($template->payload['sessions'] ?? [] as $session) {
                $event->sessions()->create($session);
            }
            foreach ($template->payload['ticket_types'] ?? [] as $type) {
                $event->ticketTypes()->create($type);
            }
            $template->increment('used_count');
            EventCreatedFromTemplate::dispatch($event, $template);
            return $event;
        });
    }

    private function snapshot(Event $event): array
    {
        return [
            'event' => $event->only(['title','description','category_id','timezone','attendee_list_visibility']),
            'sessions' => $event->sessions->map->only(['slug','title','description','starts_at','ends_at','capacity','position'])->all(),
            'ticket_types' => $event->ticketTypes->map->only([/* all type columns */])->all(),
            // sponsor_tiers, requirements, add-ons, attendance_forms similarly
        ];
    }
}
```

- [ ] **Step 5: GdprExporter**

Walks tables; returns structured array per spec §25.5.

- [ ] **Step 6: GdprAnonymizer**

Replaces PII columns with hashes or nulls. Honours `events.gdpr.anonymize_audit_log_actor`.

- [ ] **Step 7: Tests**

Cloned event has independent ticket types + price tiers; no sales data carried; ICS string parses (BEGIN:VEVENT/DTSTART/SUMMARY present); RRULE built from rule JSON; template snapshot+spawn roundtrip; exporter dump contains expected sections; anonymizer replaces holder_name + holder_email + profile.

- [ ] **Step 8: Commit**

```bash
git add src/Catalog/Support src/Flow/Support/GdprExporter.php src/Flow/Support/GdprAnonymizer.php tests
git commit -m "feat(events): add EventCloner + IcsExporter + RecurrenceExpander + TemplateManager + GDPR helpers"
```

---

## Task 21: Domain events

**Files:** under `src/Catalog/Events/`, `src/Ticketing/Events/`, `src/Attendance/Events/`, `src/Eligibility/Events/`, `src/Flow/Events/`.

- [ ] **Step 1: Write all event classes**

Each uses `Illuminate\Foundation\Events\Dispatchable`. One file per event listed in spec §14. Single-arg events have one readonly promoted property; multi-arg as needed.

Examples already shown in earlier tasks (`OrderPaid`, `RefundProcessed`, `QueueReleased`, `WaitlistPromoted`, `EventClonedFrom`, `TicketTransferRequested`, `TicketTransferred`, `AnnouncementSent`, etc.).

- [ ] **Step 2: Commit**

```bash
git add src/*/Events
git commit -m "feat(events): add domain events"
```

---

## Task 22: Policies

**Files:** `src/Policies/{EventPolicy,TicketTypePolicy,OrderPolicy,ApplicationPolicy,RefundPolicy,QueuePolicy,WaitlistPolicy,EventApprovalPolicy}.php`.

Implement gates from spec §18. `canManageEvents` and `canManageEventApprovals` gates check via `app('gate')->allows(...)`.

- [ ] **Step 1: EventPolicy**

```php
public function view(?Authenticatable $user, Event $event): bool { /* visibility + organizer/staff */ }
public function update(Authenticatable $user, Event $event): bool { /* organizer with manager+ role OR staff */ }
public function delete(...): bool { /* same */ }
public function approveForPublication(Authenticatable $user, Event $event): bool
{
    return app('gate')->allows('canManageEventApprovals', $user);
}
```

- [ ] **Step 2–7: Remaining policies**

Each follows spec §18.

- [ ] **Step 8: Commit**

```bash
git add src/Policies tests
git commit -m "feat(events): add policies"
```

---

## Task 23: Console commands

**Files:** `src/Console/Commands/{ReleaseQueueCommand,PruneQueueCommand,ExpireWaitlistClaimsCommand,GenerateOccurrencesCommand,DispatchRemindersCommand,ExpirePendingOrdersCommand,DispatchAnnouncementsCommand,EnforceRetentionCommand,DemoCommand}.php`.

- [ ] **Step 1: ExpirePendingOrdersCommand**

```php
protected $signature = 'events:expire-pending-orders';
public function handle(): int
{
    $cutoff = now()->subMinutes((int) config('events.orders.pending_timeout_minutes'));
    $count = Order::query()->where('status', OrderStatus::Pending->value)
        ->where('created_at', '<', $cutoff)
        ->get()->each(function (Order $order) {
            DB::transaction(function () use ($order) {
                foreach ($order->items as $item) {
                    TicketType::query()->where('id', $item->ticket_type_id)
                        ->lockForUpdate()->decrement('sold_count', $item->quantity);
                }
                $order->forceFill(['status' => OrderStatus::Cancelled])->save();
                $order->items()->each(fn ($i) => $i->assignments()->delete());
                OrderCancelled::dispatch($order, 'cart_timeout');
            });
        })->count();
    $this->info("Cancelled {$count} pending order(s).");
    return self::SUCCESS;
}
```

- [ ] **Step 2–9: Remaining commands**

Each mirrors spec §20. Implementation follows the support classes built earlier.

- [ ] **Step 10: Commit**

```bash
git add src/Console tests
git commit -m "feat(events): add console commands"
```

---

## Task 24: Top-level Events facade

**Files:** `src/Support/Events.php`, `src/Facades/Events.php` (thin facade).

- [ ] **Step 1: Facade-class implementation**

Implements every method from spec §15. Bound as singleton in provider.

Each method runs inside `DB::transaction(...)` where state changes. Uses pessimistic lock on `ticket_types` row inside `reserve(...)` per spec §16.3.

```php
public function reserve(TicketType $type, Model $buyer, int $quantity, array $holderAssignments, ?string $discountCode = null, ?int $unitPriceMinorOverride = null): Order
{
    return DB::transaction(function () use ($type, $buyer, $quantity, $holderAssignments, $discountCode, $unitPriceMinorOverride) {
        $type = TicketType::query()->lockForUpdate()->findOrFail($type->id);
        if (count($holderAssignments) !== $quantity) throw new InvalidArgumentException('Assignment count mismatch');
        if ($type->capacity !== null && ($type->capacity - $type->sold_count) < $quantity) {
            throw new TicketTypeSoldOut();
        }
        $unitPrice = $unitPriceMinorOverride ?? $type->currentUnitPriceMinor();
        if ($type->minimum_price_minor !== null && $unitPrice < $type->minimum_price_minor) {
            throw new InvalidArgumentException('Below minimum price');
        }
        $subtotal = $unitPrice * $quantity;

        // Apply discount via PriceCalculator if code supplied
        $draft = /* construct DraftOrder */;
        $breakdown = $this->prices->apply($draft, $discountCode ? DiscountCode::query()->where('code', $discountCode)->first() : null);

        $order = Order::create([
            'event_id' => $type->event_id, 'user_id' => $buyer->getKey(),
            'status' => OrderStatus::Pending,
            'subtotal_minor' => $breakdown->subtotalMinor,
            'discount_minor' => $breakdown->discountMinor,
            'tax_minor' => 0, 'total_minor' => $breakdown->totalMinor,
            'currency' => $breakdown->currency,
        ]);
        $orderItem = $order->items()->create([
            'ticket_type_id' => $type->id,
            'price_tier_id' => $type->activePriceTier()?->id,
            'quantity' => $quantity,
            'unit_price_minor' => $unitPrice,
            'line_total_minor' => $unitPrice * $quantity,
        ]);
        foreach ($holderAssignments as $i => $assignment) {
            $orderItem->assignments()->create([
                'seat_index' => $i,
                'holder_user_id' => $assignment['user_id'] ?? null,
                'holder_name' => $assignment['name'],
                'holder_email' => $assignment['email'],
                'holder_metadata' => $assignment['metadata'] ?? null,
            ]);
        }
        $type->increment('sold_count', $quantity);
        Event::query()->where('id', $type->event_id)->increment('tickets_sold_count', $quantity);

        OrderCreated::dispatch($order);
        return $order;
    });
}
```

- [ ] **Step 2: pay()** flips status to Paid, triggers `OrderObserver` which issues tickets + accrues payouts + completes transfers.

- [ ] **Step 3: All remaining methods**

Implement per spec §15. Each emits the matching domain event.

- [ ] **Step 4: Tests + commit**

```bash
git add src/Support/Events.php src/Facades tests
git commit -m "feat(events): add top-level Events facade"
```

---

## Task 25: EventsServiceProvider

**Files:** `src/Providers/EventsServiceProvider.php`.

- [ ] **Step 1: Provider**

```php
final class EventsServiceProvider extends PackageServiceProvider
{
    protected function module(): string { return 'events'; }

    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-modules-events')
            ->hasConfigFile('events')
            ->hasTranslations()
            ->hasMigrations([/* list every migration filename */])
            ->hasCommands([
                ReleaseQueueCommand::class, PruneQueueCommand::class,
                ExpireWaitlistClaimsCommand::class, GenerateOccurrencesCommand::class,
                DispatchRemindersCommand::class, ExpirePendingOrdersCommand::class,
                DispatchAnnouncementsCommand::class, EnforceRetentionCommand::class,
                DemoCommand::class,
            ])
            ->hasViews('events');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(Events::class, fn () => new Events(/* inject deps */));
        $this->app->singleton(QrTokenSigner::class, fn () => new QrTokenSigner(config('app.key')));
        $this->app->singleton(RequirementEngine::class);
        $this->app->scoped(QueueReleaser::class);
        $this->app->scoped(WaitlistPromoter::class);
        $this->app->scoped(RefundCoordinator::class);
        $this->app->scoped(PayoutAccruer::class);
        $this->app->scoped(AuditLogWriter::class);
        $this->app->scoped(EventCloner::class);
    }

    public function packageBooted(): void
    {
        Event::observe(EventObserver::class);
        Order::observe(OrderObserver::class);
        Ticket::observe(TicketObserver::class);
        Attendee::observe(AttendeeObserver::class);
        Application::observe(ApplicationObserver::class);

        $gate = $this->app['Illuminate\Contracts\Auth\Access\Gate'];
        $gate->policy(Event::class, EventPolicy::class);
        $gate->policy(TicketType::class, TicketTypePolicy::class);
        $gate->policy(Order::class, OrderPolicy::class);
        $gate->policy(Application::class, ApplicationPolicy::class);
        $gate->policy(Refund::class, RefundPolicy::class);

        // Schedule commands per spec §20
        if ($this->app->runningInConsole()) {
            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);
                $schedule->command(ReleaseQueueCommand::class)->everyTenSeconds();
                $schedule->command(PruneQueueCommand::class)->everyMinute();
                $schedule->command(ExpireWaitlistClaimsCommand::class)->everyMinute();
                $schedule->command(ExpirePendingOrdersCommand::class)->everyMinute();
                $schedule->command(DispatchAnnouncementsCommand::class)->everyMinute();
                $schedule->command(DispatchRemindersCommand::class)->everyFiveMinutes();
                $schedule->command(GenerateOccurrencesCommand::class)->daily();
                $schedule->command(EnforceRetentionCommand::class)->daily();
            });
        }
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Providers
git commit -m "feat(events): add EventsServiceProvider"
```

---

## Task 26: Test base + feature tests

**Files:** `tests/{Pest,TestCase}.php`, `tests/Stubs/StubUser.php`, `tests/migrations/2026_05_28_000010_create_media_table.php`, plus per-feature test files.

- [ ] **Step 1: TestCase**

```php
abstract class TestCase extends PackageTestCase
{
    protected function modulePackageProviders($app): array
    {
        return [
            MediaLibraryServiceProvider::class,
            Cviebrock\EloquentSluggable\ServiceProvider::class,
            EventsServiceProvider::class,
        ];
    }
    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();
        $this->loadMigrationsFrom(__DIR__.'/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
```

- [ ] **Step 2: Feature tests per spec §23**

Each test file is small (one scenario per `it()` block). Test file names:
- `tests/Feature/Catalog/EventLifecycleTest.php`
- `tests/Feature/Catalog/EventCloningTest.php`
- `tests/Feature/Catalog/EventTemplateTest.php`
- `tests/Feature/Ticketing/PurchaseHappyPathTest.php` — full reserve→pay→ticket-issued with group assignments
- `tests/Feature/Ticketing/PriceTiersTest.php`
- `tests/Feature/Ticketing/TransferTest.php` — free + with-fee + deadline rejected
- `tests/Feature/Ticketing/DiscountCodeTest.php` — all 6 sub-cases
- `tests/Feature/Ticketing/AddOnTest.php`
- `tests/Feature/Ticketing/ReferralAttributionTest.php`
- `tests/Feature/Attendance/ApplicationFlowTest.php`
- `tests/Feature/Attendance/MultiSessionTest.php`
- `tests/Feature/Attendance/AnnouncementTest.php`
- `tests/Feature/Attendance/AttendeeListVisibilityTest.php`
- `tests/Feature/Eligibility/RequirementMatrixTest.php`
- `tests/Feature/Eligibility/DocumentVerificationTest.php`
- `tests/Feature/Flow/QueueReleaseTest.php`
- `tests/Feature/Flow/WaitlistTest.php`
- `tests/Feature/Flow/RefundFlowTest.php`
- `tests/Feature/Flow/EuRefundWindowTest.php`
- `tests/Feature/Flow/PayoutsTest.php`
- `tests/Feature/Sponsors/SponsorshipTest.php`
- `tests/Feature/Audit/AuditLogTest.php`
- `tests/Feature/Gdpr/PersonalDataExportTest.php`
- `tests/Feature/Gdpr/AnonymizeTest.php`
- `tests/Feature/Console/ExpirePendingOrdersTest.php`
- `tests/Feature/Console/EnforceRetentionTest.php`

Each test file has 3–8 tests. Target ~80 total tests.

- [ ] **Step 3: Commit progressively (one commit per sub-aggregate's tests)**

```bash
git add tests/Feature/Catalog && git commit -m "test(events): add Catalog feature tests"
git add tests/Feature/Ticketing && git commit -m "test(events): add Ticketing feature tests"
# … continue for each
```

---

## Task 27: CI + docs

**Files:** `.github/workflows/tests.yml`, `.github/dependabot.yml`, `README.md`, `CHANGELOG.md`, `UPGRADE-1.0.md`, `docs/gdpr/{data-flow.md,processing-record.md,dsr-checklist.md}`.

- [ ] **Step 1: Copy CI from Chat**

```bash
cp ../KurtModules-Chat/.github/workflows/tests.yml .github/workflows/tests.yml
cp ../KurtModules-Chat/.github/dependabot.yml .github/dependabot.yml
```

- [ ] **Step 2: README + CHANGELOG + UPGRADE**

Standard format, describing what's included + Filament v1.1 promise.

- [ ] **Step 3: GDPR docs**

Three markdown files per spec §25.5.

- [ ] **Step 4: Commit**

```bash
git add .github README.md CHANGELOG.md UPGRADE-1.0.md docs
git commit -m "ci+docs: add github actions, README, CHANGELOG, UPGRADE-1.0, GDPR docs"
```

---

## Task 28: Push + PR + tag

- [ ] **Step 1: Push**

```bash
git push -u origin v1.0
```

- [ ] **Step 2: Open PR**

```bash
gh pr create --title "v1.0: initial release of ozankurt/laravel-modules-events" --body "$(cat <<'EOF'
## Summary

- Greenfield payment-agnostic event management module.
- 32 tables across 5 sub-aggregates (Catalog, Ticketing, Attendance, Eligibility, Flow).
- Open/Application/RSVP registration; group ticket assignment; multi-session events.
- Sale queue + waitlist; full Refund model with EU consumer-protection window.
- Discount codes (percent + flat amount), price tiers (early-bird), add-ons (parking/dinner/merch).
- Sponsors with tiers + comp tickets; referral attribution + lightweight co-organizer payouts.
- Bulk announcements; audit log; event templates + cloning.
- GDPR helpers: export, anonymize, retention command, DPIA docs.
- Optional Laravel Notifications (Mail + Database) + default Blade templates.
- Filament admin lands in v1.1.

## Test plan
- [x] vendor/bin/pint --test
- [x] vendor/bin/phpstan analyse
- [x] vendor/bin/pest (~80 tests)
- [ ] CI matrix green on this PR
EOF
)"
```

- [ ] **Step 3: Wait for CI green, merge, tag**

```bash
gh pr checks <number> --watch
gh pr merge <number> --merge
git switch master && git pull
git tag -a v1.0.0 -m "v1.0.0"
git push origin v1.0.0
gh release create v1.0.0 --title "v1.0.0" --notes-file CHANGELOG.md
```

---

## Definition of done

- [ ] All 40 migrations applied; smoke test confirms every table present.
- [ ] PriceCalculator covers all 6 discount-code edge cases.
- [ ] RequirementEngine + every default evaluator tested.
- [ ] QueueReleaser, WaitlistPromoter, RefundCoordinator, PayoutAccruer, AuditLogWriter all tested.
- [ ] TransferEngine free + with-fee paths work.
- [ ] GDPR exporter + anonymizer behave per spec.
- [ ] EU consumer-protection refund auto-approves inside window + non-exempt + uncheckedin.
- [ ] Pint + PHPStan level 8 + Pest all green (~80 tests).
- [ ] CI matrix green on Laravel 12.
- [ ] README + CHANGELOG + UPGRADE-1.0 + GDPR docs in place.
- [ ] Tag `v1.0.0` cut + GitHub release published.
- [ ] PR notes that Filament admin resources are deferred to v1.1.
