<?php

declare(strict_types=1);

namespace Tratto\Tests\Resources;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tratto\Client;
use Tratto\Tests\Fake\FakeTransport;
use Tratto\TrattoException;

final class EmailsTest extends TestCase
{
    private FakeTransport $fake;
    private Client $client;

    protected function setUp(): void
    {
        $this->fake = new FakeTransport();
        $this->client = new Client('key', ['transport' => $this->fake]);
    }

    #[Test]
    public function sendPostsTheParamsAndUnwrapsData(): void
    {
        $this->fake->queue(201, ['data' => ['id' => 'em_1']]);
        $result = $this->client->emails->send(['from' => 'a@x.it', 'to' => ['b@y.it'], 'subject' => 'Hi', 'html' => '<p>Hi</p>']);

        self::assertSame(['id' => 'em_1'], $result);
        $request = $this->fake->last();
        self::assertSame('POST', $request['method']);
        self::assertSame('https://api.tratto.email/v1/emails', $request['url']);
        self::assertSame('{"from":"a@x.it","to":["b@y.it"],"subject":"Hi","html":"<p>Hi</p>"}', $request['body']);
        self::assertSame('application/json', $request['headers']['Content-Type']);
        self::assertArrayNotHasKey('Idempotency-Key', $request['headers']);
    }

    #[Test]
    public function sendAddsTheIdempotencyKey(): void
    {
        $this->client->emails->send(['to' => 'b@y.it'], 'order-42');
        self::assertSame('order-42', $this->fake->last()['headers']['Idempotency-Key']);
    }

    #[Test]
    public function sendIgnoresAnEmptyIdempotencyKeyLikeNode(): void
    {
        $this->client->emails->send(['to' => 'b@y.it'], '');
        self::assertArrayNotHasKey('Idempotency-Key', $this->fake->last()['headers']);
    }

    #[Test]
    public function sendSerializesScheduledAtInUtc(): void
    {
        $this->client->emails->send(['to' => 'b@y.it', 'scheduledAt' => new \DateTimeImmutable('2026-12-24T09:00:00+01:00')]);
        self::assertSame('{"to":"b@y.it","scheduledAt":"2026-12-24T08:00:00.000Z"}', $this->fake->last()['body']);
    }

    #[Test]
    public function sendThrowsTheApiError(): void
    {
        $this->fake->queue(409, ['error' => ['message' => 'Duplicate', 'code' => 'idempotency_conflict']]);
        $this->expectException(TrattoException::class);
        $this->expectExceptionMessage('Duplicate');
        $this->client->emails->send(['to' => 'b@y.it'], 'k');
    }

    #[Test]
    public function listReturnsTheWholeEnvelope(): void
    {
        $page = ['data' => [['id' => 'em_1']], 'pagination' => ['hasMore' => true, 'nextCursor' => 'em_1']];
        $this->fake->queue(200, $page);

        self::assertSame($page, $this->client->emails->list());
        self::assertSame('GET', $this->fake->last()['method']);
        self::assertSame('https://api.tratto.email/v1/emails', $this->fake->last()['url']);
        self::assertNull($this->fake->last()['body']);
    }

    #[Test]
    public function listBuildsTheQuery(): void
    {
        $this->client->emails->list([
            'after' => 'em_9',
            'limit' => 50,
            'status' => 'delivered',
            'domainId' => null,
            'tags' => 'welcome',
            'dateFrom' => new \DateTimeImmutable('2026-10-01T00:00:00Z'),
            'dateTo' => '2026-10-13',
        ]);

        self::assertSame(
            'https://api.tratto.email/v1/emails?after=em_9&limit=50&status=delivered&tags=welcome&dateFrom=2026-10-01T00%3A00%3A00.000Z&dateTo=2026-10-13',
            $this->fake->last()['url'],
        );
    }

    #[Test]
    public function getUnwrapsDataAndEncodesTheId(): void
    {
        $this->fake->queue(200, ['data' => ['id' => 'a/b', 'status' => 'sent']]);

        self::assertSame(['id' => 'a/b', 'status' => 'sent'], $this->client->emails->get('a/b'));
        self::assertSame('GET', $this->fake->last()['method']);
        self::assertSame('https://api.tratto.email/v1/emails/a%2Fb', $this->fake->last()['url']);
    }

    #[Test]
    public function listEventsUnwrapsData(): void
    {
        $this->fake->queue(200, ['data' => [['type' => 'delivered']]]);

        self::assertSame([['type' => 'delivered']], $this->client->emails->listEvents('em 1'));
        self::assertSame('GET', $this->fake->last()['method']);
        self::assertSame('https://api.tratto.email/v1/emails/em%201/events', $this->fake->last()['url']);
    }
}
