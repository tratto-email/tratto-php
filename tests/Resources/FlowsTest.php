<?php

declare(strict_types=1);

namespace Tratto\Tests\Resources;

use PHPUnit\Framework\Attributes\Test;

final class FlowsTest extends ResourceTestCase
{
    #[Test]
    public function listReturnsTheWholeEnvelopeAndBuildsTheQuery(): void
    {
        $page = ['data' => [['id' => 'flw_1']], 'pagination' => ['hasMore' => false, 'nextCursor' => null]];
        $this->fake->queue(200, $page);

        self::assertSame($page, $this->client->flows->list());
        $this->assertRequest('GET', '/v1/flows', null);

        $this->client->flows->list(['after' => 'flw_0', 'limit' => 3]);
        $this->assertRequest('GET', '/v1/flows?after=flw_0&limit=3', null);
    }

    #[Test]
    public function createGetAndUpdate(): void
    {
        $this->fake->queue(201, ['data' => ['id' => 'flw_1']]);
        self::assertSame(['id' => 'flw_1'], $this->client->flows->create(['name' => 'Welcome']));
        $this->assertRequest('POST', '/v1/flows', '{"name":"Welcome"}');

        $this->fake->queue(200, ['data' => ['id' => 'flw_1', 'status' => 'draft']]);
        self::assertSame(['id' => 'flw_1', 'status' => 'draft'], $this->client->flows->get('flw_1'));
        $this->assertRequest('GET', '/v1/flows/flw_1', null);

        $this->fake->queue(200, ['data' => ['id' => 'flw_1', 'name' => 'New']]);
        self::assertSame(['id' => 'flw_1', 'name' => 'New'], $this->client->flows->update('flw_1', ['name' => 'New']));
        $this->assertRequest('PATCH', '/v1/flows/flw_1', '{"name":"New"}');
    }

    /** delete returns {id} in a data envelope (flows.ts:28-30), not a 204. */
    #[Test]
    public function deleteReturnsTheId(): void
    {
        $this->fake->queue(200, ['data' => ['id' => 'flw_1']]);

        self::assertSame(['id' => 'flw_1'], $this->client->flows->delete('flw_1'));
        $this->assertRequest('DELETE', '/v1/flows/flw_1', null);
    }

    #[Test]
    public function activateAndDeactivatePostAnEmptyObject(): void
    {
        $this->fake->queue(200, ['data' => ['id' => 'flw_1', 'status' => 'active']]);
        self::assertSame(['id' => 'flw_1', 'status' => 'active'], $this->client->flows->activate('flw_1'));
        $this->assertRequest('POST', '/v1/flows/flw_1/activate', '{}');

        $this->fake->queue(200, ['data' => ['id' => 'flw_1', 'status' => 'inactive']]);
        self::assertSame(['id' => 'flw_1', 'status' => 'inactive'], $this->client->flows->deactivate('flw_1'));
        $this->assertRequest('POST', '/v1/flows/flw_1/deactivate', '{}');
    }
}
