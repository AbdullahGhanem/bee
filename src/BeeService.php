<?php

namespace Ghanem\Bee;

use Illuminate\Support\Collection;

class BeeService
{
    public function __construct(
        protected ApiClient $client = new ApiClient()
    ) {}

    public function getCategoryList(string $lang = 'en'): Collection|array
    {
        return $this->client->getCategoryList($lang);
    }

    public function getCategoryServiceList(string $lang = 'en'): Collection|array
    {
        return $this->client->getCategoryServiceList($lang);
    }

    public function getProviderList(int $categoryId = 2, string $lang = 'en'): Collection|array
    {
        return $this->client->getProviderList($categoryId, $lang);
    }

    public function getServiceList(string $lang = 'en'): Collection|array
    {
        return $this->client->getServiceList($lang);
    }

    public function getServiceInputParameterList(string $lang = 'en'): Collection|array
    {
        return $this->client->getServiceInputParameterList($lang);
    }

    public function getServiceOutputParameterList(string $lang = 'en'): Collection|array
    {
        return $this->client->getServiceOutputParameterList($lang);
    }

    public function getTransaction(int|string $id, string $type = 'id', string $lang = 'en'): Collection|array
    {
        return $this->client->getTransaction($id, $type, $lang);
    }

    public function getAccountInfo(string $lang = 'en'): Collection|array
    {
        return $this->client->getAccountInfo($lang);
    }

    public function transactionInquiry(array $data, string $lang = 'en'): Collection|array
    {
        $providerList = $this->client->getProviderList(2, $lang);
        $data['service_version'] = $providerList['data']['service_version'];

        return $this->client->transactionInquiry($data, $lang);
    }

    public function transactionPayment(array $data, string $lang = 'en'): Collection|array
    {
        $providerList = $this->client->getProviderList(2, $lang);
        $data['service_version'] = $providerList['data']['service_version'];

        return $this->client->transactionPayment($data, $lang);
    }

    public function calculateServiceCharge(array $data): array
    {
        return $this->client->calculateServiceCharge($data);
    }

    public function calculateServiceChargeReverse(array $data): array
    {
        return $this->client->calculateServiceChargeReverse($data);
    }

    public function getBillsAmount(array $data): array
    {
        return $this->client->getBillsAmount($data);
    }
}
