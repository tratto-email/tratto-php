<?php

declare(strict_types=1);

namespace Tratto;

/**
 * Verifies the X-Tratto-Signature header of a webhook delivery.
 *
 * Header: "t=<unix ms>,v1=<hex>" (v1 may repeat during a secret rotation).
 * Signature: HMAC-SHA256 of "{t}.{raw body}" keyed with the whole secret, "whsec_" prefix included.
 *
 * The data of a "failed" event is FailedEventData; reason is the same code as failureReason on emails->get().
 *
 * @phpstan-type FailedEventData array{reason: string, error: string}
 */
final class Webhook
{
    /**
     * Event types a webhook can subscribe to (webhooks->create(['events' => ...])). "failed" (the email was not
     * sent, data {reason, error}) is delivered only to webhooks that list it.
     */
    public const EVENT_TYPES = ['sent', 'delivered', 'opened', 'clicked', 'bounced', 'complained', 'unsubscribed', 'failed'];

    public const DEFAULT_TOLERANCE_SECONDS = 300;

    /**
     * @param string      $payload          the raw request body, e.g. file_get_contents('php://input')
     * @param string|null $header           the X-Tratto-Signature header (null when missing)
     * @param string      $secret           the webhook secret, "whsec_..."
     * @param int         $toleranceSeconds max distance between "t" and now, in the past or in the future
     * @param int|null    $nowMs            current time in milliseconds (for tests)
     *
     * @return bool true only for an authentic, fresh delivery
     *
     * @throws \InvalidArgumentException on an empty secret or a negative tolerance (configuration errors)
     */
    public static function verify(
        string $payload,
        ?string $header,
        #[\SensitiveParameter]
        string $secret,
        int $toleranceSeconds = self::DEFAULT_TOLERANCE_SECONDS,
        ?int $nowMs = null,
    ): bool {
        // Exception traces show the current value of parameters, and PHP 8.1 ignores #[\SensitiveParameter].
        $key = $secret;
        $secret = '[redacted]';
        if ($key === '') {
            throw new \InvalidArgumentException('secret is required');
        }
        if ($toleranceSeconds < 0) {
            throw new \InvalidArgumentException('toleranceSeconds must not be negative');
        }

        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header ?? '') as $part) {
            $pair = explode('=', $part); // a '=' inside a value makes the part invalid: hex and digits never have one
            if (count($pair) !== 2) {
                continue;
            }
            [$name, $value] = [trim($pair[0]), trim($pair[1])];
            if ($name === 't') {
                if ($timestamp !== null) {
                    return false; // two timestamps: ambiguous, never guess
                }
                $timestamp = $value;
            } elseif ($name === 'v1' && $value !== '') {
                $signatures[] = strtolower($value);
            }
        }

        // digits only, no ctype (ext-ctype is not required); 15 digits never overflow an int
        if ($timestamp === null || $signatures === [] || preg_match('/^\d{1,15}\z/', $timestamp) !== 1) {
            return false;
        }

        $now = $nowMs ?? (int) floor(microtime(true) * 1000);
        if (abs($now - (int) $timestamp) > $toleranceSeconds * 1000) {
            return false;
        }

        // signed over the timestamp string exactly as received, never over a re-formatted number
        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $key);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }
}
