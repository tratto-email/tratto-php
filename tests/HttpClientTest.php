<?php

declare(strict_types=1);

namespace Tratto\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tratto\Http\HttpClient;
use Tratto\Tests\Fake\FakeTransport;
use Tratto\TrattoException;

final class HttpClientTest extends TestCase
{
    private FakeTransport $fake;
    private HttpClient $http;

    protected function setUp(): void
    {
        $this->fake = new FakeTransport();
        $this->http = new HttpClient('key', 'https://x.test', 30.0, $this->fake);
    }

    private function fail_(int $status, string $body): TrattoException
    {
        $this->fake->queue($status, $body);
        try {
            $this->http->request('GET', '/v1/x', unwrap: true);
        } catch (TrattoException $e) {
            return $e;
        }
        self::fail('expected TrattoException');
    }

    #[Test]
    public function mapsAJsonErrorWithAllFields(): void
    {
        $e = $this->fail_(422, '{"error":{"message":"Bad from","code":"validation_error","docs":"https://docs.tratto.email/errors#validation_error","suggestion":"Verify the domain"}}');

        self::assertSame('Bad from', $e->getMessage());
        self::assertSame('validation_error', $e->errorCode);
        self::assertSame(422, $e->statusCode);
        self::assertSame(422, $e->getCode());
        self::assertSame('https://docs.tratto.email/errors#validation_error', $e->docs);
        self::assertSame('Verify the domain', $e->suggestion);
    }

    #[Test]
    public function mapsAJsonErrorWithoutOptionalFields(): void
    {
        $e = $this->fail_(404, '{"error":{"message":"Not found","code":"not_found"}}');

        self::assertSame('not_found', $e->errorCode);
        self::assertNull($e->docs);
        self::assertNull($e->suggestion);
    }

    /** @return iterable<string, array{int, string}> */
    public static function errorsWithoutDetails(): iterable
    {
        yield 'html from a proxy' => [502, '<html>Bad Gateway</html>'];
        yield 'empty body' => [500, ''];
        yield 'json without error' => [429, '{"message":"slow down"}'];
        yield 'error is a string' => [400, '{"error":"bad"}'];
        yield 'fields of the wrong type' => [403, '{"error":{"message":1,"code":["x"],"docs":false,"suggestion":2}}'];
        yield 'json scalar' => [500, '"oops"'];
        yield 'redirect not followed' => [302, ''];
        yield 'informational' => [199, ''];
    }

    #[Test]
    #[DataProvider('errorsWithoutDetails')]
    public function mapsErrorsWithoutDetails(int $status, string $body): void
    {
        $e = $this->fail_($status, $body);

        self::assertSame('HTTP ' . $status, $e->getMessage());
        self::assertSame('unknown_error', $e->errorCode);
        self::assertSame($status, $e->statusCode);
        self::assertNull($e->docs);
        self::assertNull($e->suggestion);
    }

    /** C5: a 2xx that is not JSON is a TrattoException, never a JsonException. */
    #[Test]
    public function mapsANonJsonSuccessToInvalidResponse(): void
    {
        $e = $this->fail_(200, '<html>captive portal</html>');

        self::assertSame('invalid_response', $e->errorCode);
        self::assertSame(200, $e->statusCode);
    }

    /** C5 on the path that keeps the envelope (paginated lists). */
    #[Test]
    public function mapsANonJsonSuccessToInvalidResponseWithoutUnwrap(): void
    {
        $this->fake->queue(200, '<html>captive portal</html>');
        try {
            $this->http->request('GET', '/v1/emails');
            self::fail('expected TrattoException');
        } catch (TrattoException $e) {
            self::assertSame('invalid_response', $e->errorCode);
            self::assertSame('The API returned a response that is not JSON', $e->getMessage());
        }
    }

