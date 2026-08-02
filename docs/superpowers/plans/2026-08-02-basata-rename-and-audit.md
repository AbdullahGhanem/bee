# Basata Rename + Spec Audit — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn `ghanem/bee` into `ghanem/basata`: rename everything, fix the two critical spec-compliance bugs, add the missing action and error-code mapping, support Laravel 13, and audit all 12 actions against the v3.0.8 PDF.

**Architecture:** Same shape — `ApiClient` (HTTP + retry/cache/log/rate-limit), `BasataService` (public API + DTOs), service provider, facade, webhook controller, jobs. New: `src/Exceptions/` with an `ErrorCode` enum mapping the API's ~35 documented codes to typed exceptions, plus `TransactionStatus` and `OperationStatus` enums. Spec: `docs/superpowers/specs/2026-08-02-basata-rename-and-audit-design.md`.

**Tech Stack:** PHP ^8.1, Laravel ^10|^11|^12|^13, PHPUnit + Orchestra Testbench, `Http::fake()`.

## Global Constraints

- Package `ghanem/basata`, namespace `Ghanem\Basata\`. NO backwards-compat aliases for the old `Bee` names.
- **The API returns HTTP 200 for business failures.** Success is determined by the body's `success` flag / absence of an error code — never by HTTP status alone. This is the bug the release exists to fix; do not reintroduce it.
- `terminal_id` comes from config, never a hardcoded literal.
- `language` is a REQUIRED top-level request field on every action (API error 1011), even though the spec's parameter table omits it. Every sample in the PDF includes it.
- All 12 actions use `version: 2`. Paths are `/service`, `/transaction`, `/report` — verify each against the PDF, do not guess.
- Tests use `Http::fake()`. Never make a live API call.
- Commit messages: plain conventional style, NO `Co-Authored-By` line.
- Run `vendor/bin/phpunit` after every implementation step; never commit with failing tests. Baseline is 96 passing.
- The PDF is the authority: `/Users/abdullah/Downloads/Channel API V3.0.8_Basata (1).pdf`. When code and plan disagree with it, the PDF wins — report the discrepancy.

---

### Task 1: Error codes, enums and typed exceptions

Build the error layer first — later tasks depend on it. Nothing is renamed yet.

**Files:**
- Create: `src/Enums/ErrorCode.php`, `src/Enums/TransactionStatus.php`, `src/Enums/OperationStatus.php`
- Create: `src/Exceptions/BeeException.php` and the seven subclasses (renamed in Task 2)
- Test: `tests/Unit/ErrorCodeTest.php`, `tests/Unit/ExceptionMappingTest.php`

**Interfaces:**
- Produces: `ErrorCode` backed int enum with `message(): string` and `exceptionClass(): string`; `ErrorCode::tryFromCode(int): ?self`. `TransactionStatus` backed string enum with `isFinal(): bool`. `OperationStatus` backed string enum (`SUCCESS`, `FAIL`). Exception base carrying `apiCode`, `apiMessage`, `payload`.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/ErrorCodeTest.php` — assert a representative sample of codes map to the right message and exception class, and that an undocumented code falls back to the server exception:

```php
public function test_documented_codes_map_to_messages(): void
{
    $this->assertSame('Incorrect login or password', ErrorCode::IncorrectCredentials->message());
    $this->assertSame('Insufficient balance', ErrorCode::InsufficientBalance->message());
    $this->assertSame('Rate limit exceeded', ErrorCode::RateLimitExceeded->message());
    $this->assertSame('Transaction is in progress, please try again later', ErrorCode::TransactionInProgress->message());
}

public function test_codes_map_to_exception_classes(): void
{
    $this->assertSame(BeeInsufficientBalanceException::class, ErrorCode::InsufficientBalance->exceptionClass());
    $this->assertSame(BeeRateLimitException::class, ErrorCode::RateLimitExceeded->exceptionClass());
    $this->assertSame(BeeAuthenticationException::class, ErrorCode::IncorrectCredentials->exceptionClass());
}

public function test_unknown_code_is_not_swallowed(): void
{
    $this->assertNull(ErrorCode::tryFromCode(9999));
}
```

