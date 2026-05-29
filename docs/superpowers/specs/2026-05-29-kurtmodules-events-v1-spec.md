# `ozankurt/laravel-modules-events` v1.0 — Spec

**Repo:** `KurtModules-Events` (to be created)
**Date:** 2026-05-29
**Status:** Draft → user review pending
**Family:** [KurtModules v2 umbrella](./2026-05-28-kurtmodules-v2-design.md)

---

## 1. Purpose

A **flexible event management** module for Laravel SaaS apps. Covers create/publish/cancel events, sell or RSVP tickets, applications with organizer approval, document/age/group-membership requirements, capacity + waitlist + queue (waiting-room) for hot sales, discount codes, refund tracking, recurring events with ICS export, and a Filament admin (planned v1.1).

The module is **payment-agnostic** — it tracks Order/Ticket/Refund states and emits domain events. Consumers wire their payment gateway (Stripe/Paddle/manual) by responding to those events.

The module is **transport-agnostic for real-time** — the sale queue + waitlist promotion work over polling (HTTP) or broadcasting (Reverb/Pusher). It ships both: a heartbeat endpoint pattern + broadcast events (`QueueReleased`, `WaitlistPromoted`).

This is a **greenfield module**, initial release `v1.0.0` (not v2 — the family-wide v2 line is the renovation of the existing five; Events is new and starts at 1.0).

## 2. Modules-in-family relationship

Sibling to Blog/Chat/Forum/Library/Core. Same conventions per the [umbrella v2 design](./2026-05-28-kurtmodules-v2-design.md). Depends on Core only. No coupling to Blog/Chat/Forum/Library.

## 3. Stack & metadata

| | |
|---|---|
| Composer name | `ozankurt/laravel-modules-events` |
| PHP namespace | `Kurt\Modules\Events\` |
| Table prefix | `events_` |
| PHP | `^8.4` |
| Laravel | `^12.0 \|\| ^13.0` |
| Core | `ozankurt/laravel-modules-core: ^2.0` |
| Media | `spatie/laravel-medialibrary: ^11.0` (documents, event covers) |
| Translatable | `spatie/laravel-translatable: ^6.11` (event title/description/ticket-type name etc.) |
| Sluggable | `cviebrock/eloquent-sluggable: ^11.0 \|\| ^12.0` |
| Filament | `^3.0 \|\| ^4.0 \|\| ^5.0` (require-dev; resources land v1.1) |
| Tests | Pest 3 + Testbench + PHPStan 8 + Pint, GH Actions CI |

## 4. Architecture: 5 sub-aggregates

Code is organised by sub-aggregate, not by technical layer. Each sub-aggregate has its own `Models/`, `Enums/`, `Support/` (where needed).

```
src/
  Catalog/                # Event + Session + recurrence + organizers + taxonomy
  Ticketing/              # Ticket types, orders, order-item assignments, tickets, transfers, discount codes
  Attendance/             # Attendees, applications, custom forms, session check-ins
  Eligibility/            # Requirements engine + document verification
  Flow/                   # Queue + waitlist + refunds + reminders

  Concerns/               # IsEventOrganizer, IsEventAttendee user traits
  Contracts/              # EventOrganizer, EventAttendee, DocumentVerifier, RequirementEvaluator
  Events/                 # Domain events (Laravel events)
  Notifications/          # Optional Laravel Notification classes (Mail+Database)
  Policies/
  Providers/EventsServiceProvider.php
  Support/Events.php      # Top-level facade-style entry point
```

## 5. Data model

All tables use `bigIncrements`, `softDeletes()`, `timestamps()`. Money columns are `unsignedBigInteger` storing **minor units** (cents) — `100` = `1.00 USD`. Currency is a 3-char ISO 4217 code stored as `string`. JSON for translatable text + flexible payloads.

### 5.1 Catalog

```
events_categories                    -- optional grouping
  id, slug, name (json — translatable), description (json — translatable, nullable),
  parent_id (nullable, self FK, restrictOnDelete), position (unsigned int),
  created_at, updated_at, deleted_at

events_tags
  id, slug, name (json — translatable),
  created_at, updated_at

events_sessions                      -- multi-day / multi-session events; ticket types gate by session subset
  id, event_id (FK events_events, cascadeOnDelete),
  slug, title (json — translatable), description (json — translatable, nullable),
  starts_at, ends_at (timestamps),
  capacity (unsignedInteger nullable),                   -- per-session cap; nullable = inherits event capacity
  location_name (string nullable),                       -- room/track override for the session
  position (unsignedInteger default 0),
  attendees_count (unsignedBigInteger default 0),
  created_at, updated_at
  unique(event_id, slug)
  index(event_id, starts_at)

events_ticket_type_session           -- which sessions a ticket type grants access to; pivot
  ticket_type_id (FK events_ticket_types, cascadeOnDelete),
  session_id (FK events_sessions, cascadeOnDelete),
  primary(ticket_type_id, session_id)

