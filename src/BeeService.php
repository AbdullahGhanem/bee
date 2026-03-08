<?php

namespace Ghanem\Bee;

use Ghanem\Bee\DTOs\ApiResponse;
use Ghanem\Bee\DTOs\ServiceChargeResult;
use Ghanem\Bee\DTOs\TransactionResult;
use Ghanem\Bee\Jobs\BatchTransactionJob;
use Ghanem\Bee\Jobs\ProcessTransactionPaymentJob;
use Illuminate\Bus\Batch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;

class BeeService
{
    public function __construct(
        protected ApiClient $client = new ApiClient()
    ) {}

    // -------------------------------------------------------------------------
    // Standard methods (return Collection|array for backward compatibility)
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // DTO methods (return typed DTOs)
    // -------------------------------------------------------------------------

    public function getCategoryListDto(string $lang = 'en'): ApiResponse
    {
        return $this->client->requestDto('service', [
            'terminal_id' => '1',
            'action' => 'GetCategoryList',
            'version' => 2,
            'language' => $lang,
            'data' => ['s' => 1],
        ]);
    }

    public function getServiceListDto(string $lang = 'en'): ApiResponse
    {
        return $this->client->requestDto('service', [
            'terminal_id' => '1',
            'action' => 'GetServiceList',
            'version' => 2,
            'language' => $lang,
            'data' => ['s' => 'd'],
        ]);
    }

    public function getTransactionDto(int|string $id, string $type = 'id', string $lang = 'en'): TransactionResult
    {
        $response = $this->client->requestDto('report', [
            'terminal_id' => '1',
            'action' => $type === 'external_id' ? 'GetTransactionByExternalId' : 'GetTransactionDetails',
            'version' => 2,
            'language' => $lang,
            'data' => [$type === 'external_id' ? 'external_id' : 'transaction_id' => $id],
        ]);

        return TransactionResult::fromApiResponse($response);
    }

    public function transactionInquiryDto(array $data, string $lang = 'en'): TransactionResult
    {
        $result = $this->transactionInquiry($data, $lang);
        $apiResponse = $result instanceof Collection
            ? ApiResponse::fromSuccess($result->toArray())
            : ApiResponse::fromError($result, $result['status_code'] ?? 500);

        return TransactionResult::fromApiResponse($apiResponse);
    }

    public function transactionPaymentDto(array $data, string $lang = 'en'): TransactionResult
    {
        $result = $this->transactionPayment($data, $lang);
        $apiResponse = $result instanceof Collection
            ? ApiResponse::fromSuccess($result->toArray())
            : ApiResponse::fromError($result, $result['status_code'] ?? 500);

        return TransactionResult::fromApiResponse($apiResponse);
    }

    public function calculateServiceChargeDto(array $data): ServiceChargeResult
    {
        return ServiceChargeResult::fromArray($this->client->calculateServiceCharge($data));
    }

    public function calculateServiceChargeReverseDto(array $data): ServiceChargeResult
    {
        return ServiceChargeResult::fromArray($this->client->calculateServiceChargeReverse($data));
    }

    // -------------------------------------------------------------------------
    // Async / Queue methods
    // -------------------------------------------------------------------------

    public function transactionPaymentAsync(array $data, string $lang = 'en'): void
    {
        ProcessTransactionPaymentJob::dispatch($data, $lang);
    }

    public function batchTransactions(array $transactions, ?string $callbackEvent = null): Batch
    {
        $jobs = array_map(function ($tx) use ($callbackEvent) {
            return new BatchTransactionJob(
                action: $tx['action'] ?? 'payment',
                data: $tx['data'],
                lang: $tx['lang'] ?? 'en',
                callbackEvent: $callbackEvent,
            );
        }, $transactions);

        $batch = Bus::batch($jobs)
            ->onQueue(config('bee.queue.queue', 'default'));

        $connection = config('bee.queue.connection') ?? config('queue.default');
        if ($connection) {
            $batch->onConnection($connection);
        }

        return $batch->dispatch();
    }

    // -------------------------------------------------------------------------
    // Cache management
    // -------------------------------------------------------------------------

    public function clearCache(?string $key = null): void
    {
        $this->client->clearCache($key);
    }
}
