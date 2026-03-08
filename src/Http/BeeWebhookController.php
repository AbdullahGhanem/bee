<?php

namespace Ghanem\Bee\Http;

use Ghanem\Bee\Events\BeeWebhookReceived;
use Ghanem\Bee\Events\TransactionStatusUpdated;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class BeeWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        $secret = config('bee.webhook.secret');

        if ($secret && $request->header('X-Bee-Signature') !== hash_hmac('sha256', $request->getContent(), $secret)) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $event = $payload['event'] ?? 'unknown';

        BeeWebhookReceived::dispatch($event, $payload);

        if (in_array($event, ['transaction.completed', 'transaction.failed', 'transaction.pending'])) {
            TransactionStatusUpdated::dispatch(
                $payload['data']['transaction_id'] ?? $payload['transaction_id'] ?? 0,
                $payload['data']['status'] ?? $event,
                $payload,
            );
        }

        return response()->json(['status' => 'ok']);
    }
}