`tests/Unit/ExceptionMappingTest.php` — assert `TransactionStatus::isFinal()` matches FAQ Q9 exactly:

```php
public function test_only_terminal_statuses_are_final(): void
{
    $this->assertFalse(TransactionStatus::New->isFinal());
    $this->assertFalse(TransactionStatus::InProgress->isFinal());
    $this->assertTrue(TransactionStatus::Success->isFinal());
    $this->assertTrue(TransactionStatus::Error->isFinal());
    $this->assertTrue(TransactionStatus::DepositError->isFinal());
}
```

Note: the PDF's FAQ Q9 table lists five statuses and omits `CANCELLED`, while object 4.9 lists six. Treat `CANCELLED` as **final** (a cancelled transaction will not progress) and record that judgement in a code comment — the spec is silent, so the decision must be visible.

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit --filter ErrorCodeTest`
Expected: FAIL — classes not found.

- [ ] **Step 3: Implement `src/Enums/ErrorCode.php`**

Transcribe **every** code from PDF section 6 (page 18). The full list:

```
1001 «login» is required                          1019 Service has not inquiry feature
1002 «password» is required                       1020 Inquiry transaction ID is required
1003 Incorrect login or password                  1021 Inquiry transaction not found
1004 «action» is required                         1022 Wrong service charge
1005 Incorrect action name                        1023 Duplicate transaction ID
1006 «version» is required                        1024 «Terminal_id» is required
1007 Incorrect version                            1025 Incorrect service version, service list update is required
1008 «data» is required                           1026 Transaction not found
1009 «data» is invalid                            1027 Beecard not found
1010 Invalid user                                 1028 Beecard is used
1011 «language» is required                       1029 Beecard is expired
1012 Change password is required                  1033 Rate limit exceeded
1013 Permission denied                            1034 Transaction is in progress, please try again later
1014 Account number not found                     2000 Internal server error
1015 Receiver account number not found            20000 Ambiguous Server Error
1016 Insufficient balance                         2001 Invalid HTTP content type
1017 Wrong amount                                 2002 Invalid HTTP charset
1018 Unknown service                              2003 Invalid HTTP content
                                                  2004 Unsupported HTTP method
                                                  2005 Invalid URL path
```

Give each case a descriptive name (e.g. `IncorrectCredentials = 1003`,
`InsufficientBalance = 1016`, `RateLimitExceeded = 1033`,
`TransactionInProgress = 1034`, `AmbiguousServerError = 20000`).
`message()` returns the exact wording above. `exceptionClass()` maps per the
groups in the design spec.

- [ ] **Step 4: Implement the enums and exceptions**

`TransactionStatus`: string-backed — `New = 'NEW'`, `InProgress = 'IN_PROGRESS'`, `Success = 'SUCCESS'`, `Error = 'ERROR'`, `DepositError = 'DEPOSIT_ERROR'`, `Cancelled = 'CANCELLED'`. `isFinal()` true for Success/Error/DepositError/Cancelled.

`OperationStatus`: `Success = 'SUCCESS'`, `Fail = 'FAIL'`.

Exceptions all extend a base holding the API code, message and raw payload:

```php
class BeeException extends \RuntimeException
{
    public function __construct(
        public readonly ?int $apiCode,
        string $message,
        public readonly array $payload = [],
    ) {
        parent::__construct($message, $apiCode ?? 0);
    }

    public static function fromCode(?int $code, array $payload = []): static
    {
        $enum = $code === null ? null : ErrorCode::tryFromCode($code);
        $class = $enum?->exceptionClass() ?? BeeServerException::class;

        return new $class($code, $enum?->message() ?? 'Unknown API error', $payload);
    }
}
```

- [ ] **Step 5: Run the full suite**

Run: `vendor/bin/phpunit`
Expected: 96 baseline + new tests, all PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Enums src/Exceptions tests/Unit/ErrorCodeTest.php tests/Unit/ExceptionMappingTest.php
git commit -m "feat: error code enum, transaction status enum and typed exceptions"
```

