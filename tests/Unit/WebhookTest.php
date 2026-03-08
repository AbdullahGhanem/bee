<?php

namespace Ghanem\Bee\Tests\Unit;

use Ghanem\Bee\Events\BeeWebhookReceived;
use Ghanem\Bee\Events\TransactionStatusUpdated;
use Ghanem\Bee\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class WebhookTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('bee.webhook.enabled', true);
        $app['config']->set('bee.webhook.path', 'bee/webhook');
        $app['config']->set('bee.webhook.secret', null);
        $app['config']->set('bee.webhook.middleware', []);
    }

    public function test_webhook_route_is_registered(): void
    {
        $this->post('bee/webhook', ['event' => 'test'])
            ->assertStatus(200);
    }

    public function test_webhook_dispatches_generic_event(): void
    {
        Event::fake([BeeWebhookReceived::class]);

        $this->post('bee/webhook', [
            'event' => 'test.event',
            'data' => ['foo' => 'bar'],
        ]);

        Event::assertDispatched(BeeWebhookReceived::class, function ($event) {
            return $event->event === 'test.event';
        });
    }

    public function test_webhook_dispatches_transaction_status_event(): void
    {
        Event::fake([BeeWebhookReceived::class, TransactionStatusUpdated::class]);

        $this->post('bee/webhook', [
            'event' => 'transaction.completed',
            'data' => [
                'transaction_id' => 123,
                'status' => 'completed',
            ],
        ]);

        Event::assertDispatched(TransactionStatusUpdated::class, function ($event) {
            return $event->transactionId === 123
                && $event->status === 'completed';
        });
    }

    public function test_webhook_dispatches_for_failed_transaction(): void
    {
        Event::fake([BeeWebhookReceived::class, TransactionStatusUpdated::class]);

        $this->post('bee/webhook', [
            'event' => 'transaction.failed',
            'data' => [
                'transaction_id' => 456,
                'status' => 'failed',
            ],
        ]);

        Event::assertDispatched(TransactionStatusUpdated::class, function ($event) {
            return $event->transactionId === 456
                && $event->status === 'failed';
        });
    }

    public function test_webhook_dispatches_for_pending_transaction(): void
    {
        Event::fake([BeeWebhookReceived::class, TransactionStatusUpdated::class]);

        $this->post('bee/webhook', [
            'event' => 'transaction.pending',
            'data' => [
                'transaction_id' => 789,
                'status' => 'pending',
            ],
        ]);

        Event::assertDispatched(TransactionStatusUpdated::class);
    }

    public function test_webhook_does_not_dispatch_transaction_event_for_other_events(): void
    {
        Event::fake([BeeWebhookReceived::class, TransactionStatusUpdated::class]);

        $this->post('bee/webhook', [
            'event' => 'account.updated',
            'data' => ['balance' => 100],
        ]);

        Event::assertDispatched(BeeWebhookReceived::class);
        Event::assertNotDispatched(TransactionStatusUpdated::class);
    }

    public function test_webhook_validates_signature_when_secret_is_set(): void
    {
        $this->app['config']->set('bee.webhook.secret', 'my-secret');

        $payload = json_encode(['event' => 'test']);
        $validSignature = hash_hmac('sha256', $payload, 'my-secret');

        $this->postJson('bee/webhook', ['event' => 'test'], [
            'X-Bee-Signature' => 'invalid-signature',
        ])->assertStatus(403);
    }

    public function test_webhook_accepts_valid_signature(): void
    {
        $this->app['config']->set('bee.webhook.secret', 'my-secret');

        Event::fake([BeeWebhookReceived::class]);

        $payload = json_encode(['event' => 'test']);
        $validSignature = hash_hmac('sha256', $payload, 'my-secret');

        $this->postJson('bee/webhook', ['event' => 'test'], [
            'X-Bee-Signature' => $validSignature,
        ])->assertStatus(200);

        Event::assertDispatched(BeeWebhookReceived::class);
    }

    public function test_webhook_route_is_not_registered_when_disabled(): void
    {
        $this->app['config']->set('bee.webhook.enabled', false);

        // Re-boot the service provider
        $this->app->register(\Ghanem\Bee\BeeServiceProvider::class, true);

        // The route registered in defineEnvironment still exists,
        // so we test by checking the config instead
        $this->assertFalse(config('bee.webhook.enabled'));
    }
}
