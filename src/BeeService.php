<?php

namespace Ghanem\Bee;

use Ghanem\Bee\DTOs\ApiResponse;
use Ghanem\Bee\DTOs\ServiceChargeResult;
use Ghanem\Bee\DTOs\TransactionResult;
use Ghanem\Bee\Enums\OperationStatus;
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

    public function getCategoryList(?string $lang = null): Collection|array
    {
        return $this->client->getCategoryList($lang);
    }

    public function getCategoryServiceList(?string $lang = null): Collection|array
    {
        return $this->client->getCategoryServiceList($lang);
    }

    public function getProviderList(int $categoryId = 2, ?string $lang = null): Collection|array
    {
        return $this->client->getProviderList($categoryId, $lang);
    }

    public function getServiceList(?string $lang = null): Collection|array
    {
        return $this->client->getServiceList($lang);
    }

    public function getServiceInputParameterList(?string $lang = null): Collection|array
    {
        return $this->client->getServiceInputParameterList($lang);
    }

    public function getServiceOutputParameterList(?string $lang = null): Collection|array
    {
        return $this->client->getServiceOutputParameterList($lang);
    }

    public function getTransaction(int|string $id, string $type = 'id', ?string $lang = null): Collection|array
    {
        return $this->client->getTransaction($id, $type, $lang);
    }

    public function getAccountInfo(?string $lang = null): Collection|array
    {
        return $this->client->getAccountInfo($lang);
    }

    public function confirmPrepaidCardRecharge(string $paymentTransactionId, OperationStatus $status, ?string $lang = null): Collection|array
    {
        return $this->client->confirmPrepaidCardRecharge($paymentTransactionId, $status, $lang);
    }

    public function transactionInquiry(array $data, ?string $lang = null): Collection|array
    {
        $providerList = $this->client->getProviderList(2, $lang);
        $data['service_version'] = $providerList['data']['service_version'];

        return $this->client->transactionInquiry($data, $lang);
    }

    public function transactionPayment(array $data, ?string $lang = null): Collection|array
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

    /**
     * DTO methods route through the same ApiClient action methods as the
     * "standard" methods above (never hand-build a `data` payload here) —
     * that duplication was fixed once already (ConfirmPrepaidCardRecharge
     * task) and still slipped back in for GetCategoryList/GetServiceList via
     * copy-paste. One choke point (`toApiResponse()` below plus the action
     * methods on ApiClient) means a wire-contract fix can't miss this half
     * of the class again.
     */
    public function getCategoryListDto(?string $lang = null): ApiResponse
    {
        return $this->toApiResponse($this->client->getCategoryList($lang));
    }

    public function getServiceListDto(?string $lang = null): ApiResponse
    {
        return $this->toApiResponse($this->client->getServiceList($lang));
    }

    public function getTransactionDto(int|string $id, string $type = 'id', ?string $lang = null): TransactionResult
    {
        return TransactionResult::fromApiResponse(
            $this->toApiResponse($this->client->getTransaction($id, $type, $lang))
        );
    }

    public function transactionInquiryDto(array $data, ?string $lang = null): TransactionResult
    {
        return TransactionResult::fromApiResponse($this->toApiResponse($this->transactionInquiry($data, $lang)));
    }

    public function transactionPaymentDto(array $data, ?string $lang = null): TransactionResult
    {
        return TransactionResult::fromApiResponse($this->toApiResponse($this->transactionPayment($data, $lang)));
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

    public function transactionPaymentAsync(array $data, ?string $lang = null): void
    {
        ProcessTransactionPaymentJob::dispatch($data, $lang);
    }

    public function batchTransactions(array $transactions, ?string $callbackEvent = null): Batch
    {
        $jobs = array_map(function ($tx) use ($callbackEvent) {
            return new BatchTransactionJob(
                action: $tx['action'] ?? 'payment',
                data: $tx['data'],
                lang: $tx['lang'] ?? null,
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

    /**
     * Shared by every *Dto method: a Collection means the underlying
     * ApiClient call succeeded, anything else (a plain array) is the
     * error/transport-failure shape request() falls back to.
     */
    protected function toApiResponse(Collection|array $result): ApiResponse
    {
        return $result instanceof Collection
            ? ApiResponse::fromSuccess($result->toArray())
            : ApiResponse::fromError($result, $result['status_code'] ?? 500);
    }
}