events_events
  id, slug, title (json — translatable), description (json — translatable, nullable),
  category_id (nullable, FK events_categories, nullOnDelete),
  status (string — enum), visibility (string — enum),
  starts_at (timestamp), ends_at (timestamp),
  timezone (string default 'UTC'),
  location_kind (string — enum: physical|online|hybrid),
  location_name (string nullable), location_address (text nullable),
  online_url (string nullable),
  cover_path (string nullable),  -- denormalised from medialibrary

  -- Recurrence (single events leave both null)
  parent_event_id (nullable, self FK, cascadeOnDelete),  -- for occurrences of a recurring series
  recurrence_rule (json nullable),                       -- {frequency, interval, count, until, byDay, byMonthDay}

  -- Capacity
  capacity (unsignedInteger nullable),                   -- nullable = unlimited at event level
  sale_starts_at (timestamp nullable), sale_ends_at (timestamp nullable),
  cancelled_at (timestamp nullable), cancelled_by (FK users, nullable, nullOnDelete),
  cancellation_reason (text nullable),

  -- Counters (denormalised)
  tickets_sold_count (unsignedBigInteger default 0),
  attendees_count (unsignedBigInteger default 0),
  applications_pending_count (unsignedInteger default 0),

  created_at, updated_at, deleted_at
  index(status, starts_at)
  index(parent_event_id)

events_event_tag                     -- pivot
  event_id, tag_id, primary(event_id, tag_id)

events_event_organizers              -- pivot with role
  id, event_id, user_id, role (string — enum), created_at, updated_at
  unique(event_id, user_id)
```

### 5.2 Ticketing

```
events_ticket_types
  id, event_id (FK events_events, cascadeOnDelete),
  slug, name (json — translatable), description (json — translatable, nullable),
  mode (string — enum: open|application|rsvp),
  price_minor (unsignedBigInteger default 0), currency (string char(3) default 'USD'),
  refundable (boolean default true),
  capacity (unsignedInteger nullable),                  -- per-type capacity
  sold_count (unsignedBigInteger default 0),
  sale_starts_at, sale_ends_at (timestamps nullable),
  max_per_order (unsignedInteger default 10),
  attendance_form_id (nullable, FK events_attendance_forms, nullOnDelete),

  -- Transfers
  transferable (boolean default true),
  transfer_deadline_hours_before_event (unsignedInteger nullable),  -- null = no cutoff
  transfer_fee_minor (unsignedBigInteger nullable),                 -- null = free
  transfer_fee_currency (char(3) nullable),                         -- required when transfer_fee_minor is set

  -- EU consumer-protection refund window
  consumer_protection_exempt (boolean default false),               -- true for tickets covered by CRD Article 16(l) leisure-event exemption

  metadata (json nullable),                             -- arbitrary extension data
  position (unsignedInteger default 0),
  created_at, updated_at, deleted_at

events_orders
  id, event_id (FK), user_id (buyer — FK users, cascadeOnDelete),
  status (string — enum: pending|paid|cancelled|refunded|partially_refunded),
  subtotal_minor, discount_minor, tax_minor, total_minor (unsignedBigInteger),  -- total = subtotal − discount + tax
  tax_rate_basis_points (unsignedInteger nullable),     -- e.g. 1900 = 19.00% VAT; nullable when tax not applicable
  currency (char(3)),
  discount_code_id (nullable, FK events_discount_codes, nullOnDelete),
  processor (string nullable),                          -- e.g. 'stripe'
  processor_reference (string nullable),                -- gateway charge id
  paid_at (timestamp nullable),

  -- Group ticket assignment: each OrderItem captures holder pre-payment (see §5.3 + §11.1)
  assignment_completed_at (timestamp nullable),

  metadata (json nullable),
  created_at, updated_at, deleted_at

events_order_items
  id, order_id (FK, cascadeOnDelete),
  ticket_type_id (FK events_ticket_types, restrictOnDelete),
  quantity (unsignedInteger),
  unit_price_minor (unsignedBigInteger),
  line_total_minor (unsignedBigInteger),
  created_at, updated_at

events_order_item_assignments         -- buyer assigns each individual ticket to a holder at checkout
  id, order_item_id (FK, cascadeOnDelete),
  seat_index (unsignedInteger),                                  -- 0..quantity-1
  holder_user_id (FK users, nullable, nullOnDelete),             -- null when registering a guest by email
  holder_name (string),                                          -- always captured (mirrors user name when set)
  holder_email (string),                                         -- always captured (mirrors user email when set)
  holder_metadata (json nullable),                               -- optional profile snapshot per holder
  created_at, updated_at
  unique(order_item_id, seat_index)

events_tickets
  id, order_item_id (FK events_order_items, cascadeOnDelete),
  order_item_assignment_id (nullable, FK events_order_item_assignments, nullOnDelete),
  ticket_type_id (FK), event_id (FK),
  holder_id (FK users, nullable, nullOnDelete),         -- may differ from order.user_id (gifted/transferred); null for guest holders
  holder_name (string), holder_email (string),          -- denormalised from assignment for guest tickets
  status (string — enum: issued|cancelled|refunded|checked_in|transferred),
  qr_token (string unique),                             -- signed token for scanning
  checked_in_at (timestamp nullable),
  checked_in_by (FK users nullable, nullOnDelete),
  transferred_from (FK users nullable, nullOnDelete),   -- audit trail for transfers
  transferred_at (timestamp nullable),
  transfer_fee_order_id (nullable, FK events_orders, nullOnDelete),  -- when transfer required payment, this is the fee Order
  metadata (json nullable),
  created_at, updated_at, deleted_at
  index(event_id, status)

