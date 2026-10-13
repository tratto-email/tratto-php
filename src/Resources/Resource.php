<?php

declare(strict_types=1);

namespace Tratto\Resources;

use Tratto\Http\HttpClient;

abstract class Resource
{
    public function __construct(protected readonly HttpClient $http) {}

    /**
     * Returns the whole response (paginated lists: {data, pagination}).
     *
     * @param array<mixed>|null     $body
     * @param array<string, string> $headers
     *
     * @return array<mixed>|null
     */
    protected function request(string $method, string $path, ?array $body = null, array $headers = []): ?array
    {
        return $this->http->request($method, $path, $body, $headers);
    }

    /**
     * Returns the response without its "data" envelope.
     *
     * @param array<mixed>|null     $body
     * @param array<string, string> $headers
     *
     * @return array<mixed>
     */
    protected function requestData(string $method, string $path, ?array $body = null, array $headers = []): array
    {
        return $this->http->request($method, $path, $body, $headers, unwrap: true) ?? [];
    }

    /**
     * Builds "?a=1&b=true". Skips nulls, dates become ISO 8601 UTC, booleans "true"/"false".
     *
     * @param array<string, mixed> $params
     */
    protected static function query(array $params): string
    {
        $out = [];
        foreach ($params as $key => $value) {
            if ($value === null) {
                continue;
            }
            $out[$key] = match (true) {
                is_bool($value) => $value ? 'true' : 'false',
                $value instanceof \DateTimeInterface => HttpClient::date($value),
                is_scalar($value) => $value,
                default => throw new \InvalidArgumentException(sprintf('Query parameter "%s" must be a scalar, a date or null', $key)),
            };
        }

        return $out === [] ? '' : '?' . http_build_query($out, '', '&', PHP_QUERY_RFC3986);
    }

    /** Encodes an id for use as one path segment, so "a/b" or "x?y" cannot change the route. */
    protected static function id(string $id): string
    {
        if ($id === '' || $id === '.' || $id === '..') {
            throw new \InvalidArgumentException('Invalid id: ' . var_export($id, true));
        }

        return rawurlencode($id);
    }
}