---

### Task 2: Fix the two critical client bugs

**Files:**
- Modify: `src/ApiClient.php`, `config/bee.php`
- Test: `tests/Unit/ErrorHandlingTest.php`, `tests/Unit/TerminalIdTest.php`

**Interfaces:**
- Consumes: `ErrorCode`, exceptions (Task 1).
- Produces: `ApiClient::request()` that inspects the response BODY for success, throws mapped exceptions when `bee.errors.throw` is true (default), and reads `terminal_id` from config.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/ErrorHandlingTest.php` — this is the regression test for the headline bug:

```php
public function test_a_200_response_with_success_false_is_not_treated_as_success(): void
{
    Http::fake(['*' => Http::response([
        'success' => false,
        'code' => 1016,
        'message' => 'Insufficient balance',
    ], 200)]);

    $this->expectException(BeeInsufficientBalanceException::class);

    app(ApiClient::class)->getAccountInfo();
}

public function test_the_thrown_exception_carries_the_api_code_and_payload(): void
{
    Http::fake(['*' => Http::response(['success' => false, 'code' => 1034], 200)]);

    try {
        app(ApiClient::class)->getAccountInfo();
        $this->fail('Expected exception');
    } catch (BeeTransactionInProgressException $e) {
        $this->assertSame(1034, $e->apiCode);
        $this->assertArrayHasKey('code', $e->payload);
    }
}

public function test_an_undocumented_code_raises_the_server_exception(): void
{
    Http::fake(['*' => Http::response(['success' => false, 'code' => 9999], 200)]);

    $this->expectException(BeeServerException::class);

    app(ApiClient::class)->getAccountInfo();
}

public function test_throwing_can_be_disabled(): void
{
    config()->set('bee.errors.throw', false);
    Http::fake(['*' => Http::response(['success' => false, 'code' => 1016], 200)]);

    $result = app(ApiClient::class)->getAccountInfo();

    $this->assertSame(1016, $result['code']);
}

public function test_a_successful_response_still_returns_data(): void
{
    Http::fake(['*' => Http::response(['success' => true, 'data' => ['account_list' => []]], 200)]);

    $result = app(ApiClient::class)->getAccountInfo();

    $this->assertTrue($result['success']);
}
```

`tests/Unit/TerminalIdTest.php`:

```php
public function test_terminal_id_comes_from_config_not_a_hardcoded_value(): void
{
    config()->set('bee.terminal_id', '9876543210');
    Http::fake(['*' => Http::response(['success' => true, 'data' => []], 200)]);

    app(ApiClient::class)->getProviderList();

    Http::assertSent(fn ($request) => $request['terminal_id'] === '9876543210');
}

