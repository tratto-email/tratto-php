<?php

/**
 * Audiences: rule-based segments, plus contacts added by id.
 *
 *   TRATTO_API_KEY=tratto_test_... php examples/audiences.php
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Tratto\Client;

run_example(static function (Client $tratto): void {
    $created = $tratto->audiences->create([
        'name' => 'Pro plan, tagged vip',
        'rules' => [
            ['field' => 'status', 'operator' => 'equals', 'value' => 'subscribed'],
            ['field' => 'tags', 'operator' => 'array_contains', 'value' => 'vip'],
        ],
    ]);
    $id = (string) $created['id'];

    $audience = $tratto->audiences->get($id);
    echo "{$audience['name']}: {$audience['contactCount']} contacts\n";

    // From 1 to 500 contact ids per call.
    $contacts = $tratto->contacts->list(['status' => 'subscribed', 'limit' => 3]);
    $ids = array_values(array_map(static fn(array $contact): string => (string) $contact['id'], $contacts['data']));
    if ($ids !== []) {
        $result = $tratto->audiences->addContacts($id, $ids);
        echo "Added {$result['added']}, already there {$result['alreadyInAudience']}, not found {$result['notFound']}\n";
    }

    foreach ($tratto->audiences->list(['limit' => 20])['data'] as $item) {
        echo "  {$item['id']}  {$item['name']}\n";
    }
});
