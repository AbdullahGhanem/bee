<?php

namespace Ghanem\Bee\Tests\Unit;

use Ghanem\Bee\Facades\Bee;
use Ghanem\Bee\Jobs\BatchTransactionJob;
use Ghanem\Bee\Jobs\ProcessTransactionPaymentJob;
use Ghanem\Bee\Tests\TestCase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

class AsyncTest extends TestCase
{
    public function test_transaction_payment_async_dispatches_job(): void
    {
        Queue::fake();

        Bee::transactionPaymentAsync([
            'account_number' => '12345',
            'service_id' => 10,
            'amount' => 100,
        ]);

        Queue::assertPushed(ProcessTransactionPaymentJob::class, function ($job) {
            return $job->data['account_number'] === '12345'
                && $job->data['amount'] === 100;
        });
    }

    public function test_transaction_payment_async_with_language(): void
    {
        Queue::fake();

        Bee::transactionPaymentAsync(['service_id' => 10], 'ar');

        Queue::assertPushed(ProcessTransactionPaymentJob::class, function ($job) {
            return $job->lang === 'ar';
        });
    }

    public function test_batch_transactions_dispatches_batch(): void
    {
        Bus::fake();

        $transactions = [
            ['action' => 'payment', 'data' => ['service_id' => 10, 'amount' => 100]],
            ['action' => 'inquiry', 'data' => ['service_id' => 11, 'account_number' => '123']],
            ['action' => 'payment', 'data' => ['service_id' => 12, 'amount' => 200]],
        ];

        Bee::batchTransactions($transactions);

        Bus::assertBatched(function ($batch) {
            return $batch->jobs->count() === 3;
        });
    }

    public function test_batch_transaction_job_defaults_to_payment_action(): void
    {
        Bus::fake();

        Bee::batchTransactions([
            ['data' => ['service_id' => 10, 'amount' => 50]],
        ]);

        Bus::assertBatched(function ($batch) {
            $job = $batch->jobs->first();

            return $job instanceof BatchTransactionJob
                && $job->action === 'payment';
        });
    }

    public function test_batch_transactions_with_callback_event(): void
    {
        Bus::fake();

        Bee::batchTransactions(
            [['action' => 'payment', 'data' => ['service_id' => 10]]],
            'App\\Events\\TransactionProcessed'
        );

        Bus::assertBatched(function ($batch) {
            $job = $batch->jobs->first();

            return $job->callbackEvent === 'App\\Events\\TransactionProcessed';
        });
    }

    public function test_batch_transactions_with_language(): void
    {
        Bus::fake();

        Bee::batchTransactions([
            ['action' => 'payment', 'data' => ['service_id' => 10], 'lang' => 'ar'],
        ]);

        Bus::assertBatched(function ($batch) {
            return $batch->jobs->first()->lang === 'ar';
        });
    }

    public function test_process_transaction_payment_job_executes(): void
    {
        Http::fake([
            'https://api.bee.test/service' => Http::response([
                'success' => true,
                'data' => ['service_version' => 1],
            ], 200),
            'https://api.bee.test/transaction' => Http::response([
                'success' => true,
                'data' => ['transaction_id' => 99],
            ], 200),
        ]);

        $job = new ProcessTransactionPaymentJob(
            data: ['service_id' => 10, 'amount' => 100],
            lang: 'en',
        );

        $job->handle(app(\Ghanem\Bee\BeeService::class));

        Http::assertSentCount(2);
    }
}
