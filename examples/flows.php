<?php

/**
 * Flows: an automation with a trigger and steps.
 *
 * Flows have no granular scopes (no flows:read or flows:write): every call needs an API key with the
 * "*" permission. Create one for the job and revoke it right after. Activation needs a live key.
 *
 *   TRATTO_API_KEY=tratto_live_... php examples/flows.php
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Tratto\Client;

run_example(static function (Client $tratto): void {
    $created = $tratto->flows->create(['name' => 'Welcome series']);
    $id = (string) $created['id'];

    $tratto->flows->update($id, [
        // tagName: without it the flow enrolls on any tag change.
        'trigger' => ['type' => 'contact_tag_added', 'config' => ['tagName' => 'signed-up']],
        'steps' => [
            ['id' => 'step_1', 'type' => 'send_email', 'config' => ['templateId' => 'tmpl_welcome']],
            // delay and unit (seconds, minutes, hours, days); without them the wait is 5 minutes.
            ['id' => 'step_2', 'type' => 'wait', 'config' => ['delay' => '2', 'unit' => 'days']],
            ['id' => 'step_3', 'type' => 'send_email', 'config' => ['templateId' => 'tmpl_tips']],
        ],
    ]);

    // Rejected with 403 TEST_MODE_NOT_SUPPORTED on a test key.
    $active = $tratto->flows->activate($id);
    echo "Status: {$active['status']}\n";

    $tratto->flows->deactivate($id);

    foreach ($tratto->flows->list(['limit' => 20])['data'] as $flow) {
        echo "  {$flow['id']}  {$flow['name']}  [{$flow['status']}]\n";
    }

    $deleted = $tratto->flows->delete($id);
    echo "Deleted {$deleted['id']}\n";
});
