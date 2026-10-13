<?php

declare(strict_types=1);

namespace Tratto\Http;

use Tratto\Client;
use Tratto\TrattoException;

/**
 * The only object that holds the API key. Resources share one instance.
 *
 * @internal
 */
final class HttpClient
{
    /** Kept in a closure so that var_export() and print_r() never show the key. */
    private readonly \Closure $apiKey;

    public function __construct(
        #[\SensitiveParameter]
        string $apiKey,
        public readonly string $baseUrl,
        public readonly float $timeout,
        private readonly Transport $transport,
    ) {
        $this->apiKey = static fn(): string => $apiKey;
    }

    /**
     * @param array<mixed>|string|null $body  arrays are sent as JSON ([] becomes {}), strings as they are
     * @param array<string, string>    $headers
     * @param bool                     $unwrap return only the "data" field of the response
     *
     * @return array<mixed>|null the decoded JSON, null for 204
     */
    public function request(
        string $method,
        string $path,
        array|string|null $body = null,
        array $headers = [],
        string $contentType = 'application/json',
        bool $unwrap = false,
    ): ?array {
        // Never write the key into a parameter of this method: exception traces show parameters' current values.
        $allHeaders = [
            'Authorization' => 'Bearer ' . ($this->apiKey)(),
            'User-Agent' => 'tratto-php/' . Client::VERSION . ' php/' . PHP_VERSION,
            'Accept' => 'application/json',
        ] + ($body === null ? [] : ['Content-Type' => $contentType]) + $headers;

        try {
            $response = $this->transport->send(
                $method,
                $this->baseUrl . $path,
                $allHeaders,
                is_array($body) ? self::encode($body) : $body,
                $this->timeout,
            );
        } catch (TrattoException $e) {
            // Re-thrown without $previous: the transport's trace holds the headers, key included.
            throw new TrattoException($e->getMessage(), $e->errorCode, $e->statusCode, $e->docs, $e->suggestion);
        }
        $status = $response['status'];

        if ($status === 204) {
            return null;
        }

        try {
            $json = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $json = null;
        }

        if ($status < 200 || $status > 299) {
            $error = is_array($json) && is_array($json['error'] ?? null) ? $json['error'] : [];

            throw new TrattoException(
                self::str($error, 'message') ?? 'HTTP ' . $status,
                self::str($error, 'code') ?? 'unknown_error',
                $status,
                self::str($error, 'docs'),
                self::str($error, 'suggestion'),
            );
        }

        if (!is_array($json)) {
            throw new TrattoException('The API returned a response that is not JSON', 'invalid_response', $status);
        }
        if (!$unwrap) {
            return $json;
        }
        if (!is_array($json['data'] ?? null)) {
            throw new TrattoException('The API response has no "data" field', 'invalid_response', $status);
        }

        return $json['data'];
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return ['apiKey' => '[redacted]', 'baseUrl' => $this->baseUrl, 'timeout' => $this->timeout, 'transport' => $this->transport::class];
    }

    /** @param array<mixed> $body */
    private static function encode(array $body): string
    {
        return json_encode($body === [] ? new \stdClass() : self::normalize($body), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private static function normalize(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return self::date($value);
        }

        return is_array($value) ? array_map(self::normalize(...), $value) : $value;
    }

    /** ISO 8601 in UTC with milliseconds, like JavaScript's toISOString(). Never mutates the argument. */
    public static function date(\DateTimeInterface $date): string
    {
        return \DateTimeImmutable::createFromInterface($date)
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s.v\Z');
    }

    /** @param array<mixed> $error */
    private static function str(array $error, string $key): ?string
    {
        return is_string($error[$key] ?? null) ? $error[$key] : null;
    }
}
