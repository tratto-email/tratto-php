<?php

/**
 * Templates: markdown or HTML, versions, test send, delete.
 *
 *   TRATTO_API_KEY=tratto_live_... php examples/templates.php
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Tratto\Client;

run_example(static function (Client $tratto): void {
    // With "markdown" the SDK adds format "emailmd": the API renders the HTML at save time.
    $template = $tratto->templates->create([
        'name' => 'Order confirmation',
        'markdown' => "# Thanks for your order, {{firstName}}\n\nOrder **{{orderId}}** is confirmed.",
    ]);
    $id = (string) $template['id'];

    $tratto->templates->update($id, ['markdown' => "# Thanks, {{firstName}}\n\nOrder **{{orderId}}** is on its way."]);
    $tratto->templates->update($id, ['status' => 'published']);

    foreach ($tratto->templates->listVersions($id) as $version) {
        echo "  v{$version['version']}  saved {$version['savedAt']}\n";
    }
    $first = $tratto->templates->getVersion($id, 1);
    echo 'Version 1 HTML: ' . strlen((string) $first['html']) . " bytes\n";

    // Reaches a real inbox: rejected with 403 TEST_MODE_NOT_SUPPORTED on a test key.
    // variables is always sent as a JSON object, {} when empty.
    $tratto->templates->testSend($id, 'me@acme.com', ['firstName' => 'Alice', 'orderId' => 'A-1234']);

    echo count($tratto->templates->list(['status' => 'published', 'limit' => 20])['data']) . " published templates\n";

    $tratto->templates->delete($id);
});