events_session_check_ins              -- check-in once per (session × ticket)
  id, session_id (FK events_sessions, cascadeOnDelete),
  ticket_id (FK events_tickets, cascadeOnDelete),
  checked_in_at (timestamp),
  checked_in_by (FK users nullable, nullOnDelete),
  created_at, updated_at
  unique(session_id, ticket_id)

events_discount_codes
  id, code (string unique),
  description (string nullable),
  kind (string — enum: percent|flat_amount),
  amount_minor (unsignedBigInteger),                    -- percent stored as basis points (1 bp = 0.01%, so 1000 = 10.00%) OR minor units for flat_amount
  currency (char(3) nullable),                          -- required when kind=flat_amount
  application_scope (string — enum: order|per_ticket),  -- flat_amount: $5 off the order, or $5 off each ticket?
  applies_to (string — enum: global|events_subset),
  starts_at, expires_at (timestamps nullable),
  max_uses_total (unsignedInteger nullable),
  max_uses_per_user (unsignedInteger nullable),
  uses_count (unsignedBigInteger default 0),
  active (boolean default true),
  created_at, updated_at, deleted_at

events_discount_code_event             -- pivot when applies_to=events_subset
  discount_code_id, event_id, primary(discount_code_id, event_id)

events_discount_code_usages
  id, discount_code_id (FK), order_id (FK), user_id (FK users),
  applied_minor (unsignedBigInteger), currency (char(3)),
  created_at, updated_at
  index(discount_code_id, user_id)                      -- max_uses_per_user query
```

### 5.3 Attendance

```
events_attendance_forms                -- reusable forms attached to ticket types
  id, event_id (FK), name (string),
  schema (json),                                       -- list of {key,label,type,required,options}
  created_at, updated_at

events_applications
  id, event_id (FK), ticket_type_id (FK),
  applicant_id (FK users, cascadeOnDelete),
  status (string — enum: pending|approved|rejected|withdrawn|expired),
  submitted_at (timestamp),
  decided_at (timestamp nullable),
  decided_by (FK users nullable, nullOnDelete),
  decision_note (text nullable),

  -- Paid upfront for application-mode tickets
  reservation_order_id (nullable, FK events_orders, nullOnDelete),

  metadata (json nullable),
  created_at, updated_at
  unique(applicant_id, ticket_type_id)
  index(status, submitted_at)

events_attendees                       -- a person attending; created when ticket issued
  id, event_id, ticket_id (FK events_tickets, cascadeOnDelete),
  user_id (FK users, cascadeOnDelete),
  status (string — enum: registered|cancelled|checked_in|no_show),
  profile (json),                                      -- open-ended; suggested keys: name, email, date_of_birth, gender, dietary, t_shirt_size. Consumer defines the shape.
  created_at, updated_at
  unique(event_id, user_id)

events_attendance_responses            -- attendee's answers to AttendanceForm
  id, attendee_id (FK), attendance_form_id (FK),
  answers (json),                                      -- {question_key: value}
  created_at, updated_at
```

### 5.4 Eligibility

```
events_requirements
  id, event_id (nullable, FK events_events, cascadeOnDelete),
  ticket_type_id (nullable, FK events_ticket_types, cascadeOnDelete),
  type (string — enum: age_min|age_max|document|group_membership|gender|free_form_question|custom_rule),
  payload (json),                                      -- type-specific config
  strict (boolean default true),                       -- true = auto-reject on fail; false = flag for organizer
  position (unsignedInteger default 0),
  created_at, updated_at

  -- Either event_id or ticket_type_id is set (XOR). Event-level requirements apply to all ticket types;
  -- type-level requirements override/augment for that type.

events_requirement_checks
  id, attendee_id (nullable, FK events_attendees, cascadeOnDelete),
  application_id (nullable, FK events_applications, cascadeOnDelete),
  requirement_id (FK events_requirements, cascadeOnDelete),
  status (string — enum: pending|passed|failed|waived),
  result (json nullable),                              -- engine output / reviewer notes
  reviewed_by (FK users nullable, nullOnDelete),
  reviewed_at (timestamp nullable),
  created_at, updated_at
  unique(attendee_id, requirement_id)
  unique(application_id, requirement_id)

events_document_uploads                -- attendees upload IDs / proofs
  id, attendee_id (FK), requirement_id (FK),
  kind (string nullable),                              -- 'id_card', 'passport', 'student_card', ...
  filename (string),                                   -- denormalised; file in medialibrary
  mime_type (string), byte_size (unsignedBigInteger),
  metadata (json nullable),                            -- e.g. extracted text, OCR result
  created_at, updated_at, deleted_at

events_document_verifications
  id, document_upload_id (FK, cascadeOnDelete),
  status (string — enum: pending|verified|rejected),
  decided_by (FK users nullable, nullOnDelete),
  decided_at (timestamp nullable),
  note (text nullable),
  created_at, updated_at
```

### 5.5 Flow

```
events_sale_queue_entries              -- waiting-room before sale_starts_at
  id, event_id (FK), user_id (FK users, cascadeOnDelete),
  joined_at (timestamp),
  position (unsignedBigInteger),                       -- absolute order; rebuilt on join
  released_at (timestamp nullable),                    -- when their turn started
  expires_at (timestamp nullable),                     -- end of their checkout window
  last_heartbeat_at (timestamp),
  status (string — enum: waiting|active|expired|completed|abandoned),
  created_at, updated_at
  unique(event_id, user_id)
  index(event_id, status, position)

