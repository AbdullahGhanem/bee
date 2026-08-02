# Basata

[![Latest Stable Version](https://poser.pugx.org/ghanem/basata/v/stable.svg)](https://packagist.org/packages/ghanem/basata) [![License](https://poser.pugx.org/ghanem/basata/license.svg)](https://packagist.org/packages/ghanem/basata) [![Total Downloads](https://poser.pugx.org/ghanem/basata/downloads.svg)](https://packagist.org/packages/ghanem/basata)

A Laravel package that provides an interface to the Basata payment services API.

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12

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
```

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
// Transaction inquiry
$inquiry = Basata::transactionInquiry([
    'account_number' => '12345',
    'service_id' => 10,
    'input_parameter_list' => [
        ['key' => 'phone', 'value' => '0912345678'],
    ],
]);

// Transaction payment
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

### Account & Billing

```php
// Get account info
$account = Basata::getAccountInfo();

// Get bills amount (performs inquiry and returns amount)
$bills = Basata::getBillsAmount([
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

### Language Support

Most methods accept a language parameter (defaults to `'en'`):

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
$tx->transactionId; // ?int
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

## Changelog

### Done

- [x] Basata API integration (services, categories, providers)
- [x] Transaction inquiry and payment
- [x] Service charge calculation (forward and reverse)
- [x] Bills amount retrieval
- [x] Account info
- [x] Transaction lookup by ID and external ID
- [x] Multi-language support (en, ar, etc.)
- [x] Facade with full IDE autocompletion
- [x] Laravel 10, 11, and 12 support
- [x] PHP 8.1+ with modern type hints
- [x] Full test coverage (96 tests)
- [x] Retry mechanism for failed API requests
- [x] Request/response logging
- [x] Caching for service and category lists
- [x] Webhook support for transaction status updates
- [x] DTOs for API responses (ApiResponse, TransactionResult, ServiceChargeResult)
- [x] Rate limiting support
- [x] Async/queue support for batch transactions

## Testing

```bash
composer test
```

## Sponsor

[Become a Sponsor](https://github.com/sponsors/AbdullahGhanem)

## License

MIT
