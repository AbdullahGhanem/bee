<?php

namespace Ghanem\Bee\Facades;

use Ghanem\Bee\BeeService;
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