    #[Test]
    public function debugInfoRedactsTheKey(): void
    {
        self::assertSame(
            ['apiKey' => '[redacted]', 'baseUrl' => 'https://x.test', 'timeout' => 30.0, 'transport' => FakeTransport::class],
            $this->http->__debugInfo(),
        );
    }

    #[Test]
    public function mapsAJsonScalarSuccessToInvalidResponse(): void
    {
        self::assertSame('invalid_response', $this->fail_(201, 'true')->errorCode);
    }

    #[Test]
    public function mapsAMissingDataEnvelopeToInvalidResponse(): void
    {
        $e = $this->fail_(201, '{"id":"x"}');

        self::assertSame('invalid_response', $e->errorCode);
        self::assertSame(201, $e->statusCode);
        self::assertSame('The API response has no "data" field', $e->getMessage());
    }

    #[Test]
    public function unwrapsDataAndKeepsTheEnvelopeOtherwise(): void
    {
        $this->fake->queue(200, '{"data":[{"id":"a"}],"pagination":{"hasMore":false,"nextCursor":null}}');
        $this->fake->queue(299, '{"data":[{"id":"a"}],"pagination":{"hasMore":false,"nextCursor":null}}');

        self::assertSame([['id' => 'a']], $this->http->request('GET', '/a', unwrap: true));
        self::assertSame(['data' => [['id' => 'a']], 'pagination' => ['hasMore' => false, 'nextCursor' => null]], $this->http->request('GET', '/a'));
    }

    #[Test]
    public function returnsNullOn204EvenWithoutBody(): void
    {
        $this->fake->queue(204, '');
        self::assertNull($this->http->request('DELETE', '/v1/x', unwrap: true));
    }

    #[Test]
    public function encodesBodiesAsJson(): void
    {
        $date = new \DateTime('2026-10-13T12:00:00.123+02:00');
        $this->http->request('POST', '/a', ['to' => ['a@b.it'], 'url' => 'https://x/y', 'name' => 'Zoë', 'scheduledAt' => $date, 'nested' => ['at' => $date], 'tags' => []]);

        self::assertSame('{"to":["a@b.it"],"url":"https://x/y","name":"Zoë","scheduledAt":"2026-10-13T10:00:00.123Z","nested":{"at":"2026-10-13T10:00:00.123Z"},"tags":[]}', $this->fake->last()['body']);
        self::assertSame('application/json', $this->fake->last()['headers']['Content-Type']);
        self::assertSame('+02:00', $date->getTimezone()->getName(), 'the caller\'s date must not be mutated');
    }

    #[Test]
    public function encodesAnEmptyBodyAsAnObject(): void
    {
        $this->http->request('POST', '/a', []);
        self::assertSame('{}', $this->fake->last()['body']);
    }

    #[Test]
    public function sendsRawBodiesWithTheirContentType(): void
    {
        $this->http->request('POST', '/a', "email\na@b.it\n", contentType: 'text/csv');
        self::assertSame("email\na@b.it\n", $this->fake->last()['body']);
        self::assertSame('text/csv', $this->fake->last()['headers']['Content-Type']);
    }

    #[Test]
    public function extraHeadersCannotOverrideAuthorization(): void
    {
        $this->http->request('GET', '/a', null, ['Authorization' => 'Bearer other', 'X-Extra' => '1']);
        self::assertSame('Bearer key', $this->fake->last()['headers']['Authorization']);
        self::assertSame('1', $this->fake->last()['headers']['X-Extra']);
        self::assertArrayNotHasKey('Content-Type', $this->fake->last()['headers']);
    }

    #[Test]
    public function formatsDatesInUtcWithMilliseconds(): void
    {
        self::assertSame('2026-10-13T10:00:00.123Z', HttpClient::date(new \DateTimeImmutable('2026-10-13T12:00:00.123456+02:00')));
        self::assertSame('2026-01-01T00:00:00.000Z', HttpClient::date(new \DateTimeImmutable('2025-12-31T19:00:00', new \DateTimeZone('America/New_York'))));
    }
}
