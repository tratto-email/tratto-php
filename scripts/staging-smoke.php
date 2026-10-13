<?php

/**
 * Smoke test: one real round trip against the staging API, in test mode. Run it by hand before a release;
 * it is never run in CI.
 *
 *   TRATTO_API_KEY=tratto_test_... TRATTO_FROM_EMAIL=you@example.com php scripts/staging-smoke.php
 *
 * It sends one email to delivered@simulator.tratto.email, reads its status until it is final, and lists
 * contacts. Safety:
 * - it refuses any key that is not a test key (tratto_test_...): nothing can reach a real recipient;
 * - it targets https://api-staging.tratto.email unless TRATTO_BASE_URL says otherwise, and refuses
 *   api.tratto.email (production) unless --allow-production is passed;
 * - it never prints the key.
 *
 * Exit codes: 0 passed, 1 failed, 2 refused or misconfigured.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Tratto\Client;
use Tratto\TrattoException;

const DEFAULT_BASE_URL = 'https://api-staging.tratto.email';
const PRODUCTION_HOST = 'api.tratto.email';
const RECIPIENT = 'delivered@simulator.tratto.email';
const FINAL_STATUSES = ['delivered', 'failed', 'bounced'];

function smoke_stop(string $message, int $code): never
{
    fwrite(STDERR, $message . "\n");
    exit($code);
}

$apiKey = (string) getenv('TRATTO_API_KEY');
$from = (string) getenv('TRATTO_FROM_EMAIL');
$baseUrl = getenv('TRATTO_BASE_URL') ?: DEFAULT_BASE_URL;
$arguments = is_array($_SERVER['argv'] ?? null) ? array_slice($_SERVER['argv'], 1) : [];
$allowProduction = in_array('--allow-production', $arguments, true);

if ($apiKey === '') {
    smoke_stop('Missing TRATTO_API_KEY.', 2);
}
if (!str_starts_with($apiKey, 'tratto_test_')) {
    smoke_stop('TRATTO_API_KEY is not a test-mode key (tratto_test_...). Refusing: only a test key guarantees that nothing reaches a real recipient.', 2);
}
if ($from === '') {
    smoke_stop('Missing TRATTO_FROM_EMAIL (with a test key any syntactically valid sender works).', 2);
}
$host = strtolower(rtrim((string) parse_url($baseUrl, PHP_URL_HOST), '.'));
if ($host === PRODUCTION_HOST && !$allowProduction) {
    smoke_stop("Refusing to run against production ({$baseUrl}). Use staging, or pass --allow-production on purpose.", 2);
}

echo "Smoke test against {$baseUrl} (test mode)\n";

try {
    $tratto = new Client($apiKey, ['baseUrl' => $baseUrl]);

    $sent = $tratto->emails->send([
        'from' => $from,
        'to' => RECIPIENT,
        'subject' => 'tratto-php smoke test ' . gmdate('c'),
        'html' => '<p>tratto-php smoke test</p>',
    ], 'tratto-php-smoke-' . bin2hex(random_bytes(8)));
    $id = (string) $sent['id'];
    echo "1/3 sent        {$id}\n";

    $status = '';
    for ($attempt = 0; $attempt < 30; $attempt++) {
        $status = (string) $tratto->emails->get($id)['status'];
        if (in_array($status, FINAL_STATUSES, true)) {
            break;
        }
        sleep(2);
    }
    echo "2/3 status      {$status}\n";
    if ($status !== 'delivered') {
        smoke_stop("Expected status delivered for {$id}, got '{$status}'.", 1);
    }

    $contacts = $tratto->contacts->list(['limit' => 5]);
    echo '3/3 contacts    ' . count($contacts['data']) . ' listed, hasMore: ' . var_export($contacts['pagination']['hasMore'] ?? null, true) . "\n";
} catch (TrattoException $e) {
    smoke_stop(sprintf('FAILED [%d] %s: %s', $e->statusCode, $e->errorCode, $e->getMessage()), 1);
}

echo "Smoke test passed.\n";