events_waitlist_entries                -- after ticket_type sells out
  id, ticket_type_id (FK), user_id (FK users, cascadeOnDelete),
  quantity (unsignedInteger),
  status (string — enum: waiting|offered|claimed|expired),
  offered_at (timestamp nullable),
  claim_expires_at (timestamp nullable),
  created_at, updated_at
  unique(ticket_type_id, user_id)
  index(ticket_type_id, status, created_at)

events_refunds
  id, order_id (FK events_orders, cascadeOnDelete),
  ticket_id (nullable, FK events_tickets, nullOnDelete),     -- present for partial per-ticket refunds
  amount_minor (unsignedBigInteger), currency (char(3)),
  reason (string — enum: rejection|cancelled_event|attendee_request|organizer_initiated|other),
  reason_note (text nullable),
  status (string — enum: pending|processed|failed),
  processor_reference (string nullable),                     -- consumer's payment gateway refund id
  requested_by (FK users nullable, nullOnDelete),
  processed_by (FK users nullable, nullOnDelete),
  processed_at (timestamp nullable),
  metadata (json nullable),
  created_at, updated_at
```

## 6. Enums

```php
namespace Kurt\Modules\Events\Catalog\Enums;
enum EventStatus: string { case Draft='draft'; case Published='published'; case Cancelled='cancelled'; case Completed='completed'; }
enum EventVisibility: string { case Public='public'; case Unlisted='unlisted'; case Private='private'; }
enum LocationKind: string { case Physical='physical'; case Online='online'; case Hybrid='hybrid'; }
enum RecurrenceFrequency: string { case None='none'; case Daily='daily'; case Weekly='weekly'; case Monthly='monthly'; case Yearly='yearly'; }
enum OrganizerRole: string { case Owner='owner'; case Manager='manager'; case Scanner='scanner'; }
// Sessions reuse AttendeeStatus for check-in tracking; no dedicated enum needed.

namespace Kurt\Modules\Events\Ticketing\Enums;
enum TicketTypeMode: string { case Open='open'; case Application='application'; case Rsvp='rsvp'; }
enum OrderStatus: string { case Pending='pending'; case Paid='paid'; case Cancelled='cancelled'; case Refunded='refunded'; case PartiallyRefunded='partially_refunded'; }
enum TicketStatus: string { case Issued='issued'; case Cancelled='cancelled'; case Refunded='refunded'; case CheckedIn='checked_in'; case Transferred='transferred'; }
enum DiscountKind: string { case Percent='percent'; case FlatAmount='flat_amount'; }
enum DiscountApplicationScope: string { case Order='order'; case PerTicket='per_ticket'; }
enum DiscountScope: string { case Global='global'; case EventsSubset='events_subset'; }

namespace Kurt\Modules\Events\Attendance\Enums;
enum ApplicationStatus: string { case Pending='pending'; case Approved='approved'; case Rejected='rejected'; case Withdrawn='withdrawn'; case Expired='expired'; }
enum AttendeeStatus: string { case Registered='registered'; case Cancelled='cancelled'; case CheckedIn='checked_in'; case NoShow='no_show'; }

namespace Kurt\Modules\Events\Eligibility\Enums;
enum RequirementType: string {
    case AgeMin='age_min'; case AgeMax='age_max';
    case Document='document'; case GroupMembership='group_membership'; case Gender='gender';
    case FreeFormQuestion='free_form_question'; case CustomRule='custom_rule';
}
enum CheckStatus: string { case Pending='pending'; case Passed='passed'; case Failed='failed'; case Waived='waived'; }
enum VerificationStatus: string { case Pending='pending'; case Verified='verified'; case Rejected='rejected'; }

namespace Kurt\Modules\Events\Flow\Enums;
enum QueueStatus: string { case Waiting='waiting'; case Active='active'; case Expired='expired'; case Completed='completed'; case Abandoned='abandoned'; }
enum WaitlistStatus: string { case Waiting='waiting'; case Offered='offered'; case Claimed='claimed'; case Expired='expired'; }
enum RefundStatus: string { case Pending='pending'; case Processed='processed'; case Failed='failed'; }
enum RefundReason: string { case Rejection='rejection'; case CancelledEvent='cancelled_event'; case AttendeeRequest='attendee_request'; case OrganizerInitiated='organizer_initiated'; case Other='other'; }
```

## 7. Eligibility engine (the tricky part)

### 7.1 Contract

```php
namespace Kurt\Modules\Events\Eligibility\Contracts;

use Illuminate\Database\Eloquent\Model;

interface RequirementEvaluator
{
    /** Returns true when the requirement passes for this attendee. */
    public function evaluate(Model $attendee, array $payload, array $context = []): CheckResult;
}
```

```php
namespace Kurt\Modules\Events\Eligibility\Engine;

