<?php

/**
 * Send transactional emails (HTML, markdown, template, idempotency key) and follow the delivery timeline.
 *
 *   TRATTO_API_KEY=tratto_test_... php examples/send-email.php
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Tratto\Client;

run_example(static function (Client $tratto): void {
    // 1. Plain HTML. With a test key, any sender works and nothing is delivered.
    $sent = $tratto->emails->send([
        'from' => 'Acme <hello@mail.acme.com>',
        'to' => 'delivered@simulator.tratto.email',
        'subject' => 'Welcome to Acme!',
        'html' => '<h1>Welcome!</h1><p>Thanks for joining.</p>',
        'text' => 'Welcome! Thanks for joining.',
    ]);
    echo "Sent: {$sent['id']}\n";

    // 2. emailmd markdown, rendered server-side into responsive HTML (mutually exclusive with html).
    $tratto->emails->send([
        'from' => 'Acme <hello@mail.acme.com>',
        'to' => 'delivered@simulator.tratto.email',
        'subject' => 'Welcome!',
        'markdown' => "# Welcome, {{firstName}}\n\nGlad to have you on board.",
        'variables' => ['firstName' => 'Alice'],
    ]);

    // 3. A saved template, with an idempotency key: retrying with the same key never sends twice.
    $tratto->emails->send([
        'from' => 'hello@mail.acme.com',
        'to' => ['alice@example.com', 'bob@example.com'],
        'subject' => 'Your receipt',
        'templateId' => 'tmpl_abc123',
        'variables' => ['name' => 'Alice', 'amount' => '49.00 EUR'],
        // Schedule for later: a \DateTimeInterface is sent in UTC.
        'scheduledAt' => new DateTimeImmutable('+1 hour'),
    ], 'receipt-order-1234');

    // 4. Status and timeline.
    $email = $tratto->emails->get((string) $sent['id']);
    echo "Status: {$email['status']}\n";
    foreach ($tratto->emails->listEvents((string) $sent['id']) as $event) {
        echo "  {$event['occurredAt']}  {$event['type']}\n";
    }

    // 5. The latest delivered emails (cursor pagination: pass nextCursor as "after").
    $page = $tratto->emails->list(['status' => 'delivered', 'limit' => 5]);
    echo count($page['data']) . " delivered, hasMore: " . var_export($page['pagination']['hasMore'], true) . "\n";
});
