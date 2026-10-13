<?php

/**
 * Contacts: create, update, search, CSV import.
 *
 *   TRATTO_API_KEY=tratto_test_... php examples/contacts.php
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Tratto\Client;

run_example(static function (Client $tratto): void {
    $created = $tratto->contacts->create([
        'email' => 'alice@example.com',
        'firstName' => 'Alice',
        'lastName' => 'Smith',
        'tags' => ['vip'],
        'customFields' => ['plan' => 'pro'],
    ]);
    echo "Created contact {$created['id']}\n";

    $tratto->contacts->update((string) $created['id'], ['status' => 'subscribed']);

    // q is a case-insensitive prefix search on the email: compare the address on the results.
    $page = $tratto->contacts->list(['q' => 'alice@example.com']);
    foreach ($page['data'] as $contact) {
        if ($contact['email'] === 'alice@example.com') {
            echo "Found {$contact['id']}\n";
        }
    }

    // CSV import: pass the CSV content (not a path). The API validates it; an empty CSV is a 400.
    $csv = "email,first_name,last_name\nbob@example.com,Bob,Jones\ncarol@example.com,Carol,White\n";
    $job = $tratto->contacts->importCsv($csv);
    echo "Import {$job['jobId']}: {$job['totalRows']} rows\n";

    do {
        sleep(1);
        $status = $tratto->contacts->getImportJob((string) $job['jobId']);
    } while ($status['status'] === 'processing');
    echo "Import {$status['status']}: {$status['processedRows']} ok, {$status['failedRows']} failed\n";
});
