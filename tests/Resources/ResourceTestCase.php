<?php

declare(strict_types=1);

namespace Tratto\Tests\Resources;

use PHPUnit\Framework\TestCase;
use Tratto\Client;
use Tratto\Tests\Fake\FakeTransport;

abstract class ResourceTestCase extends TestCase
{
    protected const BASE = 'https://api.tratto.email';

    protected FakeTransport $fake;
    protected Client $client;

    protected function setUp(): void
    {
        $this->fake = new FakeTransport();
        $this->client = new Client('key', ['transport' => $this->fake]);
    }

    /** Asserts the last request: method, URL, exact body and the matching Content-Type (none without a body). */
    protected function assertRequest(string $method, string $url, ?string $body, string $contentType = 'application/json'): void
    {
        $request = $this->fake->last();
        self::assertSame($method, $request['method']);
        self::assertSame(self::BASE . $url, $request['url']);
        self::assertSame($body, $request['body']);
        self::assertArrayNotHasKey('Idempotency-Key', $request['headers']);
        if ($body === null) {
            self::assertArrayNotHasKey('Content-Type', $request['headers']);
        } else {
            self::assertSame($contentType, $request['headers']['Content-Type']);
        }
    }

    /** @param list<string> $expected sorted */
    protected static function assertPublicMethods(object $resource, array $expected): void
    {
        $methods = array_map(
            static fn(\ReflectionMethod $m): string => $m->getName(),
            (new \ReflectionClass($resource))->getMethods(\ReflectionMethod::IS_PUBLIC),
        );
        $methods = array_values(array_diff($methods, ['__construct']));
        sort($methods);

        self::assertSame($expected, $methods);
    }
}
