<?php

namespace Ghanem\Bee;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class ApiClient
{
    public function request(string $endpoint, array $params = []): Collection|array
    {
        $params['login'] = config('bee.username');
        $params['password'] = config('bee.password');
        $link = config('bee.url') . $endpoint;

        $response = Http::acceptJson()
            ->contentType('application/json;charset=UTF-8')
            ->post($link, $params);

        if ($response->ok()) {
            return collect($response->json());
        }

        return [
            'params' => $params,
            'link' => $link,
            'status_code' => $response->status(),
            ...$response->json() ?? [],
        ];
    }

    public function getProviderList(int $categoryId = 2, string $lang = 'en'): Collection|array
    {
        return $this->request('service', [
            'terminal_id' => '1',
            'action' => 'GetProviderList',
            'version' => 2,
            'language' => $lang,
            'data' => ['service_version' => 0],
        ]);
    }

    public function getServiceList(string $lang = 'en'): Collection|array
    {
        return $this->request('service', [
            'terminal_id' => '1',
            'action' => 'GetServiceList',
            'version' => 2,
            'language' => $lang,
            'data' => ['s' => 'd'],
        ]);
    }

    public function getServiceInputParameterList(string $lang = 'en'): Collection|array
    {
        return $this->request('service', [
            'terminal_id' => '1',
            'action' => 'GetServiceInputParameterList',
            'version' => 2,
            'language' => $lang,
            'data' => ['s' => 'd'],
        ]);
    }

    public function getServiceOutputParameterList(string $lang = 'en'): Collection|array
    {
        return $this->request('service', [
            'terminal_id' => '1',
            'action' => 'GetServiceOutputParameterList',
            'version' => 2,
            'language' => $lang,
            'data' => ['s' => 'd'],
        ]);
    }

    public function getCategoryList(string $lang = 'en'): Collection|array
    {
        return $this->request('service', [
            'terminal_id' => '1',
            'action' => 'GetCategoryList',
            'version' => 2,
            'language' => $lang,
            'data' => ['s' => 1],
        ]);
    }

    public function getCategoryServiceList(string $lang = 'en'): Collection|array
    {
        return $this->request('service', [
            'terminal_id' => '1',
            'action' => 'GetCategoryServiceList',
            'version' => 2,
            'language' => $lang,
            'data' => ['s' => 1],
        ]);
    }

    public function getTransaction(int|string $id, string $type = 'id', string $lang = 'en'): Collection|array
    {
        $action = $type === 'external_id' ? 'GetTransactionByExternalId' : 'GetTransactionDetails';
        $dataKey = $type === 'external_id' ? 'external_id' : 'transaction_id';

        return $this->request('report', [
            'terminal_id' => '1',
            'action' => $action,
            'version' => 2,
            'language' => $lang,
            'data' => [$dataKey => $id],
        ]);
    }

    public function getAccountInfo(string $lang = 'en'): Collection|array
    {
        return $this->request('report', [
            'terminal_id' => '1',
            'action' => 'GetAccountInfo',
            'version' => 2,
            'language' => $lang,
            'data' => ['s' => 'd'],
        ]);
    }

    public function transactionInquiry(array $data, string $lang = 'en'): Collection|array
    {
        return $this->request('transaction', [
            'terminal_id' => '1',
            'action' => 'TransactionInquiry',
            'version' => 2,
            'language' => $lang,
            'data' => [
                'service_version' => $data['service_version'] ?? 2,
                'account_number' => $data['account_number'] ?? 2,
                'service_id' => $data['service_id'] ?? 14,
                'input_parameter_list' => $data['input_parameter_list'] ?? [],
            ],
        ]);
    }

    public function transactionPayment(array $data, string $lang = 'en'): Collection|array
    {
        return $this->request('transaction', [
            'terminal_id' => '1',
            'action' => 'TransactionPayment',
            'version' => 2,
            'language' => $lang,
            'data' => [
                'service_version' => $data['service_version'] ?? 2,
                'account_number' => $data['account_number'] ?? 2,
                'service_id' => $data['service_id'] ?? 14,
                'external_id' => $data['external_id'] ?? '14',
                'amount' => $data['amount'] ?? 1.5,
                'service_charge' => $data['service_charge'] ?? 0,
                'total_amount' => $data['total_amount'] ?? 1.5,
                'quantity' => $data['quantity'] ?? 1,
                'inquiry_transaction_id' => $data['inquiry_transaction_id'] ?? 2,
                'input_parameter_list' => $data['input_parameter_list'] ?? [],
            ],
        ]);
    }

    public function calculateServiceCharge(array $data): array
    {
        $serviceList = $this->getServiceList()['data'] ?? [];
        $service = collect($serviceList['service_list'])->where('id', $data['service_id'])->first();
        $chargeObject = $this->getServiceChargeObject($service['service_charge_list'], $data['amount']);

        $data['service_charge'] = $chargeObject['percentage']
            ? $data['amount'] * $chargeObject['charge'] / 100
            : $chargeObject['charge'];
        $data['service_charge'] = max($data['service_charge'], $chargeObject['slap']);
        $data['total_amount'] = $data['amount'] + $data['service_charge'];

        return $data;
    }

    public function calculateServiceChargeReverse(array $data): array
    {
        $serviceList = $this->getServiceList()['data'] ?? [];
        $service = collect($serviceList['service_list'])->where('id', $data['service_id'])->first();
        $chargeObject = $this->getServiceChargeObject($service['service_charge_list'], $data['amount']);

        $data['total_amount'] = $data['amount'];
        $data['amount'] = round($data['total_amount'] / (1 + $chargeObject['charge'] / 100), 2);
        $data['service_charge'] = bcdiv(
            $chargeObject['percentage'] ? $data['amount'] * $chargeObject['charge'] / 100 : $chargeObject['charge'],
            1,
            2
        );
        $data['service_charge'] = max((float) $data['service_charge'], $chargeObject['slap']);
        $data['total_amount'] = $data['amount'] + $data['service_charge'];

        return $data;
    }

    public function getBillsAmount(array $data): array
    {
        $transactionInquiry = $this->transactionInquiry($data);
        $data['inquiry_transaction_id'] = $transactionInquiry['data']['transaction_id'];
        $data['amount'] = $transactionInquiry['data']['amount'];

        return $data;
    }

    protected function getServiceChargeObject(array $chargeList, float $amount): ?array
    {
        return collect($chargeList)
            ->where('from', '<=', $amount)
            ->where('to', '>=', $amount)
            ->first();
    }
}
