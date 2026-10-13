<?php

declare(strict_types=1);

namespace Tratto\Tests\Resources;

use PHPUnit\Framework\Attributes\Test;

final class CampaignsTest extends ResourceTestCase
{
    #[Test]
    public function createPostsTheParams(): void
    {
        $this->fake->queue(201, ['data' => ['id' => 'cmp_1']]);

        self::assertSame(['id' => 'cmp_1'], $this->client->campaigns->create(['name' => 'Oct', 'audienceId' => 'aud_1', 'templateId' => 'tpl_1']));
        $this->assertRequest('POST', '/v1/campaigns', '{"name":"Oct","audienceId":"aud_1","templateId":"tpl_1"}');
    }

    #[Test]
    public function listReturnsTheWholeEnvelopeAndBuildsTheQuery(): void
    {
        $page = ['data' => [['id' => 'cmp_1']], 'pagination' => ['hasMore' => false, 'nextCursor' => null]];
        $this->fake->queue(200, $page);

        self::assertSame($page, $this->client->campaigns->list());
        $this->assertRequest('GET', '/v1/campaigns', null);

        $this->client->campaigns->list(['status' => 'scheduled', 'after' => 'cmp_0', 'limit' => 10]);
        $this->assertRequest('GET', '/v1/campaigns?status=scheduled&after=cmp_0&limit=10', null);
    }

    #[Test]
    public function getAndGetStatsUnwrapData(): void
    {
        $this->fake->queue(200, ['data' => ['id' => 'cmp_1', 'status' => 'sent']]);
        self::assertSame(['id' => 'cmp_1', 'status' => 'sent'], $this->client->campaigns->get('cmp_1'));
        $this->assertRequest('GET', '/v1/campaigns/cmp_1', null);

        $this->fake->queue(200, ['data' => ['sent' => 10, 'opened' => 4]]);
        self::assertSame(['sent' => 10, 'opened' => 4], $this->client->campaigns->getStats('cmp_1'));
        $this->assertRequest('GET', '/v1/campaigns/cmp_1/stats', null);
    }

    #[Test]
    public function sendNowPostsAnEmptyObject(): void
    {
        $this->fake->queue(200, ['data' => ['status' => 'sending']]);

        self::assertSame(['status' => 'sending'], $this->client->campaigns->send('cmp_1'));
        $this->assertRequest('POST', '/v1/campaigns/cmp_1/send', '{}');
    }

    /** scheduledAt as a mutable DateTime in +02:00: sent in UTC with milliseconds, the caller's object unchanged. */
    #[Test]
    public function sendSerializesScheduledAtInUtc(): void
    {
        $at = new \DateTime('2026-12-24T09:00:00.250+02:00');
        $this->client->campaigns->send('cmp_1', ['scheduledAt' => $at]);

        $this->assertRequest('POST', '/v1/campaigns/cmp_1/send', '{"scheduledAt":"2026-12-24T07:00:00.250Z"}');
        self::assertSame('+02:00', $at->getTimezone()->getName());
    }

    #[Test]
    public function sendPassesAStringScheduledAtAsIs(): void
    {
        $this->client->campaigns->send('cmp_1', ['scheduledAt' => '2026-12-24T08:00:00Z']);
        $this->assertRequest('POST', '/v1/campaigns/cmp_1/send', '{"scheduledAt":"2026-12-24T08:00:00Z"}');
    }

    /** Like Node (`if (params?.scheduledAt)`): an empty or null scheduledAt means "now", and only scheduledAt is sent. */
    #[Test]
    public function sendIgnoresEmptyScheduledAtAndOtherParams(): void
    {
        $this->client->campaigns->send('cmp_1', ['scheduledAt' => '']);
        $this->assertRequest('POST', '/v1/campaigns/cmp_1/send', '{}');

        $this->client->campaigns->send('cmp_1', ['scheduledAt' => null]);
        $this->assertRequest('POST', '/v1/campaigns/cmp_1/send', '{}');

        $this->client->campaigns->send('cmp_1', ['scheduledAt' => '2026-12-24T08:00:00Z', 'other' => 1]);
        $this->assertRequest('POST', '/v1/campaigns/cmp_1/send', '{"scheduledAt":"2026-12-24T08:00:00Z"}');
    }

    #[Test]
    public function sendRejectsAScheduledAtOfTheWrongType(): void
    {
        try {
            // @phpstan-ignore argument.type (the point of the test)
            $this->client->campaigns->send('cmp_1', ['scheduledAt' => 1798790400]);
            self::fail('expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('scheduledAt must be a \DateTimeInterface or an ISO 8601 string', $e->getMessage());
        }
        self::assertSame([], $this->fake->requests);
    }

    #[Test]
    public function unscheduleAndPausePostAnEmptyObject(): void
    {
        $this->fake->queue(200, ['data' => ['id' => 'cmp_1', 'status' => 'draft', 'scheduledAt' => null]]);
        self::assertSame(['id' => 'cmp_1', 'status' => 'draft', 'scheduledAt' => null], $this->client->campaigns->unschedule('cmp_1'));
        $this->assertRequest('POST', '/v1/campaigns/cmp_1/unschedule', '{}');

        $this->fake->queue(200, ['data' => ['status' => 'paused']]);
        self::assertSame(['status' => 'paused'], $this->client->campaigns->pause('cmp_1'));
        $this->assertRequest('POST', '/v1/campaigns/cmp_1/pause', '{}');
    }

    #[Test]
    public function testSendPostsTheRecipient(): void
    {
        $this->fake->queue(200, ['data' => ['emailId' => 'em_1']]);

        self::assertSame(['emailId' => 'em_1'], $this->client->campaigns->testSend('cmp_1', 'me@x.it'));
        $this->assertRequest('POST', '/v1/campaigns/cmp_1/test-send', '{"to":"me@x.it"}');
    }

    #[Test]
    public function exposesExactlyTheNodeMethods(): void
    {
        self::assertPublicMethods($this->client->campaigns, ['create', 'get', 'getStats', 'list', 'pause', 'send', 'testSend', 'unschedule']);
    }
}
