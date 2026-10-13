<?php

declare(strict_types=1);

namespace Tratto\Tests\Resources;

use PHPUnit\Framework\Attributes\Test;

final class AudiencesTest extends ResourceTestCase
{
    #[Test]
    public function createPostsTheParams(): void
    {
        $this->fake->queue(201, ['data' => ['id' => 'aud_1']]);

        self::assertSame(['id' => 'aud_1'], $this->client->audiences->create(['name' => 'Newsletter']));
        $this->assertRequest('POST', '/v1/audiences', '{"name":"Newsletter"}');
    }

    #[Test]
    public function listReturnsTheWholeEnvelopeAndBuildsTheQuery(): void
    {
        $page = ['data' => [['id' => 'aud_1']], 'pagination' => ['hasMore' => true, 'nextCursor' => 'aud_1']];
        $this->fake->queue(200, $page);

        self::assertSame($page, $this->client->audiences->list(['after' => 'aud_0', 'limit' => 5]));
        $this->assertRequest('GET', '/v1/audiences?after=aud_0&limit=5', null);

        $this->client->audiences->list();
        $this->assertRequest('GET', '/v1/audiences', null);
    }

    #[Test]
    public function getUnwrapsDataAndEncodesTheId(): void
    {
        $this->fake->queue(200, ['data' => ['id' => 'aud?1', 'name' => 'N']]);

        self::assertSame(['id' => 'aud?1', 'name' => 'N'], $this->client->audiences->get('aud?1'));
        $this->assertRequest('GET', '/v1/audiences/aud%3F1', null);
    }

    #[Test]
    public function addContactsPostsTheIds(): void
    {
        $this->fake->queue(200, ['data' => ['added' => 2, 'alreadyInAudience' => 0, 'notFound' => 0]]);

        self::assertSame(['added' => 2, 'alreadyInAudience' => 0, 'notFound' => 0], $this->client->audiences->addContacts('aud_1', ['ct_1', 'ct_2']));
        $this->assertRequest('POST', '/v1/audiences/aud_1/contacts', '{"contactIds":["ct_1","ct_2"]}');
    }

    /** contactIds is always a JSON array: keys from the caller (e.g. after array_filter) would make it an object. */
    #[Test]
    public function addContactsAlwaysSendsAList(): void
    {
        // @phpstan-ignore argument.type (a filtered array keeps its keys)
        $this->client->audiences->addContacts('aud_1', [1 => 'ct_2', 5 => 'ct_6']);
        $this->assertRequest('POST', '/v1/audiences/aud_1/contacts', '{"contactIds":["ct_2","ct_6"]}');

        $this->client->audiences->addContacts('aud_1', []);
        $this->assertRequest('POST', '/v1/audiences/aud_1/contacts', '{"contactIds":[]}');
    }

    #[Test]
    public function exposesExactlyTheNodeMethods(): void
    {
        self::assertPublicMethods($this->client->audiences, ['addContacts', 'create', 'get', 'list']);
    }
}
