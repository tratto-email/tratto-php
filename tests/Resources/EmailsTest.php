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
    public function sendExposesTheErrorDetails(): void
    {
        $this->fake->queue(422, ['error' => ['message' => 'Invalid', 'code' => 'VALIDATION_ERROR', 'details' => [['path' => '/to', 'message' => 'Required']]]]);
        try {
            $this->client->emails->send(['subject' => 'Hi']);
            self::fail('expected TrattoException');
        } catch (TrattoException $e) {
            self::assertSame([['path' => '/to', 'message' => 'Required']], $e->details);
        }
    }

    #[Test]
    public function sendErrorWithoutDetailsHasNullDetails(): void
    {
        $this->fake->queue(409, ['error' => ['message' => 'Duplicate', 'code' => 'IDEMPOTENCY_CONFLICT']]);
        try {
            $this->client->emails->send(['to' => 'b@y.it'], 'k');
            self::fail('expected TrattoException');
        } catch (TrattoException $e) {
            self::assertNull($e->details);
        }
    }

    #[Test]
    public function sendBatchPostsAJsonListEvenFromAnAssociativeArray(): void
    {
        $data = ['batchId' => 'batch_1', 'livemode' => true, 'accepted' => 2, 'failed' => 0, 'emails' => [
            ['id' => 'email_1', 'status' => 'queued', 'to' => ['a@x.it']],
            ['id' => 'email_2', 'status' => 'queued', 'to' => ['b@x.it']],
        ]];
        $this->fake->queue(202, ['data' => $data]);

        $result = $this->client->emails->sendBatch(['first' => ['to' => 'a@x.it', 'subject' => 'A'], 'second' => ['to' => 'b@x.it', 'subject' => 'B']]);

        self::assertSame($data, $result);
        self::assertCount(1, $this->fake->requests);
        $request = $this->fake->last();
        self::assertSame('POST', $request['method']);
        self::assertSame('https://api.tratto.email/v1/emails/batch', $request['url']);
        self::assertSame('{"emails":[{"to":"a@x.it","subject":"A"},{"to":"b@x.it","subject":"B"}]}', $request['body']);
        self::assertSame('application/json', $request['headers']['Content-Type']);
        self::assertArrayNotHasKey('Idempotency-Key', $request['headers']);
    }

    #[Test]
    public function sendBatchAddsOneIdempotencyKey(): void
    {
        $this->client->emails->sendBatch([['to' => 'a@x.it']], 'batch-42');
        self::assertSame('batch-42', $this->fake->last()['headers']['Idempotency-Key']);

        $this->client->emails->sendBatch([['to' => 'a@x.it']], '');
        self::assertArrayNotHasKey('Idempotency-Key', $this->fake->last()['headers']);
    }

    #[Test]
    public function sendBatchReturnsAPartialFailureInsteadOfThrowing(): void
    {
        $data = ['batchId' => 'batch_1', 'livemode' => true, 'accepted' => 1, 'failed' => 1, 'emails' => [
            ['id' => 'email_1', 'status' => 'queued', 'to' => ['a@x.it']],
            ['status' => 'failed', 'to' => ['b@x.it'], 'error' => ['code' => 'SERVICE_UNAVAILABLE', 'message' => 'Try again']],
        ]];
        $this->fake->queue(202, ['data' => $data]);

        self::assertSame($data, $this->client->emails->sendBatch([['to' => 'a@x.it'], ['to' => 'b@x.it']]));
        self::assertCount(1, $this->fake->requests);
    }

    #[Test]
    public function sendBatchThrowsOnServiceUnavailableWithoutRetrying(): void
    {
        $this->fake->queue(503, ['error' => ['message' => 'Unavailable', 'code' => 'SERVICE_UNAVAILABLE']]);
        try {
            $this->client->emails->sendBatch([['to' => 'a@x.it']], 'k');
            self::fail('expected TrattoException');
        } catch (TrattoException $e) {
            self::assertSame(503, $e->statusCode);
            self::assertSame('SERVICE_UNAVAILABLE', $e->errorCode);
        }
        self::assertCount(1, $this->fake->requests);
    }

    #[Test]
    public function sendBatchValidationErrorCarriesThePathOfTheBadEmail(): void
    {
        $this->fake->queue(422, ['error' => ['message' => 'Invalid', 'code' => 'VALIDATION_ERROR', 'details' => [['path' => '/emails/1/to', 'message' => 'Required']]]]);
        try {
            $this->client->emails->sendBatch([['to' => 'a@x.it'], ['subject' => 'no recipient']]);
            self::fail('expected TrattoException');
        } catch (TrattoException $e) {
            self::assertSame(422, $e->statusCode);
            self::assertIsArray($e->details);
            self::assertIsArray($e->details[0]);
            self::assertSame('/emails/1/to', $e->details[0]['path']);
        }
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

    #[Test]
    public function sendAndSendBatchPassAttachmentsById(): void
    {
        $this->client->emails->send(['to' => 'b@y.it', 'attachments' => [['id' => 'att_1']]]);
        self::assertSame('{"to":"b@y.it","attachments":[{"id":"att_1"}]}', $this->fake->last()['body']);

        $this->client->emails->sendBatch([['to' => 'a@x.it', 'attachments' => [['id' => 'att_1']]]]);
        self::assertSame('{"emails":[{"to":"a@x.it","attachments":[{"id":"att_1"}]}]}', $this->fake->last()['body']);
    }

    #[Test]
    public function sendSurfacesPlanUpgradeRequired(): void
    {
        $this->fake->queue(403, ['error' => ['code' => 'PLAN_UPGRADE_REQUIRED', 'message' => 'Starter plan']]);
        try {
            $this->client->emails->send(['to' => 'b@y.it', 'attachments' => [['id' => 'att_1']]]);
            self::fail('expected TrattoException');
        } catch (TrattoException $e) {
            self::assertSame(403, $e->statusCode);
            self::assertSame('PLAN_UPGRADE_REQUIRED', $e->errorCode);
        }
    }

    #[Test]
    public function getReturnsAttachmentsAndFailureReason(): void
    {
        $data = [
            'id' => 'email_1', 'status' => 'failed', 'failureReason' => 'attachment_unavailable',
            'attachments' => [['id' => 'att_1', 'filename' => 'a.pdf', 'contentType' => 'application/pdf', 'size' => 3]],
            'events' => [],
        ];
        $this->fake->queue(200, ['data' => $data]);

        self::assertSame($data, $this->client->emails->get('email_1'));
    }
}
