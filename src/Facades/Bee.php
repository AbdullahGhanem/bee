<?php

namespace Ghanem\Bee\Facades;

use Ghanem\Bee\BeeService;
use Ghanem\Bee\DTOs\ApiResponse;
use Ghanem\Bee\DTOs\ServiceChargeResult;
use Ghanem\Bee\DTOs\TransactionResult;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Illuminate\Support\Collection|array getCategoryList(string $lang = 'en')
 * @method static \Illuminate\Support\Collection|array getCategoryServiceList(string $lang = 'en')
 * @method static \Illuminate\Support\Collection|array getProviderList(int $categoryId = 2, string $lang = 'en')
 * @method static \Illuminate\Support\Collection|array getServiceList(string $lang = 'en')
 * @method static \Illuminate\Support\Collection|array getServiceInputParameterList(string $lang = 'en')
 * @method static \Illuminate\Support\Collection|array getServiceOutputParameterList(string $lang = 'en')
 * @method static \Illuminate\Support\Collection|array getTransaction(int|string $id, string $type = 'id', string $lang = 'en')
 * @method static \Illuminate\Support\Collection|array getAccountInfo(string $lang = 'en')
 * @method static \Illuminate\Support\Collection|array transactionInquiry(array $data, string $lang = 'en')
 * @method static \Illuminate\Support\Collection|array transactionPayment(array $data, string $lang = 'en')
 * @method static array calculateServiceCharge(array $data)
 * @method static array calculateServiceChargeReverse(array $data)
 * @method static array getBillsAmount(array $data)
 * @method static ApiResponse getCategoryListDto(string $lang = 'en')
 * @method static ApiResponse getServiceListDto(string $lang = 'en')
 * @method static TransactionResult getTransactionDto(int|string $id, string $type = 'id', string $lang = 'en')
 * @method static TransactionResult transactionInquiryDto(array $data, string $lang = 'en')
 * @method static TransactionResult transactionPaymentDto(array $data, string $lang = 'en')
 * @method static ServiceChargeResult calculateServiceChargeDto(array $data)
 * @method static ServiceChargeResult calculateServiceChargeReverseDto(array $data)
 * @method static void transactionPaymentAsync(array $data, string $lang = 'en')
 * @method static Batch batchTransactions(array $transactions, ?string $callbackEvent = null)
 * @method static void clearCache(?string $key = null)
 *
 * @see \Ghanem\Bee\BeeService
 */
class Bee extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'ghanem-bee';
    }
}
