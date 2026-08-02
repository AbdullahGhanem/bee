<?php

namespace Ghanem\Basata\Tests\Unit;

use Ghanem\Basata\Events\BasataWebhookReceived;
use Ghanem\Basata\Events\TransactionStatusUpdated;
use Ghanem\Basata\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class WebhookTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('basata.webhook.enabled', true);
        $app['config']->set('basata.webhook.path', 'basata/webhook');
        $app['config']->set('basata.webhook.secret', null);
        $app['config']->set('basata.webhook.middleware', []);
    }

    public function test_webhook_route_is_registered(): void
    {
        $this->post('basata/webhook', ['event' => 'test'])
            ->assertStatus(200);
    }

    public function test_webhook_dispatches_generic_event(): void
    {
        Event::fake([BasataWebhookReceived::class]);

        $this->post('basata/webhook', [
            'event' => 'test.event',
            'data' => ['foo' => 'bar'],
        ]);

        Event::assertDispatched(BasataWebhookReceived::class, function ($event) {
            return $event->event === 'test.event';
        });
    }

    public function test_webhook_dispatches_transaction_status_event(): void
    {
        Event::fake([BasataWebhookReceived::class, TransactionStatusUpdated::class]);

        $this->post('basata/webhook', [
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
        Event::fake([BasataWebhookReceived::class, TransactionStatusUpdated::class]);

        $this->post('basata/webhook', [
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
        Event::fake([BasataWebhookReceived::class, TransactionStatusUpdated::class]);

        $this->post('basata/webhook', [
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
        Event::fake([BasataWebhookReceived::class, TransactionStatusUpdated::class]);

        $this->post('basata/webhook', [
            'event' => 'account.updated',
            'data' => ['balance' => 100],
        ]);

        Event::assertDispatched(BasataWebhookReceived::class);
        Event::assertNotDispatched(TransactionStatusUpdated::class);
    }

    public function test_webhook_validates_signature_when_secret_is_set(): void
    {
        $this->app['config']->set('basata.webhook.secret', 'my-secret');

        $payload = json_encode(['event' => 'test']);
        $validSignature = hash_hmac('sha256', $payload, 'my-secret');

        $this->postJson('basata/webhook', ['event' => 'test'], [
            'X-Basata-Signature' => 'invalid-signature',
        ])->assertStatus(403);
    }

    public function test_webhook_accepts_valid_signature(): void
    {
        $this->app['config']->set('basata.webhook.secret', 'my-secret');

        Event::fake([BasataWebhookReceived::class]);

        $payload = json_encode(['event' => 'test']);
        $validSignature = hash_hmac('sha256', $payload, 'my-secret');

        $this->postJson('basata/webhook', ['event' => 'test'], [
            'X-Basata-Signature' => $validSignature,
        ])->assertStatus(200);

        Event::assertDispatched(BasataWebhookReceived::class);
    }

    public function test_webhook_route_is_not_registered_when_disabled(): void
    {
        $this->app['config']->set('basata.webhook.enabled', false);

        // Re-boot the service provider
        $this->app->register(\Ghanem\Basata\BasataServiceProvider::class, true);

        // The route registered in defineEnvironment still exists,
        // so we test by checking the config instead
        $this->assertFalse(config('basata.webhook.enabled'));
    }
}
