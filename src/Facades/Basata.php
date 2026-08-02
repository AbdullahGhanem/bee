<?php

namespace Ghanem\Basata\Facades;

use Ghanem\Basata\BasataService;
use Ghanem\Basata\DTOs\ApiResponse;
use Ghanem\Basata\DTOs\ServiceChargeResult;
use Ghanem\Basata\DTOs\TransactionResult;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Illuminate\Support\Collection|array getCategoryList(?string $lang = null)
 * @method static \Illuminate\Support\Collection|array getCategoryServiceList(?string $lang = null)
 * @method static \Illuminate\Support\Collection|array getProviderList(int $categoryId = 2, ?string $lang = null)
 * @method static \Illuminate\Support\Collection|array getServiceList(?string $lang = null)
 * @method static \Illuminate\Support\Collection|array getServiceInputParameterList(?string $lang = null)
 * @method static \Illuminate\Support\Collection|array getServiceOutputParameterList(?string $lang = null)
 * @method static \Illuminate\Support\Collection|array getTransaction(int|string $id, string $type = 'id', ?string $lang = null)
 * @method static \Illuminate\Support\Collection|array getAccountInfo(?string $lang = null)
 * @method static \Illuminate\Support\Collection|array transactionInquiry(array $data, ?string $lang = null)
 * @method static \Illuminate\Support\Collection|array transactionPayment(array $data, ?string $lang = null)
 * @method static array calculateServiceCharge(array $data)
 * @method static array calculateServiceChargeReverse(array $data)
 * @method static array getBillsAmount(array $data)
 * @method static ApiResponse getCategoryListDto(?string $lang = null)
 * @method static ApiResponse getServiceListDto(?string $lang = null)
 * @method static TransactionResult getTransactionDto(int|string $id, string $type = 'id', ?string $lang = null)
 * @method static TransactionResult transactionInquiryDto(array $data, ?string $lang = null)
 * @method static TransactionResult transactionPaymentDto(array $data, ?string $lang = null)
 * @method static ServiceChargeResult calculateServiceChargeDto(array $data)
 * @method static ServiceChargeResult calculateServiceChargeReverseDto(array $data)
 * @method static void transactionPaymentAsync(array $data, ?string $lang = null)
 * @method static Batch batchTransactions(array $transactions, ?string $callbackEvent = null)
 * @method static void clearCache(?string $key = null)
 *
 * @see \Ghanem\Basata\BasataService
 */
class Basata extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'ghanem-basata';
    }
}
