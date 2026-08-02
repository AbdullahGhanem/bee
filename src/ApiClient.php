<?php

namespace Ghanem\Basata;

use Ghanem\Basata\DTOs\ApiResponse;
use Ghanem\Basata\Enums\ErrorCode;
use Ghanem\Basata\Enums\OperationStatus;
use Ghanem\Basata\Exceptions\BasataException;
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
        $params['login'] = config('basata.username');
        $params['password'] = config('basata.password');
        $link = config('basata.url') . $endpoint;

        $this->logRequest($endpoint, $params);

        $retryConfig = config('basata.retry', []);
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

        // json() is null for an empty body or a non-JSON (e.g. WAF/proxy HTML)
        // body, and a bare scalar for a body like `true` or `123` — both fold
        // into [] here. Anything that isn't an object/array never affirmatively
        // said success, so it is correctly treated as a failure below.
        $body = is_array($response->json()) ? $response->json() : [];

        if ($response->ok()) {
            $isBusinessFailure = ($body['success'] ?? null) !== true;

            $this->logResponse($endpoint, $body, $response->status(), $isBusinessFailure);

            if ($isBusinessFailure) {
                return $this->handleFailure($this->extractApiCode($body), $body);
            }

            return collect($body);
        }

        // A transport/server failure (non-2xx) goes through the same error
        // layer as a business failure: a 502 on TransactionPayment is exactly
        // when the caller must not read a null transaction_id and carry on.
        // The API error code wins when the body carries one; otherwise the
        // HTTP status stands in (no ErrorCode matches a 3-digit status, so it
        // falls back to BasataServerException).
        $errorData = [
            'params' => $this->withoutCredentials($params),
            'link' => $link,
            'status_code' => $response->status(),
            ...$body,
        ];
        $this->logResponse($endpoint, $errorData, $response->status(), true);

        return $this->handleFailure($this->extractApiCode($body) ?? $response->status(), $errorData);
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
        if (config('basata.errors.throw', true)) {
            throw BasataException::fromCode($code, $payload);
        }

        return $payload;
    }

    /**
     * Public so other classes composing an ApiClient (e.g. BasataService's DTO
     * methods) can reuse the same config resolution + validation instead of
     * duplicating it.
     */
    public function resolveTerminalId(?string $terminalId = null): string
    {
        $terminalId ??= config('basata.terminal_id');

        // Not empty(): "0" is a legitimate terminal ID, not a falsy absence.
        if ($terminalId === null || $terminalId === '') {
            throw BasataException::fromCode(ErrorCode::TerminalIdRequired->value);
        }

        return $terminalId;
    }

    public function resolveLanguage(?string $lang = null): string
    {
        return $lang ?? config('basata.language', 'en');
    }

    /**
     * Fails fast on a missing required (+) field instead of silently
     * substituting a placeholder default. `transactionInquiry()` and
     * `transactionPayment()` used to default e.g. `amount` to 1.5 and
     * `service_id` to 14 when the caller forgot them — meaning a forgotten
     * `amount` silently posted a real 1.5 EGP payment against service 14
     * instead of failing. "0" is not empty here for the same reason it isn't
     * in resolveTerminalId(): it can be a legitimate value (e.g.
     * service_version 0 on first use).
     */
    protected function requireField(array $data, string $key, ErrorCode $code): mixed
    {
        $value = $data[$key] ?? null;

        if ($value === null || $value === '') {
            throw BasataException::fromCode($code->value);
        }

        return $value;
    }

    public function requestDto(string $endpoint, array $params = []): ApiResponse
    {
        $result = $this->request($endpoint, $params);

        if ($result instanceof Collection) {
            return ApiResponse::fromSuccess($result->toArray());
        }

        return ApiResponse::fromError($result, $result['status_code'] ?? 500);
    }

    /**
     * PDF 5.1: the action takes `service_version` only — there is no category
     * filter, so the old `$categoryId` argument was accepted and silently
     * dropped. Sending `service_version: 0` means "force update the service
     * list", which FAQ A1 says to avoid doing routinely ("store this value…
     * check it periodically"), so the response is cached like the other
     * catalogue calls. Call `clearCache('provider_list_en')` (or
     * `clearCache()`) to force a refresh.
     */
    public function getProviderList(?string $lang = null, ?string $terminalId = null): Collection|array
    {
        return $this->cached('provider_list_' . $this->resolveLanguage($lang), function () use ($lang, $terminalId) {
            return $this->request('service', [
                'terminal_id' => $terminalId,
                'action' => 'GetProviderList',
                'version' => 2,
                'language' => $lang,
                'data' => ['service_version' => 0],
            ]);
        });
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
            // PDF 5.7 (p.13): service_version, account_number and service_id
            // are all required (+) — no client-side defaults for them.
            'data' => [
                'service_version' => $this->requireField($data, 'service_version', ErrorCode::DataRequired),
                'account_number' => $this->requireField($data, 'account_number', ErrorCode::DataRequired),
                'service_id' => $this->requireField($data, 'service_id', ErrorCode::DataRequired),
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
            // PDF 5.8 (p.14): external_id, service_version, account_number,
            // service_id, amount, total_amount and quantity are all required
            // (+) — no client-side defaults for them. amount/total_amount use
            // WrongAmount (1017); the rest use DataRequired (1008) — neither
            // field has a more specific documented code.
            'data' => [
                'service_version' => $this->requireField($data, 'service_version', ErrorCode::DataRequired),
                'account_number' => $this->requireField($data, 'account_number', ErrorCode::DataRequired),
                'service_id' => $this->requireField($data, 'service_id', ErrorCode::DataRequired),
                'external_id' => $this->requireField($data, 'external_id', ErrorCode::DataRequired),
                'amount' => $this->requireField($data, 'amount', ErrorCode::WrongAmount),
                // PDF FAQ A6 (p.19): "the client sends the request to Basata
                // including the amount, the calculated service charge, and
                // the total amount of the transaction" — an affirmative
                // statement that service_charge is a real request field, and
                // error 1022 "Wrong service charge" (p.18) only makes sense
                // if the server validates a client-submitted value. §5.8's
                // table omitting it is an absence, not a prohibition — that
                // table also omits `language`, which every sample sends and
                // error 1011 requires. Do not remove this again on the
                // "not in the table" reasoning; the table is demonstrably
                // incomplete elsewhere.
                'service_charge' => $data['service_charge'] ?? 0,
                'total_amount' => $this->requireField($data, 'total_amount', ErrorCode::WrongAmount),
                'quantity' => $this->requireField($data, 'quantity', ErrorCode::DataRequired),
                // Documented +/- ("Required if service's inquiry_required=
                // true") — genuinely optional, unlike the fields above, so
                // it's omitted rather than forced or defaulted to a magic
                // placeholder when the caller doesn't supply it.
                ...(($data['inquiry_transaction_id'] ?? '') !== ''
                    ? ['inquiry_transaction_id' => $data['inquiry_transaction_id']]
                    : []),
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
        $chargeList = $this->serviceChargeList($data['service_id']);
        $amount = (float) $data['amount'];
        $band = $this->getServiceChargeObject($chargeList, $amount);

        $data['service_charge'] = $this->chargeForAmount($band, $amount);
        $data['total_amount'] = $amount + $data['service_charge'];

        return $data;
    }

    /**
     * The inverse of calculateServiceCharge(): `amount` comes in as the total
     * the customer should pay, and the net amount + charge are derived from
     * it. A fixed (percentage = false) charge is an absolute amount, so it is
     * subtracted — dividing by (1 + charge/100) treated e.g. a flat 10 as
     * "10%" and returned a total that no longer matched what was asked for.
     * The band is matched on the *net* amount (what the forward calculation
     * would be given), not on the total.
     */
    public function calculateServiceChargeReverse(array $data): array
    {
        $chargeList = $this->serviceChargeList($data['service_id']);
        $total = (float) $data['amount'];

        $band = $this->requireChargeBand(collect($chargeList)->first(function ($band) use ($total) {
            $net = $total - $this->chargeForTotal($band, $total);

            return $net >= $band['from'] && $net <= $band['to'];
        }));

        $data['service_charge'] = $this->chargeForTotal($band, $total);
        $data['amount'] = round($total - $data['service_charge'], 2);
        // Round-trips by construction: the caller asked for this total and
        // gets it back, whatever the rounding did to the two components.
        $data['total_amount'] = $data['amount'] + $data['service_charge'];

        return $data;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function serviceChargeList(int|string $serviceId): array
    {
        $service = collect($this->getServiceList()['data']['service_list'] ?? [])
            ->firstWhere('id', $serviceId);

        if ($service === null) {
            throw BasataException::fromCode(ErrorCode::UnknownService->value);
        }

        return $service['service_charge_list'] ?? [];
    }

    /** Forward: charge on a known net amount. */
    protected function chargeForAmount(array $band, float $amount): float
    {
        $charge = $band['percentage'] ? $amount * $band['charge'] / 100 : (float) $band['charge'];

        return max(round($charge, 2), (float) ($band['slap'] ?? 0));
    }

    /** Reverse: charge contained in a known total. */
    protected function chargeForTotal(array $band, float $total): float
    {
        $charge = $band['percentage']
            ? $total - $total / (1 + $band['charge'] / 100)
            : (float) $band['charge'];

        return max(round($charge, 2), (float) ($band['slap'] ?? 0));
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
        $store = Cache::store(config('basata.cache.store'));
        $prefix = config('basata.cache.prefix', 'basata_');

        if ($key) {
            $store->forget($prefix . $key);
            return;
        }

        $cacheKeys = [
            'provider_list_en', 'provider_list_ar',
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

    protected function getServiceChargeObject(array $chargeList, float $amount): array
    {
        return $this->requireChargeBand(
            collect($chargeList)->first(fn ($band) => $amount >= $band['from'] && $amount <= $band['to'])
        );
    }

    /**
     * An amount outside every band used to yield service_charge = null and
     * total_amount = amount — a silently zero charge that then got posted and
     * came back as error 1022 "Wrong service charge" at best, or as an
     * under-charged payment at worst. Fail here instead.
     */
    protected function requireChargeBand(?array $band): array
    {
        if ($band === null) {
            throw BasataException::fromCode(ErrorCode::WrongServiceCharge->value);
        }

        return $band;
    }

    protected function cached(string $key, callable $callback): Collection|array
    {
        if (! config('basata.cache.enabled', true)) {
            return $callback();
        }

        $store = Cache::store(config('basata.cache.store'));
        $fullKey = config('basata.cache.prefix', 'basata_') . $key;

        // Single get(): has()-then-get() can return null if the entry expires
        // between the two calls, which violates this method's return type.
        $cached = $store->get($fullKey);

        if ($cached !== null) {
            return $cached;
        }

        $result = $callback();

        // Only a Collection means request() actually succeeded — a business
        // failure (basata.errors.throw = false) or a transport error both come
        // back as a plain array and must never be cached: a transient error
        // would otherwise poison every read for the full TTL.
        if ($result instanceof Collection) {
            $store->put($fullKey, $result, config('basata.cache.ttl', 3600));
        }

        return $result;
    }

    protected function logRequest(string $endpoint, array $params): void
    {
        if (! config('basata.logging.enabled', false)) {
            return;
        }

        Log::channel(config('basata.logging.channel'))
            ->info('Basata API Request', [
                'endpoint' => $endpoint,
                'params' => $this->withoutCredentials($params),
            ]);
    }

    /**
     * Single choke point for credential redaction — the request log and the
     * error payload both go through it, so neither can leak `login`/`password`
     * into a log line or back to the caller.
     */
    protected function withoutCredentials(array $params): array
    {
        unset($params['login'], $params['password']);

        return $params;
    }

    protected function logResponse(string $endpoint, array $data, int $statusCode, bool $isError = false): void
    {
        if (! config('basata.logging.enabled', false)) {
            return;
        }

        $method = $isError ? 'error' : 'info';

        Log::channel(config('basata.logging.channel'))
            ->$method('Basata API Response', [
                'endpoint' => $endpoint,
                'status_code' => $statusCode,
                'response' => $data,
            ]);
    }

    protected function isRateLimited(): bool
    {
        if (! config('basata.rate_limit.enabled', false)) {
            return false;
        }

        return RateLimiter::tooManyAttempts('basata-api', config('basata.rate_limit.max_attempts', 60));
    }

    protected function hitRateLimiter(): void
    {
        if (! config('basata.rate_limit.enabled', false)) {
            return;
        }

        RateLimiter::hit('basata-api', 60);
    }
}
