# Bee

A Laravel package that provides an interface to the Bee payment services API.

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12

## Installation

```bash
composer require ghanem/bee
```

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Ghanem\Bee\BeeServiceProvider" --tag="bee-config"
```

## Configuration

Add the following to your `.env` file:

```env
BEE_USERNAME=your-username
BEE_PASSWORD=your-password
BEE_URL=https://your-bee-api-url.com/
```

## Usage

You can use the `Bee` facade or resolve `BeeService` from the container.

### Service & Category Information

```php
use Ghanem\Bee\Facades\Bee;

// Get all categories
$categories = Bee::getCategoryList();

// Get category service list
$categoryServices = Bee::getCategoryServiceList();

// Get provider list by category
$providers = Bee::getProviderList(categoryId: 2);

// Get all services
$services = Bee::getServiceList();

// Get service input/output parameters
$inputParams = Bee::getServiceInputParameterList();
$outputParams = Bee::getServiceOutputParameterList();
```

### Transactions

```php
// Transaction inquiry
$inquiry = Bee::transactionInquiry([
    'account_number' => '12345',
    'service_id' => 10,
    'input_parameter_list' => [
        ['key' => 'phone', 'value' => '0912345678'],
    ],
]);

// Transaction payment
$payment = Bee::transactionPayment([
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
$transaction = Bee::getTransaction(123);

// Get transaction by external ID
$transaction = Bee::getTransaction('order-001', 'external_id');
```

### Account & Billing

```php
// Get account info
$account = Bee::getAccountInfo();

// Get bills amount (performs inquiry and returns amount)
$bills = Bee::getBillsAmount([
    'service_id' => 10,
    'account_number' => '12345',
]);
```

### Service Charge Calculation

```php
// Calculate service charge for an amount
$result = Bee::calculateServiceCharge([
    'service_id' => 10,
    'amount' => 100,
]);
// Returns: ['service_id' => 10, 'amount' => 100, 'service_charge' => 5, 'total_amount' => 105]

// Reverse calculate (from total amount back to base amount)
$result = Bee::calculateServiceChargeReverse([
    'service_id' => 10,
    'amount' => 105, // total amount including charge
]);
// Returns: ['service_id' => 10, 'amount' => 95.45, 'service_charge' => 9.55, 'total_amount' => 105]
```

### Language Support

Most methods accept a language parameter (defaults to `'en'`):

```php
$categories = Bee::getCategoryList('ar');
$services = Bee::getServiceList('ar');
```

## Testing

```bash
composer test
```

## Sponsor

[Become a Sponsor](https://github.com/sponsors/AbdullahGhanem)

## License

MIT
