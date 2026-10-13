<?php

/**
 * A webhook endpoint in plain PHP. Point a Tratto webhook at this script, for example:
 *
 *   TRATTO_WEBHOOK_SECRET=whsec_... php -S 0.0.0.0:8080 examples/webhook-endpoint.php
 *
 * The secret comes from the environment, never from the code.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Tratto\Webhook;

$secret = getenv('TRATTO_WEBHOOK_SECRET');
if ($secret === false || $secret === '') {
    http_response_code(500);
    error_log('TRATTO_WEBHOOK_SECRET is not set');
    exit;
}

// The raw body, exactly as received: a re-encoded JSON would not match the signature.
$payload = (string) file_get_contents('php://input');
$header = $_SERVER['HTTP_X_TRATTO_SIGNATURE'] ?? null;

if (!Webhook::verify($payload, is_string($header) ? $header : null, $secret)) {
    http_response_code(400);
    exit;
}

$event = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
// Deliveries can repeat: use $event['id'] to process each event once.
error_log(sprintf('Tratto event %s: %s', $event['id'] ?? '?', $event['type'] ?? '?'));

http_response_code(204);
