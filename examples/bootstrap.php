<?php

/**
 * Shared setup for the examples: the API key comes from the environment, never from the code.
 *
 *   TRATTO_API_KEY=tratto_test_... php examples/send-email.php
 *
 * TRATTO_BASE_URL is optional (defaults to https://api.tratto.email).
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Tratto\Client;
use Tratto\TrattoException;

function example_client(): Client
{
    $apiKey = getenv('TRATTO_API_KEY');
    if ($apiKey === false || $apiKey === '') {
        fwrite(STDERR, "Set the TRATTO_API_KEY environment variable.\n");
        exit(1);
    }
    $baseUrl = getenv('TRATTO_BASE_URL');

    return new Client($apiKey, $baseUrl === false || $baseUrl === '' ? [] : ['baseUrl' => $baseUrl]);
}

/** Runs an example and prints API errors the way an integration should log them. */
function run_example(callable $example): void
{
    try {
        $example(example_client());
    } catch (TrattoException $e) {
        // Log these fields, not the full trace (see "PHP 8.1" in the README).
        fwrite(STDERR, sprintf("[%d] %s: %s\n", $e->statusCode, $e->errorCode, $e->getMessage()));
        if ($e->suggestion !== null) {
            fwrite(STDERR, 'Suggestion: ' . $e->suggestion . "\n");
        }
        if ($e->docs !== null) {
            fwrite(STDERR, 'Docs: ' . $e->docs . "\n");
        }
        exit(1);
    }
}
