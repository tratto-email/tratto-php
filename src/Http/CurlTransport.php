<?php

declare(strict_types=1);

namespace Tratto\Http;

use Tratto\TrattoException;

/**
 * Default transport on ext-curl. TLS verification is always on and redirects are never followed
 * (a redirect would carry the Authorization header to another host). There is deliberately no way
 * to pass extra curl options: to change behaviour, provide your own Transport.
 */
final class CurlTransport implements Transport
{
    private const CONNECT_TIMEOUT = 10.0;

    /**
     * @throws TrattoException           with errorCode "connection_error" when no response was received
     * @throws \InvalidArgumentException when method or url is empty
     */
    public function send(string $method, string $url, array $headers, ?string $body, float $timeout): array
    {
        if ($method === '' || $url === '') {
            throw new \InvalidArgumentException('method and url are required');
        }
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => (int) ceil($timeout * 1000),
            CURLOPT_CONNECTTIMEOUT_MS => (int) ceil(min(self::CONNECT_TIMEOUT, $timeout) * 1000),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_FAILONERROR => false,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        if (!is_string($response)) {
            throw new TrattoException('Connection error: ' . curl_error($ch), 'connection_error', 0);
        }

        return ['status' => curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'body' => $response];
    }
}
