<?php

declare(strict_types=1);

namespace Tratto\Tests\Fake;

use Tratto\Http\Transport;

/**
 * Records every request and answers with queued responses (default: 200 {"data":{}}).
 */
final class FakeTransport implements Transport
{
    /** @var list<array{method: string, url: string, headers: array<string, string>, body: ?string, timeout: float}> */
    public array $requests = [];

    /** @var list<array{status: int, body: string}> */
    private array $responses = [];

    /** @param array<mixed>|string $body arrays are JSON-encoded */
    public function queue(int $status, array|string $body = ''): self
    {
        $this->responses[] = ['status' => $status, 'body' => is_array($body) ? json_encode($body, JSON_THROW_ON_ERROR) : $body];

        return $this;
    }

    public function send(string $method, string $url, array $headers, ?string $body, float $timeout): array
    {
        $this->requests[] = compact('method', 'url', 'headers', 'body', 'timeout');

        return array_shift($this->responses) ?? ['status' => 200, 'body' => '{"data":{}}'];
    }

    /** @return array{method: string, url: string, headers: array<string, string>, body: ?string, timeout: float} */
    public function last(): array
    {
        return $this->requests[array_key_last($this->requests) ?? throw new \LogicException('no request sent')];
    }
}
