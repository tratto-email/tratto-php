<?php

/**
 * Campaigns: create, test, schedule, unschedule, pause. Sending and test sends need a live key.
 *
 *   TRATTO_API_KEY=tratto_live_... php examples/campaign.php
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Tratto\Client;

run_example(static function (Client $tratto): void {
    $created = $tratto->campaigns->create([
        'name' => 'June newsletter',
        'templateId' => 'tmpl_abc123',
        'audienceId' => 'aud_abc123',
        'fromName' => 'Acme Newsletter',
        'fromEmail' => 'news@mail.acme.com',
        'subjectA' => 'Our June update is here',
    ]);
    $id = (string) $created['id'];

    $test = $tratto->campaigns->testSend($id, 'team@acme.com');
    echo "Test email {$test['emailId']}\n";

    // Schedule: any \DateTimeInterface, sent in UTC. Without scheduledAt the campaign is sent now.
    $sent = $tratto->campaigns->send($id, ['scheduledAt' => new DateTimeImmutable('next monday 09:00', new DateTimeZone('Europe/Rome'))]);
    echo "Status: {$sent['status']}\n";

    $campaign = $tratto->campaigns->get($id);
    if ($campaign['status'] === 'scheduled') {
        // Back to draft. Answers 409 once the send has started: use pause() then.
        $tratto->campaigns->unschedule($id);
    } else {
        $tratto->campaigns->pause($id);
    }

    $stats = $tratto->campaigns->getStats($id);
    echo json_encode($stats, JSON_PRETTY_PRINT) . "\n";

    echo count($tratto->campaigns->list(['status' => 'scheduled', 'limit' => 10])['data']) . " scheduled campaigns\n";
});
