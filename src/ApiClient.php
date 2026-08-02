<?php

namespace Ghanem\Bee;

use Ghanem\Bee\DTOs\ApiResponse;
use Ghanem\Bee\Enums\ErrorCode;
use Ghanem\Bee\Enums\OperationStatus;
use Ghanem\Bee\Exceptions\BeeException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class ApiClient
{
    /**
     * terminal_id and language are resolved and injected here — the same
     * choke point as login/password — so no caller (action method, DTO
     * method, or a raw ->request() call) can forget them or hardcode a
     * literal.
     */
    public function request(string $endpoint, array $params = []): Collection|array
    {
        if ($this->isRateLimited()) {
            return $this->handleFailure(ErrorCode::RateLimitExceeded->value, [
                'success' => false,
                'code' => ErrorCode::RateLimitExceeded->value,
                'message' => ErrorCode::RateLimitExceeded->message(),
            ]);
        }

        $params['terminal_id'] = $this->resolveTerminalId($params['terminal_id'] ?? null);
        $params['language'] = $this->resolveLanguage($params['language'] ?? null);
        $params['login'] = config('bee.username');
        $params['password'] = config('bee.password');
        $link = config('bee.url') . $endpoint;

        $this->logRequest($endpoint, $params);

        $retryConfig = config('bee.retry', []);
        $tries = $retryConfig['tries'] ?? 3;
        $delay = $retryConfig['delay'] ?? 100;
        $multiplier = $retryConfig['multiplier'] ?? 2;

        $response = Http::acceptJson()
            ->contentType('application/json;charset=UTF-8')
            ->retry($tries, $delay, function ($exception, $request) use ($multiplier) {
                return $exception instanceof \Illuminate\Http\Client\ConnectionException;
            }, throw: false)
            ->post($link, $params);

        $this->hitRateLimiter();

        if ($response->ok()) {
            // json() is null for an empty body or a non-JSON (e.g. WAF/proxy
            // HTML) body, which folds into [] here and is correctly treated
            // as a failure below — nothing affirmatively said success.
            $data = $response->json() ?? [];
            $success = $data['success'] ?? null;
            $isBusinessFailure = $success !== true;

            $this->logResponse($endpoint, $data, $response->status(), $isBusinessFailure);

            if ($isBusinessFailure) {
                return $this->handleFailure($this->extractApiCode($data), $data);
            }

            return collect($data);
        }

        $errorData = [
            'params' => $params,
            'link' => $link,
            'status_code' => $response->status(),
            ...$response->json() ?? [],
        ];
        $this->logResponse($endpoint, $errorData, $response->status(), true);

        return $errorData;
    }

    /**
     * Only used to pick WHICH exception to throw once a response is already
     * known to be a failure (see request()) — never to decide IF it failed.
     * The error code can show up under different keys depending on the
     * failure path, so every plausible field is checked. The PDF's only
     * full JSON sample (FAQ Q17, page 21) is a success response and carries
     * none of these keys.
     */
    protected function extractApiCode(array $data): ?int
    {
        foreach (['code', 'error_code', 'status_code'] as $key) {
            if (isset($data[$key]) && is_numeric($data[$key])) {
                return (int) $data[$key];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function handleFailure(?int $code, array $payload): array
    {
        if (config('bee.errors.throw', true)) {
            throw BeeException::fromCode($code, $payload);
        }

        return $payload;
    }

    /**
     * Public so other classes composing an ApiClient (e.g. BeeService's DTO
     * methods) can reuse the same config resolution + validation instead of
     * duplicating it.
     */
    public function resolveTerminalId(?string $terminalId = null): string
    {
        $terminalId ??= config('bee.terminal_id');

        // Not empty(): "0" is a legitimate terminal ID, not a falsy absence.
        if ($terminalId === null || $terminalId === '') {
            throw BeeException::fromCode(ErrorCode::TerminalIdRequired->value);
        }

        return $terminalId;
    }

    public function resolveLanguage(?string $lang = null): string
    {
        return $lang ?? config('bee.language', 'en');
    }

    public function requestDto(string $endpoint, array $params = []): ApiResponse
    {
        $result = $this->request($endpoint, $params);

        if ($result instanceof Collection) {
            return ApiResponse::fromSuccess($result->toArray());
        }

        return ApiResponse::fromError($result, $result['status_code'] ?? 500);
    }

    public function getProviderList(int $categoryId = 2, ?string $lang = null, ?string $terminalId = null): Collection|array
    {
        return $this->request('service', [
            'terminal_id' => $terminalId,
            'action' => 'GetProviderList',
            'version' => 2,
            'language' => $lang,
            'data' => ['service_version' => 0],
        ]);
    }

    public function getServiceList(?string $lang = null, ?string $terminalId = null): Collection|array
    {
        return $this->cached('service_list_' . $this->resolveLanguage($lang), function () use ($lang, $terminalId) {
            return $this->request('service', [
                'terminal_id' => $terminalId,
                'action' => 'GetServiceList',
                'version' => 2,
                'language' => $lang,
                // PDF 5.2 / p.24: "data": {} for all services (no provider_id).
                'data' => (object) [],
            ]);
        });
    }

    public function getServiceInputParameterList(?string $lang = null, ?string $terminalId = null): Collection|array
    {
        return $this->cached('service_input_params_' . $this->resolveLanguage($lang), function () use ($lang, $terminalId) {
            return $this->request('service', [
                'terminal_id' => $terminalId,
                'action' => 'GetServiceInputParameterList',
                'version' => 2,
                'language' => $lang,
                // PDF 5.5 / p.24: "data": {} for all services (no service_id).
                'data' => (object) [],
            ]);
        });
    }

    public function getServiceOutputParameterList(?string $lang = null, ?string $terminalId = null): Collection|array
    {
        return $this->cached('service_output_params_' . $this->resolveLanguage($lang), function () use ($lang, $terminalId) {
            return $this->request('service', [
                'terminal_id' => $terminalId,
                'action' => 'GetServiceOutputParameterList',
                'version' => 2,
                'language' => $lang,
                // PDF 5.6 / p.24: "data": {} for all services (no service_id).
                'data' => (object) [],
            ]);
        });
    }

    public function getCategoryList(?string $lang = null, ?string $terminalId = null): Collection|array
    {
        return $this->cached('category_list_' . $this->resolveLanguage($lang), function () use ($lang, $terminalId) {
            return $this->request('service', [
                'terminal_id' => $terminalId,
                'action' => 'GetCategoryList',
                'version' => 2,
                'language' => $lang,
                // PDF 5.3 / p.23: no request parameters at all — "data": {}.
                'data' => (object) [],
            ]);
        });
    }

    public function getCategoryServiceList(?string $lang = null, ?string $terminalId = null): Collection|array
    {
        return $this->cached('category_service_list_' . $this->resolveLanguage($lang), function () use ($lang, $terminalId) {
            return $this->request('service', [
                'terminal_id' => $terminalId,
                'action' => 'GetCategoryServiceList',
                'version' => 2,
                'language' => $lang,
                // PDF 5.4 / p.24: no request parameters at all — "data": {}.
                'data' => (object) [],
            ]);
        });
    }

    public function getTransaction(int|string $id, string $type = 'id', ?string $lang = null, ?string $terminalId = null): Collection|array
    {
        $action = $type === 'external_id' ? 'GetTransactionByExternalId' : 'GetTransactionDetails';
        $dataKey = $type === 'external_id' ? 'external_id' : 'transaction_id';

        return $this->request('report', [
            'terminal_id' => $terminalId,
            'action' => $action,
            'version' => 2,
            'language' => $lang,
            'data' => [$dataKey => $id],
        ]);
    }

    public function getAccountInfo(?string $lang = null, ?string $terminalId = null): Collection|array
    {
        return $this->request('report', [
            'terminal_id' => $terminalId,
            'action' => 'GetAccountInfo',
            'version' => 2,
            'language' => $lang,
            // PDF 5.10 / p.25: no request parameters at all — "data": {}.
            'data' => (object) [],
        ]);
    }

    public function transactionInquiry(array $data, ?string $lang = null, ?string $terminalId = null): Collection|array
    {
        return $this->request('transaction', [
            'terminal_id' => $terminalId,
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

    public function transactionPayment(array $data, ?string $lang = null, ?string $terminalId = null): Collection|array
    {
        return $this->request('transaction', [
            'terminal_id' => $terminalId,
            'action' => 'TransactionPayment',
            'version' => 2,
            'language' => $lang,
            'data' => [
                'service_version' => $data['service_version'] ?? 2,
                'account_number' => $data['account_number'] ?? 2,
                'service_id' => $data['service_id'] ?? 14,
                'external_id' => $data['external_id'] ?? '14',
                'amount' => $data['amount'] ?? 1.5,
                // PDF 5.8 (p.14): service_charge is NOT a request field — only
                // external_id, service_version, account_number, service_id,
                // inquiry_transaction_id, amount, total_amount, quantity and
                // input_parameter_list are. total_amount already carries the
                // charge; sending an undocumented service_charge key was a
                // contract mismatch.
                'total_amount' => $data['total_amount'] ?? 1.5,
                'quantity' => $data['quantity'] ?? 1,
                'inquiry_transaction_id' => $data['inquiry_transaction_id'] ?? 2,
                'input_parameter_list' => $data['input_parameter_list'] ?? [],
            ],
        ]);
    }

    public function confirmPrepaidCardRecharge(
        string $paymentTransactionId,
        OperationStatus $status,
        ?string $lang = null,
        ?string $terminalId = null,
    ): Collection|array {
        return $this->request('service', [
            'terminal_id' => $terminalId,
            'action' => 'ConfirmPrepaidCardRecharge',
            'version' => 2,
            'language' => $lang,
            'data' => [
                'payment_transaction_id' => $paymentTransactionId,
                'operation_status' => $status->value,
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

    public function clearCache(?string $key = null): void
    {
        $store = Cache::store(config('bee.cache.store'));
        $prefix = config('bee.cache.prefix', 'bee_');

        if ($key) {
            $store->forget($prefix . $key);
            return;
        }

        $cacheKeys = [
            'category_list_en', 'category_list_ar',
            'category_service_list_en', 'category_service_list_ar',
            'service_list_en', 'service_list_ar',
            'service_input_params_en', 'service_input_params_ar',
            'service_output_params_en', 'service_output_params_ar',
        ];

        foreach ($cacheKeys as $cacheKey) {
            $store->forget($prefix . $cacheKey);
        }
    }

    protected function getServiceChargeObject(array $chargeList, float $amount): ?array
    {
        return collect($chargeList)
            ->where('from', '<=', $amount)
            ->where('to', '>=', $amount)
            ->first();
    }

    protected function cached(string $key, callable $callback): Collection|array
    {
        if (! config('bee.cache.enabled', true)) {
            return $callback();
        }

        $store = Cache::store(config('bee.cache.store'));
        $fullKey = config('bee.cache.prefix', 'bee_') . $key;

        if ($store->has($fullKey)) {
            return $store->get($fullKey);
        }

        $result = $callback();

        // Only a Collection means request() actually succeeded — a business
        // failure (bee.errors.throw = false) or a transport error both come
        // back as a plain array and must never be cached: a transient error
        // would otherwise poison every read for the full TTL.
        if ($result instanceof Collection) {
            $store->put($fullKey, $result, config('bee.cache.ttl', 3600));
        }

        return $result;
    }

    protected function logRequest(string $endpoint, array $params): void
    {
        if (! config('bee.logging.enabled', false)) {
            return;
        }

        $safeParams = $params;
        unset($safeParams['login'], $safeParams['password']);

        Log::channel(config('bee.logging.channel'))
            ->info('Bee API Request', [
                'endpoint' => $endpoint,
                'params' => $safeParams,
            ]);
    }

    protected function logResponse(string $endpoint, array $data, int $statusCode, bool $isError = false): void
    {
        if (! config('bee.logging.enabled', false)) {
            return;
        }

        $method = $isError ? 'error' : 'info';

        Log::channel(config('bee.logging.channel'))
            ->$method('Bee API Response', [
                'endpoint' => $endpoint,
                'status_code' => $statusCode,
                'response' => $data,
            ]);
    }

    protected function isRateLimited(): bool
    {
        if (! config('bee.rate_limit.enabled', false)) {
            return false;
        }

        return RateLimiter::tooManyAttempts('bee-api', config('bee.rate_limit.max_attempts', 60));
    }

    protected function hitRateLimiter(): void
    {
        if (! config('bee.rate_limit.enabled', false)) {
            return;
        }

        RateLimiter::hit('bee-api', 60);
    }
}
