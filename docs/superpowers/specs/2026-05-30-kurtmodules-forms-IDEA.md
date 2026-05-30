# `ozankurt/laravel-modules-forms` — PARKED IDEA (not yet planned)

**Date:** 2026-05-30
**Status:** Idea captured during brainstorming, then parked by request. NOT scheduled for build.
**Family:** [KurtModules v2 umbrella](./2026-05-28-kurtmodules-v2-design.md)

This records the decisions reached before the idea was shelved, so it can resume cleanly later.

## Concept

A **public data-entry / forms** module: routes/forms that accept submissions from unauthenticated (or optionally authenticated) visitors, with first-class validation + anti-abuse. Closes the "Forms / Surveys" gap from the SaaS-gap landscape. Public endpoints are inherently easy to test (no auth needed) — which was the original motivation.

Identity (proposed): `ozankurt/laravel-modules-forms`, namespace `Kurt\Modules\Forms\`, table prefix `forms_`. Family conventions (PHP 8.4, Laravel 12, Filament 3/4/5, Core dep).

## Approved decisions

- **Two layers (both):**
  1. **Endpoint-hardening toolkit** — a `PublicDataEntry` middleware/route-macro the dev attaches to their *own* routes: honeypot, min-fill-time, per-IP rate limit, `CaptchaVerifier` contract (no shipped provider), optional `SpamDetector` contract, optional submission audit log, CORS option. Consumer keeps their own controller + FormRequest.
  2. **Form-definition layer (optional)** — data-driven `Form` (JSON `schema` of fields + validation rules + settings) auto-exposed at `POST /forms/{form:slug}`; stores `Submission` rows; notify-only mode when storage disabled.
- **Field schema as JSON** on `forms_forms` (repeater-editable in Filament), not a separate fields table — matches Events `AttendanceForm`.
- **Anti-spam defaults ON** — honeypot + min-fill-time + per-IP rate limit by default; captcha opt-in via contract.
- **Owner optional polymorphic** (`owner_type`/`owner_id`) — single- or multi-tenant, like MediaLibrary.
- **Not necessarily anonymous** — capture `user_id` when the submitter is logged in; forms can require identifying fields via schema rules.

## Sketch data model

```
forms_forms        slug, name(json), description(json?), schema(json), settings(json),
                   owner_type?, owner_id?, is_public, is_active, submissions_count, timestamps, softDeletes
forms_submissions  form_id?, route?, data(json), user_id?, ip, user_agent, referer,
                   status(new|read|spam|archived), spam_score?, meta(json), timestamps, softDeletes
```

Enums: `FieldType` (text/textarea/email/url/number/tel/select/multiselect/checkbox/radio/date/datetime/file/hidden), `SubmissionStatus` (new/read/spam/archived).

## Shared

- Events: `SubmissionReceived`, `SubmissionFlaggedSpam`, `SubmissionStatusChanged`, `FormPublished`.
- Optional Notifications: `NewSubmission` (Mail+Database) to `settings.notify_emails`.
- Filament v3/v4/v5 (cross-version pattern): `FormResource` (schema repeater + settings), `SubmissionResource` (triage inbox, mark read/spam/archive, CSV export).
- Console: `forms:prune-submissions`, `forms:export`, `forms:demo`.
- File fields → configured disk by default; `spatie/laravel-medialibrary` optional.

## To resume

Run brainstorming-confirm on any open detail, then writing-plans → build via the family's subagent or inline flow, including the Filament cross-version treatment ([plan](../plans/2026-05-30-kurtmodules-filament-admin.md)).