final readonly class CheckResult
{
    public function __construct(
        public bool $passed,
        public ?string $message = null,
        public array $data = [],
    ) {}

    public static function pass(array $data = []): self;
    public static function fail(string $message, array $data = []): self;
    public static function pending(string $message = 'Awaiting review'): self;  // for manual review
}
```

### 7.2 Default evaluators (shipped)

| Type | Evaluator | Behaviour |
|---|---|---|
| `age_min` | `AgeMinEvaluator` | Reads `$attendee->profile['date_of_birth']`. Fails if `< payload.min`. |
| `age_max` | `AgeMaxEvaluator` | Same but `>`. |
| `document` | `DocumentEvaluator` | Looks up uploaded document for this requirement. Returns pending until verified (manual or auto via `DocumentVerifier` contract). |
| `group_membership` | `GroupMembershipEvaluator` | Calls a consumer-supplied `GroupResolver` contract — returns the user's group identifiers. Passes when `payload.group` intersects. |
| `gender` | `GenderEvaluator` | Reads `$attendee->profile['gender']`. Passes when matches any of `payload.allowed`. |
| `free_form_question` | `FreeFormEvaluator` | Always returns pending (organizer reviews response manually). |
| `custom_rule` | `CustomRuleEvaluator` | `payload.evaluator` is the FQCN of a class implementing `RequirementEvaluator`. Module instantiates via container and delegates. |

### 7.3 RequirementEngine

```php
namespace Kurt\Modules\Events\Eligibility\Engine;

final class RequirementEngine
{
    /** Evaluate all requirements attached to a ticket type for a single attendee. */
    public function evaluateFor(Attendee $attendee, TicketType $ticketType): EvaluationOutcome;
}

