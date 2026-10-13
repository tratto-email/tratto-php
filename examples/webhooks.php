<?php

/**
 * Webhooks: register an endpoint, test it, read the delivery history, rotate the secret.
 * To receive and verify deliveries, see webhook-endpoint.php.
 *
 *   TRATTO_API_KEY=tratto_test_... php examples/webhooks.php
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Tratto\Client;

run_example(static function (Client $tratto): void {
    $created = $tratto->webhooks->create([
        'url' => 'https://my-app.example.com/webhooks/tratto',
        'events' => ['delivered', 'bounced', 'complained', 'unsubscribed'],
    ]);
    $id = (string) $created['id'];
    // $created['secret'] is returned only now: put it in your secret store
    // (the TRATTO_WEBHOOK_SECRET of your endpoint). Never print or log it.
    echo "Webhook {$id} registered\n";

    foreach ($tratto->webhooks->list() as $hook) {
        echo "  {$hook['id']}  {$hook['url']}  [{$hook['status']}]\n";
    }

    $test = $tratto->webhooks->test($id);
    echo 'Test event queued: ' . var_export($test['queued'], true) . "\n";

    foreach ($tratto->webhooks->listDeliveries($id, ['limit' => 10])['data'] as $delivery) {
        echo "  {$delivery['attemptedAt']}  {$delivery['eventType']}  {$delivery['status']}\n";
    }

    // The new secret replaces the old one: update your endpoint's secret store.
    $tratto->webhooks->rotateSecret($id);

    $tratto->webhooks->delete($id);
});
