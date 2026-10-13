<?php

declare(strict_types=1);

namespace Tratto\Http;

use Tratto\TrattoException;

/**
 * Sends one HTTP request. Replace it (Client option "transport") to plug in another client or a fake in tests.
 */
interface Transport
{
    /**
     * @param array<string, string> $headers
     *
     * @return array{status: int, body: string}
     *
     * @throws TrattoException with errorCode "connection_error" when no response was received
     */
    public function send(string $method, string $url, array $headers, ?string $body, float $timeout): array;
}