final readonly class EvaluationOutcome
{
    public function __construct(
        public bool $allPassed,
        public bool $anyStrictFailed,
        /** @var array<int, RequirementCheck> */
        public array $checks,
    ) {}
}
```

The engine runs every applicable Requirement, persists `RequirementCheck` rows, and returns an outcome. If `anyStrictFailed`, the consumer should reject the application or refuse the ticket; otherwise the organizer reviews flagged checks.

## 8. Sale queue (waiting-room)

### 8.1 Transport-agnostic design

The queue is a **DB table + a small algorithm**. Clients can interact via:
- **HTTP polling**: client calls `Events::joinQueue($event, $user)` then polls `Events::queueState($event, $user)` every N seconds.
- **Broadcasting**: client subscribes to `private-events.queue.{eventId}.user.{userId}`. The module broadcasts `QueueReleased($entry)` when their turn opens.

Both work. The module dispatches `QueueReleased` as a `ShouldBroadcast` event; consumers who don't use broadcasting just rely on polling.

### 8.2 Heartbeat

To keep the queue honest, each waiting user must heartbeat. `Events::queueHeartbeat($entry)` updates `last_heartbeat_at`. A scheduled command `events:prune-queue` marks `status=abandoned` for entries with `last_heartbeat_at < now() - config('events.queue.heartbeat_timeout_seconds', 60)`.

### 8.3 Release algorithm

When `sale_starts_at` is reached, `events:release-queue` runs:
1. Counts current `active` entries for the event.
2. Promotes the lowest-`position` `waiting` entries up to `config('events.queue.active_concurrency', 100)` minus current actives.
3. For each promoted entry, set `status=active`, `released_at=now()`, `expires_at=now() + active_window_seconds`.
4. Dispatch `QueueReleased(entry)`.

A separate scheduled tick expires `active` entries past `expires_at` and promotes the next waiters.

## 9. Waitlist

When a `TicketType` sells out, users join `events_waitlist_entries`. When a ticket is cancelled/refunded, the top entry is `offered_at = now()`, given a `claim_expires_at`, and dispatched `WaitlistPromoted` (broadcast). Consumer collects their payment; on success calls `Events::claim($waitlistEntry)`. On expiry, next entry is offered.

## 10. Discount codes

### 10.1 Calculation

`PriceCalculator::apply(Order $draftOrder, DiscountCode $code): PriceBreakdown` returns:

```php
final readonly class PriceBreakdown
{
    public int $subtotalMinor;
    public int $discountMinor;
    public int $totalMinor;
    public string $currency;
}
```

**Percent codes** use basis points where 1 bp = 0.01% (so `1000` represents 10.00%) and apply to the order subtotal.

**Flat-amount codes** use minor units in a specific currency. Their `application_scope` selects either:
- `order` — single flat deduction off the order subtotal.
- `per_ticket` — deducted from each ticket line (multiplied by item quantity).

Flat-amount codes are rejected when their currency doesn't match the order's currency. (Cross-currency conversion is consumer territory — see §25 follow-ups.)

### 10.2 Limits enforcement

Before applying, the calculator checks:
- `code.active`
- `now() between starts_at and expires_at` (if set)
- `code.uses_count < max_uses_total` (if set)
- For per-user: `count(discount_code_usages where code_id and user_id) < max_uses_per_user`
- `applies_to`: global, or event_id ∈ pivot

Failing any check throws a typed `DiscountCodeNotApplicable` exception with reason.

### 10.3 Audit

Every successful application writes a `DiscountCodeUsage` row (code × order × user × applied_amount + timestamp). Admin can query by code, by user, or by event.

## 11. Group tickets, sessions, and transfers

### 11.1 Group ticket assignment at checkout

A buyer purchasing N tickets supplies one assignment row per seat (`{name, email, user_id?, metadata?}`). The buyer can:
- assign a seat to themselves (`user_id = $buyer->id`),
- assign to a registered user by `user_id`, or
- assign to a guest by `name + email` (no user account).

Assignment rows persist in `events_order_item_assignments` before payment. On `OrderPaid`, the module issues one Ticket per assignment, copying `holder_*` columns. The buyer can rename/replace assignments while the order is still `pending`; once paid, changes go through the transfer flow.

### 11.2 Multi-session events

`events_sessions` rows belong to an Event. `TicketType::sessions()` BelongsToMany pivots to `events_ticket_type_session`. Selecting a ticket type implicitly grants access to its linked sessions.

Check-in is per-session: `Events::checkInSession(Ticket $ticket, Session $session, User $scanner)` writes an `events_session_check_ins` row (unique on `session_id × ticket_id`). The event-level `Events::checkIn()` remains valid for single-session events; for multi-session events, the session-level call is canonical.

### 11.3 Ticket transfers

Transfer rules per ticket type:
- `transferable=false` → all transfers rejected.
- `transfer_deadline_hours_before_event` (nullable) → transfers rejected when `now() > event.starts_at - hours`.
- `transfer_fee_minor` + `transfer_fee_currency` (nullable) → fee charged before transfer completes.

Flow:

1. `Events::transferTicket(Ticket $ticket, Model $newHolder): Ticket` validates rules.
2. If a fee is configured: create a single-line fee Order in `pending` status. The returned Ticket has `transfer_fee_order_id` set; `holder_id` is unchanged until payment lands. `TicketTransferRequested` is dispatched.
3. When `Events::pay($feeOrder, ...)` lands: the ticket's `holder_id` flips, `transferred_from` records the old holder, `transferred_at` is set, `TicketTransferred` is dispatched. Notifications (when enabled) go to both holders.
4. If no fee: the swap happens immediately and `TicketTransferred` fires.

### 11.4 Tax on Order

`tax_minor` and `tax_rate_basis_points` are populated by the consumer's payment integration (calculated against the buyer's jurisdiction). The module accepts the numbers, persists them, and ensures `total_minor = subtotal_minor − discount_minor + tax_minor`. The module never computes tax itself; consumer payment listeners write the values back via an extension on `Events::pay()` (signature includes optional tax breakdown).

## 12. Recurrence + ICS

### 12.1 Recurrence

`recurrence_rule` is a small JSON DSL (subset of RFC 5545 RRULE):
```json
{ "frequency": "weekly", "interval": 1, "byDay": ["MO", "WE", "FR"], "count": 12 }
```

`events:generate-occurrences` runs daily (via the scheduler) and materialises Occurrence rows (which are themselves `Event` rows with `parent_event_id` set) for the next `config('events.recurrence.window_days', 90)` days. Occurrences inherit ticket types from the parent unless overridden.

### 12.2 ICS export

`IcsExporter::forEvent(Event $event): string` returns a VCALENDAR string. `Event::ics()` returns a `Response` with `text/calendar` Content-Type. Used by consumer routes to provide download links — module ships no routes.

## 13. Refunds

`Refund` rows are first-class. Lifecycle:

1. `RefundRequested` dispatched (e.g., from `Events::reject($application, ...)` or `Events::cancel($event, ...)` or `Events::requestRefund($order, ...)`). Row created with `status=pending`.
2. Consumer listens to `RefundRequested`, calls their payment gateway.
3. On success: consumer calls `Events::markRefundProcessed($refund, $processorReference)`. Status → `processed`.
4. On failure: consumer calls `Events::markRefundFailed($refund, $note)`. Status → `failed`. (Admin can retry.)

Order status auto-recomputes after each refund: full refund → `OrderStatus::Refunded`; partial → `OrderStatus::PartiallyRefunded`.

### 13.1 EU consumer-protection refund window

The EU Consumer Rights Directive (CRD, 2011/83/EU) gives consumers a 14-day right of withdrawal for most online purchases. **Tickets for "leisure services with a specific date or period of performance"** are exempt under CRD Article 16(l) — concerts, festivals, theatre, sports matches typically qualify and can be marked exempt.

Module behaviour:

- Config: `events.refunds.consumer_protection_window_days` (default `14`; set `0` to disable globally).
- Per ticket type: `TicketType.consumer_protection_exempt` (default `false`). When `true`, the window does not apply to tickets sold under that type.
- When an attendee calls `Events::requestRefund($order, ..., RefundReason::AttendeeRequest, ...)` and:
  - the order was paid within the consumer-protection window AND
  - no ticket on the order is `consumer_protection_exempt` AND
  - no ticket on the order has been checked in,
  then the refund is auto-approved: `Refund::status` skips `pending` review and starts a normal `RefundRequested` flow with a `consumer_protection_eligible=true` flag in `metadata`. The consumer's payment listener processes it as usual.
- Outside the window, refunds follow organizer-discretion rules (per ticket type's `refundable` flag + organizer approval).

Consumer apps operating outside the EU set the config to `0` to disable; consumer apps inside the EU keep the default. The module itself doesn't infer jurisdiction.

## 14. Domain events

Selection of dispatched events (all under `Kurt\Modules\Events\Events\`):

- Catalog: `EventCreated`, `EventUpdated`, `EventPublished`, `EventCancelled`, `EventCompleted`, `OccurrenceGenerated`, `SessionCreated`, `SessionUpdated`, `SessionDeleted`.
- Ticketing: `TicketTypeCreated`, `TicketTypeReleased`, `OrderCreated`, `OrderPaid`, `OrderCancelled`, `OrderRefunded`, `OrderPartiallyRefunded`, `TicketIssued`, `TicketTransferRequested`, `TicketTransferred`, `TicketCheckedIn`, `SessionCheckedIn`, `DiscountCodeApplied`.
- Attendance: `ApplicationSubmitted`, `ApplicationApproved`, `ApplicationRejected`, `AttendeeRegistered`, `AttendeeCancelled`, `AttendanceFormResponseStored`.
- Eligibility: `DocumentUploaded`, `DocumentVerified`, `DocumentRejected`, `RequirementCheckPassed`, `RequirementCheckFailed`.
- Flow: `QueueJoined`, `QueueReleased` (broadcast), `QueueExpired`, `WaitlistJoined`, `WaitlistPromoted` (broadcast), `WaitlistExpired`, `RefundRequested`, `RefundProcessed`, `RefundFailed`.

## 15. Top-level facade

```php
namespace Kurt\Modules\Events\Support;

