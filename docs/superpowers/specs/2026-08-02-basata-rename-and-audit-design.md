# Basata v3 — Rename, Spec Audit and Modernization — Design Spec

**Date:** 2026-08-02
**Current package:** `ghanem/bee` (v2.1 on Packagist, 160 downloads, 49/month)
**New package:** `ghanem/basata`
**Authority:** Basata *Cash Collector Channel API* **v3.0.8** (25-page PDF, revised 22-10-2024)

## Why rename

The spec states plainly: *"This document assumes that Bee = Internal system"*. The
package was named after Basata's internal codename rather than the product. The
public name should be Basata.

Packagist names are permanent, so this is a republish, not a rename:
- publish `ghanem/basata`
- mark `ghanem/bee` **abandoned**, with `"abandoned": "ghanem/basata"` so Composer
  tells existing users exactly what to switch to.

## Audit findings (current code vs v3.0.8)

These are the substance of the release; the rename alone would ship the bugs.

| # | Finding | Severity |
|---|---|---|
| 1 | **Business errors read as success.** The API returns HTTP 200 with `success: false` and an error code in the body. `ApiClient::request()` only checks `$response->ok()`, so error 1016 (insufficient balance) or 1034 (transaction in progress) is returned to the caller as a successful result. | **Critical** |
| 2 | **`terminal_id` hardcoded to `'1'`.** The spec requires a unique External Terminal ID per terminal (FAQ Q3). Every consumer currently sends `1`. | **Critical** |
| 3 | **`ConfirmPrepaidCardRecharge` not implemented.** Spec 5.9 — mandatory after a successful payment when the service's input parameters contain a `card_data` record. Prepaid-card recharges are left unconfirmed. | **Important** |
| 4 | **No error-code mapping.** ~35 documented codes (1001–1034, 2000–2005, 20000) with no representation in the package. | **Important** |
| 5 | **Rate-limit returns a magic array** (`['error' => …, 'status_code' => 429]`) instead of throwing, so it is indistinguishable from a response payload. | **Important** |
| 6 | No Laravel 13 support (currently `^10|^11|^12`). | Minor |
| 7 | `minimum-stability: dev` in composer.json — ignored for installed dependencies, but wrong for a published library. | Minor |

## Targets

- PHP `^8.1` (unchanged)
- Laravel `^10.0|^11.0|^12.0|^13.0`
- Package `ghanem/basata`, namespace `Ghanem\Basata\`
- Repo: `gaitco/basata` (GitHub rename of `bee`, preserving history and issues)
- Dev: `orchestra/testbench`, `phpunit/phpunit` (keep PHPUnit — the package already uses it)

## Rename map

| Old | New |
|---|---|
| `ghanem/bee` | `ghanem/basata` |
| `Ghanem\Bee\` | `Ghanem\Basata\` |
| `BeeService` | `BasataService` |
| `BeeServiceProvider` | `BasataServiceProvider` |
| `Facades\Bee` / alias `Bee` | `Facades\Basata` / alias `Basata` |
| `BeeWebhookController` | `BasataWebhookController` |
| `BeeWebhookReceived` | `BasataWebhookReceived` |
| `config/bee.php`, `config('bee.*')` | `config/basata.php`, `config('basata.*')` |
| `BEE_*` env vars | `BASATA_*` |
| cache prefix `bee_` | `basata_` |
| webhook path `bee/webhook` | `basata/webhook` |

No backwards-compatibility shims. This is a new package name; consumers migrate
deliberately. The README documents the mapping.

## Architecture changes

### Error handling — `src/Exceptions/`

The spec's own contract is that a 200 response may still be a failure. The client
must inspect the body.

- `BasataException` — base, carries `int $code` (the API code), `string $apiMessage`, and the raw payload.
- `BasataAuthenticationException` — 1001, 1002, 1003, 1010, 1012, 1013
- `BasataValidationException` — 1004–1009, 1011, 1017, 1020, 1022, 1024, 2001–2005
- `BasataInsufficientBalanceException` — 1016
- `BasataRateLimitException` — 1033 (and the client-side limiter, replacing finding 5's magic array)
- `BasataTransactionInProgressException` — 1034
- `BasataNotFoundException` — 1014, 1015, 1018, 1021, 1026, 1027
- `BasataServerException` — 2000, 20000, and any undocumented code

`ErrorCode` — a PHP 8.1 backed enum of every documented code with `message()` and
`exceptionClass()`. Unknown codes map to `BasataServerException` rather than
being swallowed.

**Throwing is opt-in per config** (`basata.errors.throw`, default `true`). When
`false`, the client returns a failed `ApiResponse` carrying the same code, so
existing array-style call sites keep working.

### `terminal_id`

Becomes `config('basata.terminal_id')` (env `BASATA_TERMINAL_ID`), overridable
per call. The service provider **throws on boot** if it is empty and requests are
attempted — a silent default is what caused the bug.

### New action — `confirmPrepaidCardRecharge()`

Spec 5.9. Path `/service`, action `ConfirmPrepaidCardRecharge`, version 2.
Request: `payment_transaction_id`, `operation_status` (`SUCCESS`|`FAIL`).
Response: `info`. An `OperationStatus` backed enum expresses the two values.

### Full-spec audit

Every one of the 12 actions is checked field-by-field against the PDF: path,
action name, version, required request fields, and response shape. `language` is
a **required top-level field** (error 1011) present in every sample but missing
from the spec's own parameter table — verify it is always sent.

### Transaction status

`TransactionStatus` backed enum: `NEW`, `IN_PROGRESS`, `SUCCESS`, `ERROR`,
`DEPOSIT_ERROR`, `CANCELLED`, with `isFinal()` — per FAQ Q9, only `SUCCESS`,
`ERROR` and `DEPOSIT_ERROR` are final. This is the distinction that tells a
caller whether to keep polling.

## Kept as-is

Retry, logging, caching, rate limiting, webhooks, async jobs, batch transactions
and the DTO layer all stay — they work and are tested. They are renamed, not
redesigned.

## Testing

- PHPUnit + Testbench, `Http::fake()` for all API interaction. No live calls.
- Every one of the 12 actions gets a test asserting the exact request body sent
  (path, action, version, language, terminal_id, data) against the PDF samples.
- Error mapping: one test per exception class, plus an unknown-code test.
- Regression tests for findings 1 and 2 specifically: a 200-with-`success:false`
  body must not read as success; `terminal_id` must come from config.
- CI: Laravel 10/11/12/13 × PHP 8.1–8.4, invalid combinations excluded.

## Out of scope

- Live/staging API calls — credentials are the user's, tests stay faked.
- The V12.0 / voucher-specific flows beyond what the PDF documents.
- Backwards-compatibility aliases for the old `Bee` names.