public function test_every_action_sends_the_configured_terminal_id(): void
{
    config()->set('bee.terminal_id', 'T-42');
    Http::fake(['*' => Http::response(['success' => true, 'data' => []], 200)]);

    $client = app(ApiClient::class);
    $client->getProviderList();
    $client->getServiceList();
    $client->getCategoryList();

    Http::assertSentCount(3);
    Http::assertSent(fn ($request) => $request['terminal_id'] === 'T-42');
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit --filter 'ErrorHandlingTest|TerminalIdTest'`
Expected: FAIL — a `success:false` body currently returns normally, and `terminal_id` is `'1'`.

- [ ] **Step 3: Add config keys**

In `config/bee.php` add:

```php
    'terminal_id' => env('BEE_TERMINAL_ID', ''),

    'language' => env('BEE_LANGUAGE', 'en'),

    'errors' => [
        // The API returns HTTP 200 for business failures, so success is read
        // from the response body. Set false to receive the raw payload instead
        // of a thrown exception.
        'throw' => env('BEE_ERRORS_THROW', true),
    ],
```

- [ ] **Step 4: Rewrite the response handling in `ApiClient::request()`**

Replace the `if ($response->ok())` block. The rules, from the PDF:
- A transport-level failure (non-200) is still an error — keep that path.
- On 200, inspect the body: if `success` is explicitly `false`, OR an error
  `code` is present and is not a success code, it is a failure.
- Determine the code from whichever field the API actually uses — check `code`,
  `error_code`, and `status_code` in the payload; if the shape differs from this
  plan, follow the PDF/actual payload and report it.
- When `config('bee.errors.throw')` is true, `throw BeeException::fromCode($code, $payload)`.
  Otherwise return the payload unchanged so existing array call sites work.

Also replace the rate-limit magic array (`['error' => 'Rate limit exceeded', 'status_code' => 429]`)
with `throw new BeeRateLimitException(1033, ...)` when throwing is enabled, and
the same payload shape as other errors when it is not.

- [ ] **Step 5: Replace the hardcoded `terminal_id`**

Every action method currently passing `'terminal_id' => '1'` reads
`config('bee.terminal_id')` instead. Add an optional `?string $terminalId = null`
parameter where a per-call override is sensible. Do the same for `language`,
defaulting to `config('bee.language')`.

Do NOT silently default `terminal_id` to `'1'` or `''` — if it is empty, throw a
`BeeValidationException` with API code 1024 (`«Terminal_id» is required`), which
is exactly what the server would return.

- [ ] **Step 6: Run the full suite**

Run: `vendor/bin/phpunit`
Expected: all PASS. Existing tests that asserted the old success-on-200 behaviour
must be UPDATED to the correct behaviour, not deleted — if one now fails because
it encoded the bug, fix the test and note it in your report.

- [ ] **Step 7: Commit**

```bash
git add src/ApiClient.php config/bee.php tests/
git commit -m "fix: read success from the response body and take terminal_id from config"
```

---

### Task 3: Add `ConfirmPrepaidCardRecharge` and audit all 12 actions

**Files:**
- Modify: `src/ApiClient.php`, `src/BeeService.php`
- Test: `tests/Unit/ConfirmPrepaidCardRechargeTest.php`, `tests/Unit/ActionContractTest.php`

**Interfaces:**
- Produces: `ApiClient::confirmPrepaidCardRecharge(string $paymentTransactionId, OperationStatus $status, ?string $lang = null)` and the matching `BasataService` method.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/ConfirmPrepaidCardRechargeTest.php`:

```php
public function test_it_sends_the_documented_request(): void
{
    config()->set('bee.terminal_id', '1234567890');
    Http::fake(['*' => Http::response(['success' => true, 'data' => ['info' => 'ok']], 200)]);

    app(ApiClient::class)->confirmPrepaidCardRecharge('225615364271', OperationStatus::Success);

    Http::assertSent(function ($request) {
        return str_ends_with($request->url(), '/service')
            && $request['action'] === 'ConfirmPrepaidCardRecharge'
            && $request['version'] === 2
            && $request['data']['payment_transaction_id'] === '225615364271'
            && $request['data']['operation_status'] === 'SUCCESS';
    });
}

public function test_it_can_report_failure(): void
{
    Http::fake(['*' => Http::response(['success' => true, 'data' => ['info' => 'ok']], 200)]);

    app(ApiClient::class)->confirmPrepaidCardRecharge('1', OperationStatus::Fail);

    Http::assertSent(fn ($request) => $request['data']['operation_status'] === 'FAIL');
}
```

`tests/Unit/ActionContractTest.php` — **this is the audit**. One test per action
asserting the exact wire contract against the PDF. Use a data provider:

```php
public static function actions(): array
{
    return [
        // [method, args, expected path, expected action]
        'GetProviderList'               => ['getProviderList', [], 'service', 'GetProviderList'],
        'GetServiceList'                => ['getServiceList', [], 'service', 'GetServiceList'],
        'GetCategoryList'               => ['getCategoryList', [], 'service', 'GetCategoryList'],
        'GetCategoryServiceList'        => ['getCategoryServiceList', [], 'service', 'GetCategoryServiceList'],
        'GetServiceInputParameterList'  => ['getServiceInputParameterList', [], 'service', 'GetServiceInputParameterList'],
        'GetServiceOutputParameterList' => ['getServiceOutputParameterList', [], 'service', 'GetServiceOutputParameterList'],
        'GetAccountInfo'                => ['getAccountInfo', [], 'report', 'GetAccountInfo'],
        // …transaction and report actions with their real args
    ];
}

#[DataProvider('actions')]
public function test_action_matches_the_v3_0_8_contract(string $method, array $args, string $path, string $action): void
{
    config()->set('bee.terminal_id', '1234567890');
    Http::fake(['*' => Http::response(['success' => true, 'data' => []], 200)]);

    app(ApiClient::class)->{$method}(...$args);

    Http::assertSent(function ($request) use ($path, $action) {
        return str_ends_with($request->url(), '/'.$path)
            && $request['action'] === $action
            && $request['version'] === 2
            && $request['language'] === 'en'
            && $request['terminal_id'] === '1234567890'
            && array_key_exists('login', $request->data())
            && array_key_exists('password', $request->data());
    });
}
```

**Before writing the provider, re-read PDF sections 5.1–5.12 and confirm each
action's path and name.** `TransactionInquiry` and `TransactionPayment` are on
`/transaction`; `GetAccountInfo`, `GetTransactionDetails` and
`GetTransactionByExternalId` are on `/report`; the rest are on `/service`. If any
current implementation disagrees with the PDF, **fix the implementation** and
report it — that is what this task is for.

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit --filter 'ConfirmPrepaidCardRecharge|ActionContract'`
Expected: FAIL — method missing; contract mismatches surface here.

- [ ] **Step 3: Implement `confirmPrepaidCardRecharge()` on `ApiClient` and the service**

```php
public function confirmPrepaidCardRecharge(
    string $paymentTransactionId,
    OperationStatus $status,
    ?string $lang = null,
): Collection|array {
    return $this->request('service', [
        'action' => 'ConfirmPrepaidCardRecharge',
        'version' => 2,
        'data' => [
            'payment_transaction_id' => $paymentTransactionId,
            'operation_status' => $status->value,
        ],
    ], $lang);
}
```

(`terminal_id`, `language`, `login` and `password` are injected centrally by
`request()` after Task 2 — do not repeat them per action.)

- [ ] **Step 4: Fix every contract mismatch the audit surfaced**

Work through each failing data-provider row. For each fix, note in your report:
what the code did, what the PDF says, and what you changed it to.

- [ ] **Step 5: Run the full suite**

Run: `vendor/bin/phpunit`
Expected: all PASS.

- [ ] **Step 6: Commit**

```bash
git add src tests
git commit -m "feat: ConfirmPrepaidCardRecharge action; audit all 12 actions against the v3.0.8 spec"
```

---

### Task 4: Rename Bee → Basata

Do this AFTER the functional work, so the audit diffs stay readable.

**Files:** every file in `src/`, `config/`, `tests/`; `composer.json`; `README.md`

**Interfaces:**
- Produces: package `ghanem/basata`, namespace `Ghanem\Basata\`, per the design spec's rename map.

- [ ] **Step 1: Rename the namespace and classes**

Apply the rename map from the design spec:

| Old | New |
|---|---|
| `ghanem/bee` | `ghanem/basata` |
| `Ghanem\Bee\` | `Ghanem\Basata\` |
| `BeeService` | `BasataService` |
| `BeeServiceProvider` | `BasataServiceProvider` |
| `Facades\Bee`, alias `Bee` | `Facades\Basata`, alias `Basata` |
| `BeeWebhookController` | `BasataWebhookController` |
| `BeeWebhookReceived` | `BasataWebhookReceived` |
| `BeeException` + subclasses | `BasataException` + subclasses |
| `config/bee.php`, `config('bee.*')` | `config/basata.php`, `config('basata.*')` |
| `BEE_*` env vars | `BASATA_*` |
| cache prefix `bee_` | `basata_` |
| webhook path `bee/webhook` | `basata/webhook` |

Use `git mv` for file renames so history is preserved. Update `composer.json`
`name`, `description` (say Basata, not Bee), `keywords`, `homepage`, PSR-4 maps,
and the `extra.laravel` provider and alias entries.

Also: remove `minimum-stability: dev` and `prefer-stable` from composer.json —
Composer ignores them for installed dependencies and they are wrong on a library.

**No backwards-compatibility aliases.** This is a new package name.

- [ ] **Step 2: Verify nothing was missed**

Run: `grep -rni 'bee' src/ config/ tests/ composer.json | grep -v -i 'beecard'`
Expected: no output. Note that error codes 1027–1029 legitimately say
"Beecard" — that is the API's own wording for the physical card product and
must NOT be renamed. Keep those strings verbatim and say so in your report.

- [ ] **Step 3: Run the full suite**

Run: `vendor/bin/phpunit`
Expected: all PASS. `composer dump-autoload` first if class-not-found errors appear.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "refactor!: rename Bee to Basata across the package"
```

---

### Task 5: Laravel 13, CI and README

**Files:** `composer.json`, `.github/workflows/tests.yml`, `README.md`, `CHANGELOG.md`

- [ ] **Step 1: Widen the version constraints**

`illuminate/support` and `illuminate/http` → `^10.0|^11.0|^12.0|^13.0`.
`orchestra/testbench` → `^8.0|^9.0|^10.0|^11.0`. `phpunit/phpunit` → `^10.5|^11.0|^12.0`.

- [ ] **Step 2: Write the CI matrix**

Map Laravel to testbench in lockstep — **8→L10, 9→L11, 10→L12, 11→L13** — and give
every matrix row more than one distinguishing key (GitHub merges `include` rows
that differ in only one key, silently dropping a leg). Laravel 13 needs PHP 8.3+;
Laravel 11/12 need PHP 8.2+. Exclude invalid combinations.

Include `composer config policy.advisories.block false --no-interaction` before
install, with a comment explaining that Laravel 10/11 are past security support
so their releases are otherwise unselectable and Composer would silently fall
back to branch heads.

- [ ] **Step 3: Verify every matrix cell resolves for real**

For each combination, in a throwaway directory under the scratchpad (copy
`composer.json` only — never touch the repo's `vendor/`), run
`composer update --dry-run` and record the resolved `laravel/framework` and
`orchestra/testbench` versions. Any cell that cannot resolve goes in `exclude:`
with a comment. Do not ship an unverified matrix.

- [ ] **Step 4: Rewrite the README**

Must cover: install as `ghanem/basata`; the required `BASATA_TERMINAL_ID` config
(explain it is a unique per-terminal ID, FAQ Q3 — this was previously hardcoded);
all 13 actions including `confirmPrepaidCardRecharge`; the error model (200 can
mean failure; exceptions and the `basata.errors.throw` switch; a table of the
main codes); `TransactionStatus` and which statuses are final; and a
**Migrating from `ghanem/bee`** section giving the full rename map plus the
`BEE_*` → `BASATA_*` env changes.

Verify every documented method and config key against the actual source. The
code wins over this plan.

- [ ] **Step 5: Write the CHANGELOG**

A `3.0.0` entry. Lead with the two fixed bugs (success-on-200, hardcoded
`terminal_id`) since those change behaviour for every existing user, then the new
action, the error layer, Laravel 13, and the rename.

- [ ] **Step 6: Run the full suite and commit**

```bash
vendor/bin/phpunit
git add -A
git commit -m "feat: Laravel 13 support, CI matrix and rewritten docs for Basata"
```

---

## Notes for the executor

- The PDF is the authority. Read the relevant section before implementing each
  action rather than trusting this plan's transcription.
- "Beecard" in error codes 1027–1029 is the API's own product name — do not
  rename it to Basatacard.
- The API's exact error-payload shape (`code` vs `error_code`, nesting) is not
  fully pinned down by the PDF. Inspect what the existing code and any sample
  responses suggest, implement defensively (check several field names), and
  report what you found rather than guessing silently.
- Do NOT create a GitHub repo, add a remote, push, or publish to Packagist. The
  controller handles release.
