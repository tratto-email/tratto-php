<?php

declare(strict_types=1);

namespace Tratto\Tests\Resources;

use PHPUnit\Framework\Attributes\Test;

final class WebhooksTest extends ResourceTestCase
{
    #[Test]
    public function createPostsTheParamsAndReturnsTheSecret(): void
    {
        $this->fake->queue(201, ['data' => ['id' => 'wh_1', 'secret' => 'whsec_x']]);

        self::assertSame(['id' => 'wh_1', 'secret' => 'whsec_x'], $this->client->webhooks->create(['url' => 'https://a.it/hook', 'events' => ['delivered', 'bounced']]));
        $this->assertRequest('POST', '/v1/webhooks', '{"url":"https://a.it/hook","events":["delivered","bounced"]}');
    }

    #[Test]
    public function listUnwrapsDataWithoutPagination(): void
    {
        $this->fake->queue(200, ['data' => [['id' => 'wh_1', 'status' => 'active']]]);

        self::assertSame([['id' => 'wh_1', 'status' => 'active']], $this->client->webhooks->list());
        $this->assertRequest('GET', '/v1/webhooks', null);
    }

    #[Test]
    public function deleteSendsDeleteAndReturnsNothingOn204(): void
    {
        $this->fake->queue(204, '');

        $this->client->webhooks->delete('wh/1');
        $this->assertRequest('DELETE', '/v1/webhooks/wh%2F1', null);
    }

    #[Test]
    public function listDeliveriesReturnsTheWholeEnvelope(): void
    {
        $page = ['data' => [['id' => 'd_1', 'status' => 'success']], 'pagination' => ['hasMore' => false, 'nextCursor' => null]];
        $this->fake->queue(200, $page);

        self::assertSame($page, $this->client->webhooks->listDeliveries('wh_1'));
        $this->assertRequest('GET', '/v1/webhooks/wh_1/deliveries', null);
    }

    #[Test]
    public function listDeliveriesBuildsTheQuery(): void
    {
        $this->client->webhooks->listDeliveries('wh 1', ['after' => 'd_9', 'limit' => 25, 'skip' => null]);
        $this->assertRequest('GET', '/v1/webhooks/wh%201/deliveries?after=d_9&limit=25', null);
    }

    #[Test]
    public function testPostsAnEmptyObject(): void
    {
        $this->fake->queue(200, ['data' => ['queued' => true]]);

        self::assertSame(['queued' => true], $this->client->webhooks->test('wh_1'));
        $this->assertRequest('POST', '/v1/webhooks/wh_1/test', '{}');
    }

    #[Test]
    public function rotateSecretPostsAnEmptyObject(): void
    {
        $this->fake->queue(200, ['data' => ['secret' => 'whsec_new']]);

        self::assertSame(['secret' => 'whsec_new'], $this->client->webhooks->rotateSecret('wh_1'));
        $this->assertRequest('POST', '/v1/webhooks/wh_1/rotate-secret', '{}');
    }

    #[Test]
    public function exposesExactlyTheNodeMethods(): void
    {
        self::assertPublicMethods($this->client->webhooks, ['create', 'delete', 'list', 'listDeliveries', 'rotateSecret', 'test']);
    }
}
