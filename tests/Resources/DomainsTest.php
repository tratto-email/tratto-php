<?php

declare(strict_types=1);

namespace Tratto\Tests\Resources;

use PHPUnit\Framework\Attributes\Test;

final class DomainsTest extends ResourceTestCase
{
    #[Test]
    public function addPostsTheDomain(): void
    {
        $domain = ['id' => 'dom_1', 'domain' => 'mail.x.it', 'status' => 'pending', 'records' => []];
        $this->fake->queue(201, ['data' => $domain]);

        self::assertSame($domain, $this->client->domains->add('mail.x.it'));
        $this->assertRequest('POST', '/v1/domains', '{"domain":"mail.x.it"}');
    }

    #[Test]
    public function listReturnsTheWholeEnvelopeAndBuildsTheQuery(): void
    {
        $page = ['data' => [['id' => 'dom_1']], 'pagination' => ['hasMore' => false, 'nextCursor' => null]];
        $this->fake->queue(200, $page);

        self::assertSame($page, $this->client->domains->list());
        $this->assertRequest('GET', '/v1/domains', null);

        $this->client->domains->list(['after' => 'dom_1', 'limit' => 100]);
        $this->assertRequest('GET', '/v1/domains?after=dom_1&limit=100', null);
    }

    #[Test]
    public function getUnwrapsDataAndEncodesTheId(): void
    {
        $this->fake->queue(200, ['data' => ['id' => 'dom#1']]);

        self::assertSame(['id' => 'dom#1'], $this->client->domains->get('dom#1'));
        $this->assertRequest('GET', '/v1/domains/dom%231', null);
    }

    #[Test]
    public function verifyPostsAnEmptyObject(): void
    {
        $this->fake->queue(200, ['data' => ['id' => 'dom_1', 'status' => 'verified']]);

        self::assertSame(['id' => 'dom_1', 'status' => 'verified'], $this->client->domains->verify('dom_1'));
        $this->assertRequest('POST', '/v1/domains/dom_1/verify', '{}');
    }

    /** delete returns {id, deletedAt} (data envelope), unlike webhooks->delete which is a 204. */
    #[Test]
    public function deleteReturnsIdAndDeletedAt(): void
    {
        $this->fake->queue(200, ['data' => ['id' => 'dom_1', 'deletedAt' => '2026-10-13T10:00:00.000Z']]);

        self::assertSame(['id' => 'dom_1', 'deletedAt' => '2026-10-13T10:00:00.000Z'], $this->client->domains->delete('dom_1'));
        $this->assertRequest('DELETE', '/v1/domains/dom_1', null);
    }

    #[Test]
    public function exposesExactlyTheNodeMethods(): void
    {
        self::assertPublicMethods($this->client->domains, ['add', 'delete', 'get', 'list', 'verify']);
    }
}
