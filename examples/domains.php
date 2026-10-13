<?php

/**
 * Domains: add a sending domain, print its DNS records, verify it.
 *
 *   TRATTO_API_KEY=tratto_live_... php examples/domains.php
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Tratto\Client;

run_example(static function (Client $tratto): void {
    $domain = $tratto->domains->add('mail.acme.com');
    $id = (string) $domain['id'];

    echo "Publish these DNS records:\n";
    foreach ($domain['records'] as $record) {
        echo "  {$record['type']}  {$record['host']}\n        {$record['value']}\n";
    }

    // DNS takes minutes to hours: call verify() again later instead of waiting here.
    $verified = $tratto->domains->verify($id);
    echo "Status: {$verified['status']}\n";

    foreach ($tratto->domains->list(['limit' => 20])['data'] as $item) {
        echo "  {$item['domain']}  {$item['status']}\n";
    }

    $deleted = $tratto->domains->delete($id);
    echo "Deleted at {$deleted['deletedAt']}\n";
});
