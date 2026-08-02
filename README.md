# Basata

[![Latest Stable Version](https://poser.pugx.org/ghanem/basata/v/stable.svg)](https://packagist.org/packages/ghanem/basata) [![License](https://poser.pugx.org/ghanem/basata/license.svg)](https://packagist.org/packages/ghanem/basata) [![Total Downloads](https://poser.pugx.org/ghanem/basata/downloads.svg)](https://packagist.org/packages/ghanem/basata)

A Laravel package that provides an interface to the Basata *Cash Collector Channel API* (spec v3.0.8) payment services.

## Requirements

- PHP 8.1+
- Laravel 10, 11, 12, or 13

## Installation

```bash
composer require ghanem/basata
```

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Ghanem\Basata\BasataServiceProvider" --tag="basata-config"
```

## Configuration

Add the following to your `.env` file:

```env
BASATA_USERNAME=your-username
BASATA_PASSWORD=your-password
BASATA_URL=https://your-basata-api-url.com/
BASATA_TERMINAL_ID=your-terminal-id
```

`BASATA_TERMINAL_ID` is **required**. The Basata API requires a unique
External Terminal ID per terminal (spec FAQ Q3) — it identifies which
physical/logical terminal is making the request, not a login credential. If
it is empty, every request throws immediately with API error code `1024`
(`TerminalIdRequired`) rather than being silently sent without one.

> **Coming from `ghanem/bee`?** This field used to be hardcoded to the
> literal string `'1'` for every request, for every installation. That was a
> bug — see [Migrating from `ghanem/bee`](#migrating-from-ghanembee) below.

## Usage

You can use the `Basata` facade or resolve `BasataService` from the container.

### Service & Category Information

```php
use Ghanem\Basata\Facades\Basata;

// Get all categories
$categories = Basata::getCategoryList();

// Get category service list
$categoryServices = Basata::getCategoryServiceList();

// Get provider list by category
$providers = Basata::getProviderList(categoryId: 2);

// Get all services
$services = Basata::getServiceList();

// Get service input/output parameters
$inputParams = Basata::getServiceInputParameterList();
$outputParams = Basata::getServiceOutputParameterList();
```

### Transactions

```php
// Transaction inquiry — account_number and service_id are required (plus
// service_version, auto-filled below); a missing one throws
// BasataValidationException (code 1008) instead of silently defaulting.
$inquiry = Basata::transactionInquiry([
    'account_number' => '12345',
    'service_id' => 10,
    'input_parameter_list' => [
        ['key' => 'phone', 'value' => '0912345678'],
    ],
]);

// Transaction payment — account_number, service_id, external_id, amount,
// total_amount and quantity are all required (plus service_version,
// auto-filled below).
$payment = Basata::transactionPayment([
    'account_number' => '12345',
    'service_id' => 10,
    'external_id' => 'order-001',
    'amount' => 100,
    'service_charge' => 5,
    'total_amount' => 105,
    'quantity' => 1,
    'inquiry_transaction_id' => $inquiry['data']['transaction_id'],
    'input_parameter_list' => [],
]);

// Get transaction details by ID
$transaction = Basata::getTransaction(123);

// Get transaction by external ID
$transaction = Basata::getTransaction('order-001', 'external_id');
```

`Basata::transactionInquiry()`/`Basata::transactionPayment()` fill in
`service_version` automatically from `getProviderList()` before sending the
request, so you don't need to pass it yourself. This auto-fill only happens
through the facade/`BasataService` — `getBillsAmount()` and any direct
`ApiClient` usage do not get it and must supply `service_version` explicitly.

### Prepaid Card Recharge Confirmation

Per spec 5.9, a successful `transactionPayment()` for a service whose input
parameters include a `card_data` record must be confirmed afterwards:

```php
use Ghanem\Basata\Enums\OperationStatus;

Basata::confirmPrepaidCardRecharge(
    paymentTransactionId: $payment['data']['transaction_id'],
    status: OperationStatus::Success, // or OperationStatus::Fail
);
```

### Account & Billing

```php
// Get account info
$account = Basata::getAccountInfo();

// Get bills amount (performs an inquiry and returns the amount).
// Unlike transactionInquiry(), getBillsAmount() does NOT auto-fill
// service_version from getProviderList() — pass it yourself, or it throws
// BasataValidationException (code 1008).
$bills = Basata::getBillsAmount([
    'service_version' => 0,
    'service_id' => 10,
    'account_number' => '12345',
]);
```

### Service Charge Calculation

```php
// Calculate service charge for an amount
$result = Basata::calculateServiceCharge([
    'service_id' => 10,
    'amount' => 100,
]);
// Returns: ['service_id' => 10, 'amount' => 100, 'service_charge' => 5, 'total_amount' => 105]

// Reverse calculate (from total amount back to base amount)
$result = Basata::calculateServiceChargeReverse([
    'service_id' => 10,
    'amount' => 105, // total amount including charge
]);
// Returns: ['service_id' => 10, 'amount' => 95.45, 'service_charge' => 9.55, 'total_amount' => 105]
```

Both are client-side calculations over the service's `service_charge_list`
(from the cached `getServiceList()`), and both fail loudly rather than
guessing:

- an unknown `service_id` throws `BasataNotFoundException` (code 1018);
- an amount outside every charge band throws `BasataValidationException`
  (code 1022) instead of returning a zero charge that would then be posted.

`calculateServiceChargeReverse()` honours the band's `percentage` flag: a
percentage charge is extracted out of the total, a fixed charge is subtracted
from it. The band itself is matched on the resulting *net* amount, and
`total_amount` always round-trips back to the total you passed in.

### All actions at a glance

| Method | Maps to API action |
|---|---|
| `getCategoryList()` | `GetCategoryList` |
| `getCategoryServiceList()` | `GetCategoryServiceList` |
| `getProviderList()` | `GetProviderList` |
| `getServiceList()` | `GetServiceList` |
| `getServiceInputParameterList()` | `GetServiceInputParameterList` |
| `getServiceOutputParameterList()` | `GetServiceOutputParameterList` |
| `getTransaction($id, 'id')` | `GetTransactionDetails` |
| `getTransaction($id, 'external_id')` | `GetTransactionByExternalId` |
| `getAccountInfo()` | `GetAccountInfo` |
| `transactionInquiry()` | `TransactionInquiry` |
| `transactionPayment()` | `TransactionPayment` |
| `confirmPrepaidCardRecharge()` | `ConfirmPrepaidCardRecharge` |
| `calculateServiceCharge()` / `calculateServiceChargeReverse()` | client-side calculation built on `getServiceList()` — not a separate API action |
| `getBillsAmount()` | client-side helper built on `transactionInquiry()` — not a separate API action |

`confirmPrepaidCardRecharge()` is new in this release; every other method
existed already and was re-verified field-by-field against the v3.0.8 spec.

### Language Support

Most methods accept a language parameter (defaults to `'en'`, or
`BASATA_LANGUAGE` if set):

```php
$categories = Basata::getCategoryList('ar');
$services = Basata::getServiceList('ar');
```

### DTOs (Typed Responses)

Use `*Dto` methods for typed response objects instead of raw arrays/collections:

```php
use Ghanem\Basata\DTOs\ApiResponse;
use Ghanem\Basata\DTOs\TransactionResult;
use Ghanem\Basata\DTOs\ServiceChargeResult;

// API response DTO
$response = Basata::getCategoryListDto(); // returns ApiResponse
$response->success;    // bool
$response->data;       // array
$response->statusCode; // int
$response->get('categories.0.name'); // dot notation access

// Transaction DTO
$tx = Basata::getTransactionDto(123); // returns TransactionResult
$tx->transactionId; // int|string|null — the spec types transaction_id as a
                    // String, so it is passed through verbatim (never cast)
$tx->amount;        // ?float
$tx->serviceCharge; // ?float
$tx->totalAmount;   // ?float

$inquiry = Basata::transactionInquiryDto($data);  // TransactionResult
$payment = Basata::transactionPaymentDto($data);  // TransactionResult

// Service charge DTO
$charge = Basata::calculateServiceChargeDto([
    'service_id' => 10,
    'amount' => 100,
]); // returns ServiceChargeResult
$charge->serviceId;     // int
$charge->amount;        // float
$charge->serviceCharge; // float
$charge->totalAmount;   // float
```

## Error Handling

The Basata API returns **HTTP 200 even for a business failure** — for
example insufficient balance or a transaction already in progress. Success is
never inferred from the HTTP status; it's read from the response body
(`"success": true`). Anything else — `"success": false`, a missing `success`
key, an empty body, a scalar body, or a non-JSON body — is treated as a
failure.

**Exactly one thing means success: an HTTP 2xx whose body says
`"success": true`.** Everything else — a business failure, *and* a transport
or server failure (any non-2xx: 401, 404, 502, 504, …) — goes through the same
error layer and obeys the same `basata.errors.throw` setting. There is no path
where a 502 quietly returns an array that reads like a response, so
`$payment['data']['transaction_id']` can never be silently `null` because the
gateway died — which matters most on `transactionPayment()`, where a 5xx is
exactly the case where the payment may already have executed.

For a non-2xx the exception's `apiCode` is the API's own error code when the
body carries one, and otherwise the HTTP status (e.g. `502`); a bare status
matches no documented code, so it surfaces as `BasataServerException`. The
`payload` always includes `status_code`, `link`, and the request `params`
**with `login`/`password` stripped**.

By default, a failure throws a typed exception carrying the error code,
message, and full payload:

```php
use Ghanem\Basata\Exceptions\BasataException;
use Ghanem\Basata\Exceptions\BasataInsufficientBalanceException;

try {
    Basata::transactionPayment($data);
} catch (BasataInsufficientBalanceException $e) {
    // $e->apiCode  — int, e.g. 1016
    // $e->getMessage() — the API's message text
    // $e->payload  — array, the raw response body
} catch (BasataException $e) {
    // catches every Basata exception — they all extend this base class
}
```

Set `basata.errors.throw` to `false` (env `BASATA_ERRORS_THROW=false`) to get
the raw response payload back instead of an exception — useful for call
sites written against the old array-return contract:

```env
BASATA_ERRORS_THROW=false
```

`basata.errors.throw` governs how a *failed request* is handled — a business
failure in the body, a non-2xx transport/server failure, or the client-side
rate limiter. It does
**not** cover pre-flight validation that runs before a request is ever sent —
a missing `BASATA_TERMINAL_ID` (code 1024) or a missing required field on
`transactionInquiry()`/`transactionPayment()` (code 1008/1017) always throws,
regardless of this setting, because there is no API response to fall back
to.

### Exception classes

Every documented error code (spec section 6) maps to one of these, via
`Ghanem\Basata\Enums\ErrorCode::exceptionClass()`. An undocumented/unknown
code falls back to `BasataServerException` rather than being swallowed.

| Exception | Example codes |
|---|---|
| `BasataAuthenticationException` | 1001 login required, 1002 password required, 1003 incorrect credentials, 1010 invalid user, 1012 change password required, 1013 permission denied |
| `BasataValidationException` | 1004–1009, 1011 (language required), 1017 (wrong amount), 1020, 1022, 1024 (**terminal_id required**), 1025, 1023, 1019, 1028, 1029, 2001–2005 |
| `BasataInsufficientBalanceException` | 1016 |
| `BasataRateLimitException` | 1033 |
| `BasataTransactionInProgressException` | 1034 |
| `BasataNotFoundException` | 1014 account not found, 1015 receiver account not found, 1018 unknown service, 1021 inquiry transaction not found, 1026 transaction not found, 1027 Beecard not found |
| `BasataServerException` | 2000, 20000, and any code not in the table above |

> Codes 1027–1029 refer to "Beecard" — the API's own product name for a
> physical prepaid card. That naming is kept verbatim rather than renamed to
> "Basatacard".

### Main error codes

| Code | Name | Meaning |
|---|---|---|
| 1008 | DataRequired | A required `data` field is missing (used by the client-side validation on `transactionInquiry`/`transactionPayment`) |
| 1011 | LanguageRequired | `language` was not sent — this package always sends it |
| 1016 | InsufficientBalance | Terminal balance too low for the transaction |
| 1017 | WrongAmount | `amount`/`total_amount` missing or invalid |
| 1022 | WrongServiceCharge | Submitted `service_charge` doesn't match the server's calculation |
| 1023 | DuplicateTransactionId | `external_id` was already used |
| 1024 | TerminalIdRequired | `terminal_id` missing — thrown client-side before the request is even sent if `BASATA_TERMINAL_ID` is unset |
| 1026 | TransactionNotFound | No transaction matches the given ID |
| 1033 | RateLimitExceeded | Client-side rate limiter tripped (see [Rate Limiting](#rate-limiting)) |
| 1034 | TransactionInProgress | The transaction is still processing; retry the inquiry later |
| 2000 / 20000 | InternalServerError / AmbiguousServerError | Basata-side failure |

See `Ghanem\Basata\Enums\ErrorCode` for the full list of ~35 codes and their
exact spec wording.

## Transaction Status

`Ghanem\Basata\Enums\TransactionStatus` models the transaction lifecycle:

```php
use Ghanem\Basata\Enums\TransactionStatus;

$status = TransactionStatus::from($transaction['data']['status']);

if ($status->isFinal()) {
    // stop polling
}
```

| Status | Final? |
|---|---|
| `NEW` | No |
| `IN_PROGRESS` | No |
| `SUCCESS` | **Yes** |
| `ERROR` | **Yes** |
| `DEPOSIT_ERROR` | **Yes** |
| `CANCELLED` | Yes (not enumerated in the spec's finality table, but a cancelled transaction will not progress further) |

Use `isFinal()` to decide whether to keep polling `getTransaction()` for a
pending transaction.

### Retry Mechanism

Failed API requests are automatically retried with exponential backoff:

```env
BASATA_RETRY_TRIES=3       # Number of retry attempts
BASATA_RETRY_DELAY=100     # Initial delay in milliseconds
BASATA_RETRY_MULTIPLIER=2  # Backoff multiplier
```

### Request/Response Logging

Enable logging to debug API calls. Credentials are automatically redacted:

```env
BASATA_LOG_ENABLED=true
BASATA_LOG_CHANNEL=stack   # Optional: specific log channel
```

### Caching

Service and category lists are automatically cached to reduce API calls:

```env
BASATA_CACHE_ENABLED=true    # Enabled by default
BASATA_CACHE_TTL=3600        # Cache lifetime in seconds
BASATA_CACHE_STORE=redis     # Optional: specific cache store
```

```php
// Clear all cached data
Basata::clearCache();

// Clear specific cache key
Basata::clearCache('category_list_en');
```

### Rate Limiting

Limit the number of API requests per minute:

```env
BASATA_RATE_LIMIT_ENABLED=true
BASATA_RATE_LIMIT_MAX=60      # Max requests per minute
```

When the limit is hit, the request throws `BasataRateLimitException` (code
1033) instead of hitting the network — unless `basata.errors.throw` is
`false`, in which case the same shape payload is returned as an array.

### Webhooks

Receive transaction status updates via webhooks:

```env
BASATA_WEBHOOK_ENABLED=true
BASATA_WEBHOOK_PATH=basata/webhook
BASATA_WEBHOOK_SECRET=your-secret  # Optional: signature validation
```

Listen for webhook events in your application:

```php
use Ghanem\Basata\Events\BasataWebhookReceived;
use Ghanem\Basata\Events\TransactionStatusUpdated;

// Listen to all webhook events
Event::listen(BasataWebhookReceived::class, function ($event) {
    // $event->event   - event name (e.g. 'transaction.completed')
    // $event->payload - full webhook payload
});

// Listen specifically to transaction status changes
Event::listen(TransactionStatusUpdated::class, function ($event) {
    // $event->transactionId
    // $event->status
    // $event->payload
});
```

### Async / Queue Support

Process transactions asynchronously using Laravel queues:

```env
BASATA_QUEUE_CONNECTION=redis   # Optional: queue connection
BASATA_QUEUE_NAME=payments      # Optional: queue name
```

```php
// Dispatch a single payment to the queue
Basata::transactionPaymentAsync([
    'account_number' => '12345',
    'service_id' => 10,
    'amount' => 100,
]);

// Batch multiple transactions
$batch = Basata::batchTransactions([
    ['action' => 'payment', 'data' => ['service_id' => 10, 'amount' => 100]],
    ['action' => 'inquiry', 'data' => ['service_id' => 11, 'account_number' => '123']],
    ['action' => 'payment', 'data' => ['service_id' => 12, 'amount' => 200], 'lang' => 'ar'],
]);

// Batch with callback event
Basata::batchTransactions($transactions, App\Events\TransactionProcessed::class);
```

## Migrating from `ghanem/bee`

`ghanem/basata` is a republish, not a drop-in upgrade — Packagist names are
permanent, and this package renames every symbol to match the product's
actual name (the spec itself says "Bee" was only ever an internal codename).
There are no backwards-compatibility aliases. Update every reference below
deliberately.

### Rename map

| Old (`ghanem/bee`) | New (`ghanem/basata`) |
|---|---|
| `composer require ghanem/bee` | `composer require ghanem/basata` |
| `Ghanem\Bee\` | `Ghanem\Basata\` |
| `Ghanem\Bee\BeeService` | `Ghanem\Basata\BasataService` |
| `Ghanem\Bee\BeeServiceProvider` | `Ghanem\Basata\BasataServiceProvider` |
| `Ghanem\Bee\Facades\Bee` / `Bee::` | `Ghanem\Basata\Facades\Basata` / `Basata::` |
| `Ghanem\Bee\Http\BeeWebhookController` | `Ghanem\Basata\Http\BasataWebhookController` |
| `Ghanem\Bee\Events\BeeWebhookReceived` | `Ghanem\Basata\Events\BasataWebhookReceived` |
| `config/bee.php`, `config('bee.*')` | `config/basata.php`, `config('basata.*')` |
| Cache key prefix `bee_` | `basata_` |
| Webhook path `bee/webhook` | `basata/webhook` |
| Webhook signature header `X-Bee-Signature` | `X-Basata-Signature` |

### Environment variables

| Old | New |
|---|---|
| `BEE_USERNAME` | `BASATA_USERNAME` |
| `BEE_PASSWORD` | `BASATA_PASSWORD` |
| `BEE_URL` | `BASATA_URL` |
| `BEE_TERMINAL_ID` (config key existed but was ignored — see below) | `BASATA_TERMINAL_ID` **(now required and actually used)** |
| `BEE_LANGUAGE` | `BASATA_LANGUAGE` |
| `BEE_RETRY_TRIES` / `_DELAY` / `_MULTIPLIER` | `BASATA_RETRY_TRIES` / `_DELAY` / `_MULTIPLIER` |
| `BEE_LOG_ENABLED` / `_CHANNEL` | `BASATA_LOG_ENABLED` / `_CHANNEL` |
| `BEE_CACHE_ENABLED` / `_TTL` / `_STORE` | `BASATA_CACHE_ENABLED` / `_TTL` / `_STORE` |
| `BEE_RATE_LIMIT_ENABLED` / `_MAX` | `BASATA_RATE_LIMIT_ENABLED` / `_MAX` |
| `BEE_WEBHOOK_ENABLED` / `_PATH` / `_SECRET` | `BASATA_WEBHOOK_ENABLED` / `_PATH` / `_SECRET` |
| `BEE_QUEUE_CONNECTION` / `_NAME` | `BASATA_QUEUE_CONNECTION` / `_NAME` |
| — (did not exist) | `BASATA_ERRORS_THROW` **(new, default `true`)** |

### ⚠️ `terminal_id` is now required — read this before upgrading

`ghanem/bee` shipped a `BEE_TERMINAL_ID` config key, but the actual request
code never read it — every action method **hardcoded `terminal_id` to the
literal string `'1'`**, on every single request, for every installation,
regardless of what you set. That was a bug, not a default: the spec requires
a unique External Terminal ID per terminal (FAQ Q3), and sending `'1'` from
every installation is indistinguishable from not identifying your terminal
at all.

`ghanem/basata` removes the hardcoded value. You **must** set
`BASATA_TERMINAL_ID` in your `.env` to your actual terminal ID before
upgrading — if it is empty, every API call now throws
`BasataValidationException` (API code 1024) instead of silently sending `1`.

### ⚠️ Business failures now throw

`ghanem/bee` only checked the HTTP status code. A `200 OK` response with
`"success": false` in the body (e.g. insufficient balance, transaction in
progress) was returned to your code as if it had succeeded. Any code that
inspected `$result['success']` or relied on exceptions never being thrown
for these cases must be updated — see [Error Handling](#error-handling), or
set `BASATA_ERRORS_THROW=false` to keep the old array-return behavior while
you migrate call sites incrementally.

### ⚠️ Missing required transaction fields now throw

`ghanem/bee`'s `transactionInquiry()`/`transactionPayment()` silently
defaulted a missing `amount` to `1.5` and a missing `service_id` to `14` if
the caller forgot to pass them — meaning a bug in caller code could submit a
real 1.5 EGP payment against the wrong service instead of failing loudly.
`ghanem/basata` throws `BasataValidationException` for any missing required
field instead.

## Testing

```bash
composer test
```

## Sponsor

[Become a Sponsor](https://github.com/sponsors/AbdullahGhanem)

## License

MIT
