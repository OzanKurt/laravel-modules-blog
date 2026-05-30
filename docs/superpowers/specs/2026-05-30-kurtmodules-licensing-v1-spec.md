# `ozankurt/laravel-modules-licensing` v1.0 — Spec

**Repo:** `KurtModules-Licensing` (to be created)
**Date:** 2026-05-30
**Status:** Draft → user review pending
**Family:** [KurtModules v2 umbrella](./2026-05-28-kurtmodules-v2-design.md)

---

## 1. Purpose

A **self-hosted software-licensing system** for selling premium Laravel packages (or any licenseable product). Fills a real gap: Laravel ships nothing for issuing/validating license keys, and the commercial options (Keygen, Anystack, Lemon Squeezy licensing) are hosted SaaS. This is the infrastructure a package author uses to sell their own premium packages — the model Spatie/Filament use (private composer repo + license key), made self-hostable.

Two roles in one package:

- **Server** — the vendor's store/admin app installs it. Issues + manages + validates license keys, runs the Filament admin, and gates private Composer downloads. Requires Core.
- **Client SDK** — the vendor embeds it inside each premium package they sell, so the package self-enforces its license at runtime (online activation + offline signed-file verification + seat enforcement). Written **Core-free** (pure `illuminate/*` + `ext-sodium`) so embedding adds negligible weight.

## 2. Family relationship

