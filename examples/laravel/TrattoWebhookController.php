<?php

/**
 * Laravel: receive and verify Tratto webhooks.
 *
 * Register the route in routes/api.php, which has no CSRF middleware (a webhook carries no CSRF token):
 *
 *     Route::post('/webhooks/tratto', \App\Http\Controllers\TrattoWebhookController::class);
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tratto\Webhook;

final class TrattoWebhookController
{
    public function __invoke(Request $request): Response
    {
        $secret = config('services.tratto.webhook_secret');
        if (!is_string($secret) || $secret === '') {
            return new Response('', 500);
        }

        // getContent() is the raw body: never $request->all() or a re-encoded JSON.
        $payload = $request->getContent();
        $signature = $request->header('X-Tratto-Signature');

        if (!Webhook::verify($payload, is_string($signature) ? $signature : null, $secret)) {
            return new Response('', 400);
        }

        $event = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        // Deliveries can repeat: use $event['id'] to process each event once (e.g. a unique index).
        logger()->info('Tratto event', ['id' => $event['id'] ?? null, 'type' => $event['type'] ?? null]);

        return new Response('', 204);
    }
}
