<?php

namespace Ghanem\Bee\Tests\Unit;

use Ghanem\Bee\ApiClient;
use Ghanem\Bee\Enums\OperationStatus;
use Ghanem\Bee\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class ConfirmPrepaidCardRechargeTest extends TestCase
{
    public function test_it_sends_the_documented_request(): void
    {
        config()->set('bee.terminal_id', '1234567890');
        Http::fake(['*' => Http::response(['success' => true, 'data' => ['info' => 'ok']], 200)]);

        app(ApiClient::class)->confirmPrepaidCardRecharge('225615364271', OperationStatus::Success);

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/service')
                && $request['action'] === 'ConfirmPrepaidCardRecharge'
                && $request['version'] === 2
                && $request['data']['payment_transaction_id'] === '225615364271'
                && $request['data']['operation_status'] === 'SUCCESS';
        });
    }

    public function test_it_can_report_failure(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'data' => ['info' => 'ok']], 200)]);

        app(ApiClient::class)->confirmPrepaidCardRecharge('1', OperationStatus::Fail);

        Http::assertSent(fn ($request) => $request['data']['operation_status'] === 'FAIL');
    }
}