use Illuminate\Database\Eloquent\Model;
use Kurt\Modules\Events\Catalog\Models\Event;
use Kurt\Modules\Events\Ticketing\Models\{Order, Ticket, TicketType, DiscountCode};
use Kurt\Modules\Events\Attendance\Models\{Application, Attendee};
use Kurt\Modules\Events\Flow\Models\{SaleQueueEntry, WaitlistEntry, Refund};

final class Events
{
    public function createEvent(array $data, Model $organizer): Event;
    public function publish(Event $event): void;
    public function cancel(Event $event, Model $canceller, string $reason): void;

    /**
     * @param array<int, array{name: string, email: string, user_id?: int|string|null, metadata?: array<string, mixed>}> $holderAssignments
     *        One row per seat; length must equal $quantity. Captures group-ticket assignment at checkout time.
     */
    public function reserve(TicketType $type, Model $buyer, int $quantity, array $holderAssignments, ?string $discountCode = null): Order;
    public function pay(Order $order, string $processor, string $reference): void;
    public function transferTicket(Ticket $ticket, Model $newHolder): Ticket;
    public function checkIn(Ticket $ticket, Model $scanner): Attendee;

    public function apply(TicketType $type, Model $applicant, array $formAnswers = []): Application;
    public function approve(Application $application, Model $approver): Ticket;
    public function reject(Application $application, Model $rejector, string $reason): ?Refund;

    public function requestRefund(Order|Ticket $target, Model $requester, RefundReason $reason, ?string $note = null): Refund;
    public function markRefundProcessed(Refund $refund, string $processorReference): void;
    public function markRefundFailed(Refund $refund, string $note): void;