Sibling to Core/Blog/Chat/Forum/ResourceLibrary/Events/MediaLibrary. Same conventions per the [umbrella v2 design](./2026-05-28-kurtmodules-v2-design.md). The **server** depends on Core only. The **client SDK** depends on nothing from the family (so premium packages embedding it don't drag the family in). Revocation composes with the Events module via events — **not** a hard dependency.

## 3. Stack & metadata

| | |
|---|---|
| Composer name | `ozankurt/laravel-modules-licensing` |
| PHP namespace | `Kurt\Modules\Licensing\` |
| Table prefix | `licensing_` |
| PHP | `^8.4` (uses bundled `ext-sodium` for Ed25519) |
| Laravel | `^12.0 \|\| ^13.0` |
| Core (server only) | `ozankurt/laravel-modules-core: ^2.0` |
| Crypto | `ext-sodium` (bundled in PHP 8.4); no userland crypto lib |
| Filament | `^3.0 \|\| ^4.0 \|\| ^5.0` (require-dev; admin resources v1.0 — see §13) |
| Tests | Pest 3 + Testbench + PHPStan 8 + Pint, GH Actions CI |

`composer.json` `require`: `php`, `illuminate/contracts|support|http|database`, `ozankurt/laravel-modules-core`. `ext-sodium` declared in `require`. Note: Core is in `require` for the server provider; the client SDK classes are written to not reference Core so a premium package can use them without booting Core-specific features. (If embed weight ever matters, the client extracts cleanly to `ozankurt/laravel-licensing-client` — out of scope for v1.)

## 4. Architecture

```
src/
  Server/
    Models/{Product, License, Activation, LicenseEvent}.php
    Enums/{LicenseStatus, PolicyType, LicenseEventType}.php
    Support/{KeyGenerator, KeyHasher, LicenseFileSigner, LicenseIssuer, ActivationManager, LicenseValidator}.php
    Http/Controllers/{ActivateController, ValidateController, DeactivateController, LicenseFileController, ComposerRepositoryController}.php
    Http/Middleware/AuthenticatesComposer.php
    Support/ComposerAuthValidator.php
    Policies/{ProductPolicy, LicensePolicy, ActivationPolicy}.php
  Client/                          # Core-free; safe to embed in premium packages
    License.php                    # facade-style entry: License::for('vendor/pkg')
    LicenseManager.php             # online activate/validate/deactivate + offline fallback + grace
    OfflineVerifier.php            # Ed25519 verify of signed license file; expiry/updates-window checks
    Fingerprint.php                # stable domain/machine fingerprint
    HttpLicenseTransport.php       # talks to the server API
    Contracts/{LicenseTransport, LicenseCache}.php
    ValueObjects/{LicenseStatusResult, Entitlements, SignedLicenseFile}.php
  Crypto/
    Ed25519.php                    # thin sodium wrapper (sign/verify/keypair); shared by Server + Client
  Filament/
    LicensingPlugin.php            # version-dispatching facade
    V3/ V4/ V5/                    # ProductResource, LicenseResource, ActivationResource (+ event log)
  Concerns/IsLicensee.php          # optional trait for the consumer's User model (server side)
  Contracts/Licensee.php
  Events/                          # domain events
  Providers/LicensingServiceProvider.php
  Support/Licensing.php            # top-level server facade-service
  Facades/Licensing.php
config/licensing.php
routes/licensing.php               # API + composer-repo routes (opt-in)
database/migrations/
database/factories/
resources/views/                   # composer packages.json view + (optional) license-file download view
tests/
```

## 5. Data model (server)

All tables: `bigIncrements` id, `softDeletes()` on domain rows, `timestamps()`.

```
licensing_products
  id, slug (unique), name (json — translatable), description (json — translatable, nullable),
  composer_packages (json),                        -- ['ozankurt/laravel-shield-pro', ...] gated by a license of this product
  default_policy (json),                            -- {type, duration_days?, max_activations, updates_window_days?}
  is_active (boolean default true),
  created_at, updated_at, deleted_at

licensing_licenses
  id, product_id (FK licensing_products, restrictOnDelete),
  key_hash (string, unique),                        -- hash of the issued key (never store plaintext)
  key_prefix (string),                              -- first 8 chars, shown in admin/support
  licensee_email (string),
  licensee_user_id (nullable, FK users, nullOnDelete),
  licensee_name (string nullable), licensee_company (string nullable),
  status (string — enum: active|suspended|expired|revoked),
  policy_type (string — enum: perpetual|subscription|updates_window),
  max_activations (unsignedInteger default 1),
  issued_at (timestamp),
  expires_at (timestamp nullable),                  -- subscription end (null = perpetual / updates_window)
  updates_until (timestamp nullable),               -- updates-window cutoff for composer downloads
  order_reference (string nullable),                -- consumer's payment/order id for refund→revoke
  revoked_at (timestamp nullable), revoked_reason (string nullable),
  metadata (json nullable),
  notes (text nullable),
  created_at, updated_at, deleted_at
  index(status), index(licensee_email)

licensing_activations
  id, license_id (FK licensing_licenses, cascadeOnDelete),
  fingerprint_hash (string),                        -- hashed device/domain fingerprint
  label (string nullable),                          -- human label: domain, hostname, machine id
  ip (string nullable), user_agent (string nullable),
  activated_at (timestamp), last_seen_at (timestamp nullable), deactivated_at (timestamp nullable),
  created_at, updated_at
  unique(license_id, fingerprint_hash)
  index(license_id, deactivated_at)

licensing_license_events
  id, license_id (FK licensing_licenses, cascadeOnDelete),
  action (string — enum: issued|activated|deactivated|validated|expired|revoked|renewed|suspended|composer_authorized|composer_denied|limit_reached),
  context (json nullable),                          -- ip, fingerprint, package requested, version, reason
  occurred_at (timestamp indexed),
  created_at, updated_at
```

## 6. Enums

```php
namespace Kurt\Modules\Licensing\Server\Enums;
enum LicenseStatus: string { case Active='active'; case Suspended='suspended'; case Expired='expired'; case Revoked='revoked'; }
enum PolicyType: string { case Perpetual='perpetual'; case Subscription='subscription'; case UpdatesWindow='updates_window'; }
enum LicenseEventType: string {
    case Issued='issued'; case Activated='activated'; case Deactivated='deactivated'; case Validated='validated';
    case Expired='expired'; case Revoked='revoked'; case Renewed='renewed'; case Suspended='suspended';
    case ComposerAuthorized='composer_authorized'; case ComposerDenied='composer_denied'; case LimitReached='limit_reached';
}
```

Per the family standard, these enums are framework-free (no Filament `HasLabel`/`HasColor`); the Filament admin sets labels/colors inline.

## 7. Key + license-file formats

### 7.1 Short license key

Human-pasteable: 4 groups of 4 base32 chars, e.g. `K7QF-3M2A-9XBC-R4ND` (+ optional product prefix). Generated by `KeyGenerator`. Stored **hashed** (`KeyHasher`, HMAC-SHA256 keyed by app key); `key_prefix` (first 8 visible chars) stored separately for support lookups. Used for: online activation, the `validate` heartbeat, and Composer HTTP-basic auth.

### 7.2 Signed license file (offline)

An Ed25519-signed token (compact `payload.signature`, base64url) embedding entitlements:

```json
{
  "license_id": 123, "product": "laravel-shield-pro",
  "licensee": "buyer@example.com",
  "policy_type": "updates_window",
  "max_activations": 2,
  "issued_at": "2026-05-30T...", "expires_at": null, "updates_until": "2027-05-30T...",
  "fingerprint": "sha256:...",      // bound to the activation that requested it (optional)
  "nonce": "..."
}
```

Signed with the vendor **private key**; verified by the client with the embedded **public key**. Lets the client check validity offline (expiry/updates-window) with no network.

### 7.3 Crypto

`Crypto\Ed25519` wraps `sodium_crypto_sign_*`. `licensing:keygen` produces a keypair: the **private key** goes in the server env (`LICENSING_SIGNING_KEY`), the **public key** is printed for the vendor to embed in each premium package's config (`licensing.public_key`). Shared by Server (sign) and Client (verify); no Core dependency in `Crypto\`.

## 8. Server API (routes/licensing.php, opt-in + throttled)

Registered when `config('licensing.routes.api_enabled')` (default true). All under `config('licensing.routes.prefix', 'licensing')`, `api` middleware + a configurable throttle.

| Method + path | Body | Returns |
|---|---|---|
| `POST /activate` | `key, fingerprint, label?` | activation record + signed license file; 422 over `max_activations`; 403 if not active/expired/revoked |
| `POST /validate` | `key` or `signed_file`, `fingerprint?` | `{status, entitlements, expires_at, updates_until}`; updates `last_seen_at` (heartbeat) |
| `POST /deactivate` | `key, fingerprint` | frees the seat |
| `GET  /license-file` | `key` (auth) | re-download the signed license file |

Each writes a `licensing_license_events` row. `LicenseValidator` centralises the active/expired/suspended/revoked + seat decision.

## 9. Composer download gating

### 9.1 Auth bridge

`AuthenticatesComposer` middleware + `ComposerAuthValidator`: authenticate HTTP-basic where **username = licensee email, password = license key**. Validate:
- key resolves to an `active` (or within-grace) license,
- the requested package ∈ the product's `composer_packages`,
- for a versioned dist download, the version's release date ≤ `updates_until` (when policy is `updates_window`), or the license is `active` (subscription) / always (perpetual).

On success → 200 + writes `composer_authorized` event; on failure → 403 + `composer_denied` event.

### 9.2 Optional built-in Composer repository

`ComposerRepositoryController` + `resources/views/composer/packages.blade.php` make the **licensing server itself a private Composer repository** (no separate Satis needed), serving:
- `GET /repo/packages.json` (behind `AuthenticatesComposer`) — the package metadata for the product(s) the authenticated license entitles.
- `GET /repo/dist/{package}/{version}.zip` (behind `AuthenticatesComposer` + entitlement/version check) — streams the dist artifact from a configured disk.

Enabled by `config('licensing.composer.repository_enabled')` (default false — vendors using an existing Satis just use the §9.1 guard in front of it). When enabled, the consumer adds:
```bash
composer config repositories.mine composer https://store.example.com/licensing/repo
composer config --auth http-basic.store.example.com buyer@example.com K7QF-3M2A-9XBC-R4ND
composer require ozankurt/laravel-shield-pro
```

Dist artifacts (zips) are uploaded per product+version to a configured disk (`config('licensing.composer.dist_disk')`); a `licensing:publish-dist {product} {version} {path}` command registers one. (Building artifacts from a git source is out of scope — consumer supplies the zip, same as Satis dist mirroring.)

## 10. Client SDK (Core-free, embed in premium packages)

### 10.1 Usage

A premium package adds `ozankurt/laravel-modules-licensing` to its `require`, publishes/sets its config with the vendor public key + server URL + product slug, then in its own service provider:

```php
use Kurt\Modules\Licensing\Client\License;
use Kurt\Modules\Licensing\Client\Fingerprint;

$status = License::for('ozankurt/laravel-shield-pro')
    ->serverUrl(config('shield-pro.licensing.server'))
    ->publicKey(config('shield-pro.licensing.public_key'))
    ->fingerprint(Fingerprint::forDomain())          // or ::forMachine()
    ->key(config('shield-pro.licensing.key'))
    ->check();                                         // LicenseStatusResult

if (! $status->valid()) {
    // the premium package decides: disable features, log, nag. SDK never hard-kills.
}
```

### 10.2 Behaviour

- **First run:** `check()` activates online (binds the fingerprint as a seat), caches the returned signed license file via a `LicenseCache` (default: framework cache store).
- **Steady state:** verifies the cached signed file **offline** (Ed25519 + expiry/updates-window) on every call — no network.
- **Heartbeat:** re-validates online every `config('licensing.client.heartbeat_days')` to catch revocation/seat changes; updates the cached file.
- **Offline grace:** if the server is unreachable, the cached signed file stays valid until `grace_days` past its last successful heartbeat, so an outage never breaks paying customers.
- **Contracts:** `LicenseTransport` (HTTP by default; swappable) + `LicenseCache` (framework cache by default) keep the client testable and Core-free.

### 10.3 Value objects

`LicenseStatusResult` (`valid()`, `status`, `reason`, `entitlements`, `expiresAt`, `updatesUntil`), `Entitlements`, `SignedLicenseFile`.

## 11. Server facade + issuance

`Kurt\Modules\Licensing\Support\Licensing`:

```php
Licensing::issue(Product $product, string $email, array $opts = []): License;   // mints key, signs file, dispatches LicenseIssued
Licensing::renew(License $license, ?Carbon $until = null): License;              // extends expires_at/updates_until
Licensing::suspend(License $license, string $reason): void;
Licensing::revoke(License $license, string $reason): void;                       // status=revoked; future validate/activate denied
Licensing::activate(License $license, string $fingerprint, ?string $label = null): Activation;
Licensing::deactivate(License $license, string $fingerprint): void;
Licensing::validate(string $key, ?string $fingerprint = null): LicenseStatusResult;
Licensing::signFileFor(License $license, ?string $fingerprint = null): string;   // Ed25519 signed token
Licensing::authorizeComposer(string $email, string $key, string $package, ?string $version = null): bool;
```

Bound as a singleton. `Facades\Licensing` is the thin facade.

## 12. Auth / policies

- Gates/Policies only. `ProductPolicy`, `LicensePolicy`, `ActivationPolicy` gate the admin behind a `canManageLicensing` gate.
- Public API endpoints (`/activate` etc.) are unauthenticated but throttled + key-validated (the key is the credential).
- Composer routes are behind `AuthenticatesComposer`.

## 13. Filament admin (v3/v4/v5)

Shipped in v1.0 (this module is admin-heavy — managing licenses IS the product), using the family's proven cross-version pattern (parallel `src/Filament/V{3,4,5}`, version-dispatching `LicensingPlugin::make()`, per-version PHPStan configs, Filament-major-guarded introspection tests, CI matrix axis — copy the Blog reference):

- `ProductResource` — name/description (translatable), composer_packages repeater, default policy builder.
- `LicenseResource` — issue / renew / suspend / revoke / re-send actions; activations relation manager; key shown as prefix only; status badge; filters by status/product. Issue action mints the key and surfaces it once (copyable).
- `ActivationResource` — read + force-deactivate.
- License-event log (read-only) under the License view.

## 14. Config (`config/licensing.php`)

```php
return [
    'signing_key' => env('LICENSING_SIGNING_KEY'),   // server: Ed25519 private key (base64). null on pure client installs.
    'public_key' => env('LICENSING_PUBLIC_KEY'),      // client: vendor public key to verify signed files.

    'key' => [
        'groups' => 4, 'group_size' => 4, 'alphabet' => 'ABCDEFGHJKMNPQRSTUVWXYZ23456789',
    ],

    'routes' => [
        'api_enabled' => true,
        'prefix' => 'licensing',
        'throttle' => '60,1',
    ],

    'composer' => [
        'repository_enabled' => false,                // turn on to make this server a private composer repo
        'dist_disk' => env('LICENSING_DIST_DISK', 'local'),
    ],

    'client' => [
        'heartbeat_days' => 7,
        'grace_days' => 14,
        'cache_store' => null,                        // null = default cache store
    ],

    'models' => [/* override points */],
];
```

## 15. Console commands

- `licensing:keygen` — generate + print an Ed25519 keypair (private for server env, public for clients).
- `licensing:issue {product} {email} {--seats=} {--policy=} {--expires=} {--updates-until=}` — mint a key (prints once).
- `licensing:revoke {key}` / `licensing:suspend {key}` / `licensing:renew {key} {--until=}`.
- `licensing:expire` — flip lapsed subscriptions → `expired` (scheduled daily).
- `licensing:prune-activations` — mark activations stale when `last_seen_at` is older than grace + heartbeat (scheduled).
- `licensing:publish-dist {product} {version} {path}` — register a dist artifact for the built-in composer repo.
- `licensing:demo` — seed a product + a couple of licenses + activations.

## 16. Events

`LicenseIssued`, `LicenseActivated`, `LicenseDeactivated`, `LicenseValidated`, `LicenseExpired`, `LicenseRevoked`, `LicenseRenewed`, `LicenseSuspended`, `ActivationLimitReached`, `ComposerDownloadAuthorized`, `ComposerDownloadDenied`. Consumers wire payment→`issue` and refund→`revoke` (the Events module's `RefundProcessed` is a natural trigger — via a consumer listener, no hard dependency).

## 17. Testing matrix

### Unit
- `Ed25519` sign/verify round-trip; tampered payload rejected; wrong-key rejected.
- `OfflineVerifier`: valid within window; expired; updates-window cutoff; clock-skew tolerance.
- `KeyGenerator` format + `KeyHasher` determinism; prefix extraction.
- `Fingerprint` stability for the same domain/machine.

### Feature (Pest + Testbench)
- Issue → key minted, hashed at rest, `LicenseIssued` fired.
- Activate happy path → activation row + signed file; seat-limit rejection at `max_activations`; reactivation of same fingerprint is idempotent.
- Validate/heartbeat → status + `last_seen_at`; revoked/expired/suspended return the right status.
- Deactivate frees a seat.
- Composer auth: active+entitled → authorized; expired/revoked → denied; wrong product → denied; version past `updates_until` → denied; all write the right event.
- Built-in composer repo: `packages.json` lists only entitled packages; dist download gated.
- Client SDK: online activate then offline `check()` works with server unreachable within grace; fails after grace; respects revocation on next heartbeat. (Transport faked.)
- Renew extends window; revoke denies subsequent activate/validate.
- Filament smoke per version (V3/V4/V5 introspection, per the pilot pattern).

Coverage target: **75% lines**.

## 18. Repository setup

Create `https://github.com/OzanKurt/KurtModules-Licensing`. Initial commit `SECURITY.md` + `LICENSE.md`. Branch `v1.0`. VCS repository entry for Core during dev (Core not yet on Packagist).

## 19. Definition of done (v1.0)

- [ ] Pint + PHPStan level 8 + Pest (≥75%) green.
- [ ] CI matrix green: Laravel 12 × Filament 3/4/5 (per-version PHPStan).
- [ ] Ed25519 sign/verify + offline verification proven.
- [ ] Activation + seat-limit + deactivate + revoke flows tested.
- [ ] Composer auth guard + built-in repo gated by license + version window.
- [ ] Client SDK online-activate → offline-check → grace → revocation cycle tested with a faked transport.
- [ ] Filament v3/v4/v5 admin resources (cross-version pattern) green across the matrix.
- [ ] README (server setup + client embed guide + composer-repo guide) + CHANGELOG + UPGRADE-1.0.
- [ ] Tagged `v1.0.0` + release.

## 20. Open follow-ups (not in v1.0)

- Extract a standalone `ozankurt/laravel-licensing-client` if embed weight becomes a concern.
- Self-service customer portal (buyers manage their own activations).
- Webhook-out on license lifecycle (would pair with a future webhooks module).
- Floating/concurrent licenses (lease-based seats) beyond fixed-fingerprint activations.
- Git-source → dist artifact building for the composer repo (currently consumer supplies the zip).
- Usage-metered licenses (pairs with a future metering module).

## 21. References

- [Umbrella v2 design](./2026-05-28-kurtmodules-v2-design.md)
- [Filament admin plan](../plans/2026-05-30-kurtmodules-filament-admin.md) — cross-version resource pattern to copy.
- [Events v1 spec](./2026-05-29-kurtmodules-events-v1-spec.md) — refund→revoke composition.
- Prior art: Keygen.sh, Anystack, Lemon Squeezy licensing, the Spatie/Filament private-composer + license-key model.
