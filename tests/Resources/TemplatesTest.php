<?php

declare(strict_types=1);

namespace Tratto\Tests\Resources;

use PHPUnit\Framework\Attributes\Test;

final class TemplatesTest extends ResourceTestCase
{
    #[Test]
    public function listReturnsTheWholeEnvelopeAndBuildsTheQuery(): void
    {
        $page = ['data' => [['id' => 'tpl_1']], 'pagination' => ['hasMore' => false, 'nextCursor' => null]];
        $this->fake->queue(200, $page);

        self::assertSame($page, $this->client->templates->list());
        $this->assertRequest('GET', '/v1/templates', null);

        $this->client->templates->list(['limit' => 5, 'after' => 'tpl_0', 'status' => 'published']);
        $this->assertRequest('GET', '/v1/templates?limit=5&after=tpl_0&status=published', null);
    }

    #[Test]
    public function createWithHtmlSendsTheParamsAsTheyAre(): void
    {
        $this->fake->queue(201, ['data' => ['id' => 'tpl_1', 'format' => 'html']]);

        self::assertSame(['id' => 'tpl_1', 'format' => 'html'], $this->client->templates->create(['name' => 'Welcome', 'html' => '<p>Hi</p>']));
        $this->assertRequest('POST', '/v1/templates', '{"name":"Welcome","html":"<p>Hi</p>"}');
    }

    /** templates.ts:28-29: markdown present → format "emailmd", overriding any format the caller passed. */
    #[Test]
    public function createWithMarkdownAddsTheEmailmdFormat(): void
    {
        $this->client->templates->create(['name' => 'Welcome', 'markdown' => '# Hi']);
        $this->assertRequest('POST', '/v1/templates', '{"name":"Welcome","markdown":"# Hi","format":"emailmd"}');

        $this->client->templates->create(['name' => 'W', 'format' => 'html', 'markdown' => '# Hi']);
        $this->assertRequest('POST', '/v1/templates', '{"name":"W","format":"emailmd","markdown":"# Hi"}');

        // Node checks `!== undefined`: a null markdown still adds the format (the API then decides)
        $this->client->templates->create(['name' => 'W', 'markdown' => null]);
        $this->assertRequest('POST', '/v1/templates', '{"name":"W","markdown":null,"format":"emailmd"}');
    }

    #[Test]
    public function getUpdateAndDelete(): void
    {
        $this->fake->queue(200, ['data' => ['id' => 'tpl_1']]);
        self::assertSame(['id' => 'tpl_1'], $this->client->templates->get('tpl_1'));
        $this->assertRequest('GET', '/v1/templates/tpl_1', null);

        $this->fake->queue(200, ['data' => ['id' => 'tpl_1', 'name' => 'New']]);
        self::assertSame(['id' => 'tpl_1', 'name' => 'New'], $this->client->templates->update('tpl_1', ['name' => 'New', 'markdown' => '# x']));
        $this->assertRequest('PATCH', '/v1/templates/tpl_1', '{"name":"New","markdown":"# x"}');

        $this->fake->queue(204, '');
        $this->client->templates->delete('tpl_1');
        $this->assertRequest('DELETE', '/v1/templates/tpl_1', null);
    }

    #[Test]
    public function listVersionsAndGetVersion(): void
    {
        $this->fake->queue(200, ['data' => [['version' => 2, 'savedAt' => 't2'], ['version' => 1, 'savedAt' => 't1']]]);
        self::assertSame([['version' => 2, 'savedAt' => 't2'], ['version' => 1, 'savedAt' => 't1']], $this->client->templates->listVersions('tpl_1'));
        $this->assertRequest('GET', '/v1/templates/tpl_1/versions', null);

        $this->fake->queue(200, ['data' => ['version' => 2, 'savedAt' => 't2', 'html' => '<p/>']]);
        self::assertSame(['version' => 2, 'savedAt' => 't2', 'html' => '<p/>'], $this->client->templates->getVersion('tpl_1', 2));
        $this->assertRequest('GET', '/v1/templates/tpl_1/versions/2', null);
    }

    /** version is an int, as in Node (`version: number`): "2/../x" cannot be passed. */
    #[Test]
    public function getVersionTakesAnInt(): void
    {
        $this->expectException(\TypeError::class);
        // @phpstan-ignore argument.type (the point of the test)
        $this->client->templates->getVersion('tpl_1', '2/../x');
    }

    /** D7: variables is always a JSON object. An empty PHP [] must not go out as a JSON array. */
    #[Test]
    public function testSendSendsVariablesAsAnObject(): void
    {
        $this->fake->queue(200, ['data' => ['queued' => true]]);
        self::assertSame(['queued' => true], $this->client->templates->testSend('tpl_1', 'me@x.it'));
        $this->assertRequest('POST', '/v1/templates/tpl_1/test-send', '{"to":"me@x.it","variables":{}}');

        $this->client->templates->testSend('tpl_1', 'me@x.it', []);
        $this->assertRequest('POST', '/v1/templates/tpl_1/test-send', '{"to":"me@x.it","variables":{}}');

        $this->client->templates->testSend('tpl_1', 'me@x.it', ['name' => 'Ann', 'city' => 'Roma']);
        $this->assertRequest('POST', '/v1/templates/tpl_1/test-send', '{"to":"me@x.it","variables":{"name":"Ann","city":"Roma"}}');

        // a list still becomes an object, never an array
        // @phpstan-ignore argument.type (a list instead of a map)
        $this->client->templates->testSend('tpl_1', 'me@x.it', ['Ann']);
        $this->assertRequest('POST', '/v1/templates/tpl_1/test-send', '{"to":"me@x.it","variables":{"0":"Ann"}}');
    }

    #[Test]
    public function exposesExactlyTheNodeMethods(): void
    {
        self::assertPublicMethods($this->client->templates, ['create', 'delete', 'get', 'getVersion', 'list', 'listVersions', 'testSend', 'update']);
    }
}