    public function joinQueue(Event $event, Model $user): SaleQueueEntry;
    public function queueHeartbeat(SaleQueueEntry $entry): void;
    public function joinWaitlist(TicketType $type, Model $user, int $quantity = 1): WaitlistEntry;
    public function claimWaitlist(WaitlistEntry $entry): Order;
}
```

Bound as singleton in the container. A `Kurt\Modules\Events\Facades\Events` facade is a thin wrapper for static call ergonomics (optional).

## 16. Author/attendee traits

```
Kurt\Modules\Events\Contracts\EventOrganizer
Kurt\Modules\Events\Contracts\EventAttendee
Kurt\Modules\Events\Concerns\IsEventOrganizer
Kurt\Modules\Events\Concerns\IsEventAttendee
```

Attendee profile fields are app-controlled. The trait exposes `eventTickets()`, `eventOrders()`, `eventApplications()`, `eventAttendances()`, `eventOrganized()`.

## 17. Auth / policies

- `EventPolicy` — view (per visibility), update/delete (organizer with manager+role or staff via `canManageEvents` gate).
- `TicketTypePolicy` — manage by event organizer.
- `OrderPolicy` — view by buyer; refund by staff.
- `ApplicationPolicy` — view+withdraw by applicant; decide by organizer.
- `RefundPolicy` — request by buyer or staff; process by staff.
- `QueuePolicy`/`WaitlistPolicy` — join by any auth user (subject to event eligibility).

## 18. Config (`config/events.php`)

```php
return [
    'currency' => env('EVENTS_DEFAULT_CURRENCY', 'USD'),

    'queue' => [
        'enabled' => true,
        'active_concurrency' => 100,
        'active_window_seconds' => 300,    // 5-minute checkout slot
        'heartbeat_timeout_seconds' => 60,
    ],

    'waitlist' => [
        'enabled' => true,
        'claim_window_seconds' => 600,     // 10-minute claim window
    ],

    'recurrence' => [
        'enabled' => true,
        'window_days' => 90,
    ],

    'refunds' => [
        'consumer_protection_window_days' => 14, // EU CRD; set 0 to disable.
    ],

    'transfers' => [
        'allowed_by_default' => true,             // overridden per ticket type
    ],

    'tax' => [
        'enabled' => true,                        // when false, tax_minor + tax_rate_basis_points stay null
    ],

    'documents' => [
        'disk' => env('EVENTS_DOCUMENT_DISK', 'private'),
        'verifier' => null,                // FQCN implementing DocumentVerifier; null = manual review
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
        'group_resolver' => null,          // FQCN implementing GroupResolver; null = no groups
    ],

    'notifications' => [
        'enabled' => false,                // opt-in; when true, shipped Notification classes wire up
        'channels' => ['mail', 'database'],
    ],

    'broadcasting' => [
        'enabled' => true,                 // QueueReleased + WaitlistPromoted broadcast
    ],

    'reminders' => [
        'enabled' => true,
        'before_hours' => [24, 1],         // dispatch at T-24h and T-1h
    ],

    'models' => [
        // override points for each model, same pattern as Blog/Library
    ],
];
```

## 19. Console commands

- `events:release-queue` — runs every 10 seconds when scheduler is active. Promotes queued users.
- `events:prune-queue` — marks abandoned heartbeats.
- `events:expire-waitlist-claims` — runs every minute. Re-offers expired claims to next.
- `events:generate-occurrences` — daily. Materialises recurring events into the rolling window.
- `events:dispatch-reminders` — every 5 minutes. Sends reminders for events crossing each `before_hours` threshold.
- `events:demo` — seeds a sample event, ticket types, discount code, attendees.

## 20. Optional Laravel Notifications

Shipped under `src/Notifications/` (only registered when `config('events.notifications.enabled')`):

- `TicketIssued` (to holder)
- `TicketTransferred` (to old holder AND new holder)
- `EventReminderDue` (to attendees)
- `ApplicationApproved` (to applicant)
- `ApplicationRejected` (to applicant)
- `OrderPaid` (to buyer)
- `RefundProcessed` (to buyer)
- `WaitlistOffer` (to user with claim link)
- `SessionReminderDue` (to attendees of a specific session in a multi-session event)

Each uses `via(['mail', 'database'])` by default; consumer can override.

## 21. Filament admin (v1.1)

Resources to ship under `src/Filament/V{3,4,5}/Resources/`:

- `EventResource` (list, create, edit, manage organizers, manage ticket types, manage requirements).
- `TicketTypeResource` (relation-managed under EventResource).
- `OrderResource` (read-only main; refund action).
- `ApplicationResource` (queue view; approve/reject actions).
- `DiscountCodeResource`.
- `DocumentVerificationResource` (review queue).
- `WaitlistResource` (read-only diagnostic).
- `RefundResource`.

V1.0 ships **headless**. V1.1 adds the resources.

## 22. Testing matrix

### Unit
- All enums.
- `PriceCalculator`: discount math (percent + fixed); currency mismatch; over-discount clamped to 0; multi-item orders.
- `IcsExporter`: recurring + single events.
- Each requirement evaluator in isolation.

### Feature (Pest + Testbench)
- Event lifecycle: create → publish → cancel.
- Open-mode purchase happy path with group ticket assignment (3 holders assigned during one purchase).
- Application-mode happy path (apply → approve → ticket issued).
- Application-mode rejection with paid reservation → Refund row created with `status=pending`.
- Discount code limits: max_uses_total, max_uses_per_user, expired, inactive. Both percent and flat_amount (order + per_ticket scopes).
- Ticket transfer: free transfer happy path; transfer with fee creates fee Order; transfer rejected after deadline; transfer rejected when type is `transferable=false`.
- Multi-session: ticket type linked to sessions A+B; check-in for session A is independent of session B; session attendees_count denormalised correctly.
- EU consumer-protection refund: within window + non-exempt + uncheckedin → auto-approved; exempt type → manual flow; checked-in ticket → manual flow.
- Queue: join → release → expire → promote next.
- Waitlist: sell-out → join → cancel ticket → offer → claim or expire.
- Recurrence: rule expands to N occurrences over window; idempotent re-runs.
- ICS download for a single + recurring event.
- Refund flow end-to-end: requested → processed → order state updates.
- Eligibility engine: age min/max pass+fail; document upload+verify cycle; custom_rule via stub evaluator; strict vs flag behaviour.
- Notifications: Mail::fake + Notification::assertSentTo when enabled in config.
- Broadcasting: Event::fake + assertDispatched for `QueueReleased` + `WaitlistPromoted`.

Coverage target: **70% lines** (lower than Blog/Library/Forum/Chat at 80% because Events is bigger and lifecycle-heavy — many code paths are integration-level).

## 23. Repository setup

The repo `KurtModules-Events` does not yet exist on GitHub. Steps before implementation:

1. Create empty GitHub repo: `https://github.com/OzanKurt/KurtModules-Events`.
2. `git init` locally at `D:\Code\Projects\KurtModules-Events`.
3. `git remote add origin https://github.com/OzanKurt/KurtModules-Events`.
4. Initial commit: `SECURITY.md` (copy from Core) + `LICENSE.md` (MIT, same as siblings).
5. Push master.
6. Branch `v1.0` and start the implementation plan from there.

Implementation plan will reference this spec and structure work into ~15 tasks.

## 24. Definition of done (v1.0)

- [ ] Pint + PHPStan level 8 + Pest (≥ 70% line coverage) all green.
- [ ] CI matrix green on Laravel 12.
- [ ] All sub-aggregates have at least one feature test exercising the happy path.
- [ ] Eligibility engine tests cover every shipped evaluator.
- [ ] Queue release algorithm tested for fairness (position order preserved).
- [ ] Discount code limits enforced with audit row per usage.
- [ ] Refund flow end-to-end with payment-gateway hook.
- [ ] ICS exporter validated against a real calendar (manual verification once).
- [ ] README + CHANGELOG + LICENSE in place.
- [ ] Tagged `v1.0.0` after merge to master.

## 25. Open follow-ups (not in v1.0)

- Filament resources (v1.1).
- Group/role-based ticket pricing (e.g., student discount tied to verified document).
- Multi-language ticket emails.
- Per-ticket-type questions during checkout (currently per attendance form attached to type).
- Stripe Tax / VAT handling — out of scope; consumer's payment integration owns tax.
- Calendar sync (Google/Outlook OAuth) — out of scope.
- Geofencing for check-in — out of scope.
