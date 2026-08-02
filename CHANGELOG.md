# Changelog

All notable changes to `ghanem/basata` are documented here.

## 3.0.0

`ghanem/basata` is the republished, audited, and modernized successor to
`ghanem/bee`. It is not a drop-in upgrade — see the **Migrating from
`ghanem/bee`** section in the README for the full rename map and required
`.env` changes before upgrading.

### Fixed (behaviour changes for every existing user)

- **Business failures were silently read as success.** The Basata API
  returns HTTP 200 even when a request fails (e.g. insufficient balance,
  transaction in progress) with `"success": false` and an error code in the
  body. The client only checked the HTTP status, so callers received these
  as successful responses. Success is now read from the response body; a
  failure throws a typed exception by default (see "Error handling" below).
- **`terminal_id` was hardcoded to `'1'` on every request**, for every
  installation, regardless of the (unused) `BEE_TERMINAL_ID`/`terminal_id`
  config value. The spec requires a unique External Terminal ID per terminal
  (FAQ Q3). `terminal_id` is now resolved from `BASATA_TERMINAL_ID` and is
  **required** — an empty value throws immediately (API code 1024) instead
  of silently sending `1`. **Every existing user must set
  `BASATA_TERMINAL_ID` before upgrading.**

### Added

- **Required-field validation** on `transactionInquiry()`/
  `transactionPayment()`. Previously, a caller who forgot a required field
  silently got a client-side default substituted (`amount` → `1.5`,
  `service_id` → `14`, and others) — meaning a bug in caller code could post
  a real 1.5 EGP payment against the wrong service instead of failing. Every
  spec-required (`+`) field now throws `BasataValidationException` instead.
- **`confirmPrepaidCardRecharge()`** — spec 5.9, `ConfirmPrepaidCardRecharge`
  action. Confirms a prepaid-card recharge after a successful
  `transactionPayment()` for a service whose input parameters include a
  `card_data` record. Backed by the new `OperationStatus` enum
  (`Success`/`Fail`).
- **Typed error layer**: `Ghanem\Basata\Enums\ErrorCode`, a backed enum of
  every documented API error code (1001–1034, 2000–2005, 20000) with spec
  message text, plus `Ghanem\Basata\Exceptions\BasataException` and 7
  subclasses (`BasataAuthenticationException`, `BasataValidationException`,
  `BasataInsufficientBalanceException`, `BasataRateLimitException`,
  `BasataTransactionInProgressException`, `BasataNotFoundException`,
  `BasataServerException`). Throwing is switched by the new
  `basata.errors.throw` config (env `BASATA_ERRORS_THROW`, default `true`);
  set it to `false` to get the raw failure payload back as an array instead.
- **`Ghanem\Basata\Enums\TransactionStatus`**, a backed enum of the
  transaction lifecycle (`NEW`, `IN_PROGRESS`, `SUCCESS`, `ERROR`,
  `DEPOSIT_ERROR`, `CANCELLED`) with `isFinal()` to tell a caller whether to
  keep polling.
- **`Ghanem\Basata\Enums\OperationStatus`** (`Success`/`Fail`), used by
  `confirmPrepaidCardRecharge()`.
- Laravel 13 support (`illuminate/support`/`illuminate/http` `^13.0`,
  `orchestra/testbench` `^11.0`).
- CI matrix covering PHP 8.1–8.4 × Laravel 10–13 (12 verified combinations).

### Fixed (internal correctness, found during the spec audit)

- Rate limiting returned a bare `['error' => …, 'status_code' => 429]` array
  indistinguishable from a real response payload; it now throws
  `BasataRateLimitException` (code 1033) through the same error layer as
  every other failure (still subject to `basata.errors.throw`).
- The `service_charge` field was briefly removed from `TransactionPayment`
  during the audit on the reasoning that the spec's §5.8 field table omits
  it, then restored: FAQ A6 and error 1022 ("Wrong service charge") both
  confirm the client submits it, and that table also omits `language`
  (which every sample sends and error 1011 requires) — the table is
  demonstrably incomplete.
- `BasataService`'s `*Dto` methods (`getCategoryListDto()`,
  `getServiceListDto()`, `getTransactionDto()`) sent their own hand-built
  request payloads with stale placeholder fields (`['s' => 1]`,
  `['s' => 'd']`) instead of routing through the same `ApiClient` action
  methods as the non-DTO methods — a wire-contract fix could miss this half
  of the class. They now share one code path via `toApiResponse()`.
- `language` is now injected centrally in `ApiClient::request()`, alongside
  `terminal_id`, so no action method or caller can omit it (API error 1011).

### Changed — renamed from `ghanem/bee`

- Package `ghanem/bee` → `ghanem/basata`; namespace `Ghanem\Bee\` →
  `Ghanem\Basata\`.
- `BeeService` → `BasataService`, `BeeServiceProvider` →
  `BasataServiceProvider`, facade `Bee` → `Basata`,
  `BeeWebhookController` → `BasataWebhookController`,
  `BeeWebhookReceived` → `BasataWebhookReceived`.
- `config/bee.php` / `config('bee.*')` → `config/basata.php` /
  `config('basata.*')`.
- Every `BEE_*` env var → `BASATA_*` (see README for the full table).
- Cache key prefix `bee_` → `basata_`; webhook path `bee/webhook` →
  `basata/webhook`; webhook signature header `X-Bee-Signature` →
  `X-Basata-Signature`.
- No backwards-compatibility aliases are provided for any old name — this is
  a deliberate migration, not an in-place upgrade.

### Kept as-is

Retry, logging, caching, rate limiting, webhooks, async jobs, batch
transactions and the DTO layer are unchanged in behaviour (aside from the
fixes above) — renamed, not redesigned.
