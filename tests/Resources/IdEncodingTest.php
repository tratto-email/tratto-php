<?php

declare(strict_types=1);

namespace Tratto\Tests\Resources;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tratto\Client;

/**
 * Every route that takes an id encodes it as one path segment: "/", "?" and "#" cannot change the route.
 * One case per route; new resources add their routes here.
 */
final class IdEncodingTest extends ResourceTestCase
{
    private const ID = 'a/b?c#d';
    private const ENCODED = 'a%2Fb%3Fc%23d';

    /** @return iterable<string, array{\Closure(Client, string): mixed, string, string}> */
    public static function routes(): iterable
    {
        yield 'emails.get' => [static fn(Client $c, string $id) => $c->emails->get($id), 'GET', '/v1/emails/{id}'];
        yield 'emails.listEvents' => [static fn(Client $c, string $id) => $c->emails->listEvents($id), 'GET', '/v1/emails/{id}/events'];
        yield 'contacts.update' => [static fn(Client $c, string $id) => $c->contacts->update($id, ['firstName' => 'A']), 'PATCH', '/v1/contacts/{id}'];
        yield 'contacts.getImportJob' => [static fn(Client $c, string $id) => $c->contacts->getImportJob($id), 'GET', '/v1/contacts/import/{id}'];
        yield 'audiences.get' => [static fn(Client $c, string $id) => $c->audiences->get($id), 'GET', '/v1/audiences/{id}'];
        yield 'audiences.addContacts' => [static fn(Client $c, string $id) => $c->audiences->addContacts($id, ['ct_1']), 'POST', '/v1/audiences/{id}/contacts'];
        yield 'webhooks.delete' => [static fn(Client $c, string $id) => $c->webhooks->delete($id), 'DELETE', '/v1/webhooks/{id}'];
        yield 'webhooks.listDeliveries' => [static fn(Client $c, string $id) => $c->webhooks->listDeliveries($id), 'GET', '/v1/webhooks/{id}/deliveries'];
        yield 'webhooks.test' => [static fn(Client $c, string $id) => $c->webhooks->test($id), 'POST', '/v1/webhooks/{id}/test'];
        yield 'webhooks.rotateSecret' => [static fn(Client $c, string $id) => $c->webhooks->rotateSecret($id), 'POST', '/v1/webhooks/{id}/rotate-secret'];
        yield 'domains.get' => [static fn(Client $c, string $id) => $c->domains->get($id), 'GET', '/v1/domains/{id}'];
        yield 'domains.verify' => [static fn(Client $c, string $id) => $c->domains->verify($id), 'POST', '/v1/domains/{id}/verify'];
        yield 'domains.delete' => [static fn(Client $c, string $id) => $c->domains->delete($id), 'DELETE', '/v1/domains/{id}'];
        yield 'campaigns.get' => [static fn(Client $c, string $id) => $c->campaigns->get($id), 'GET', '/v1/campaigns/{id}'];
        yield 'campaigns.getStats' => [static fn(Client $c, string $id) => $c->campaigns->getStats($id), 'GET', '/v1/campaigns/{id}/stats'];
        yield 'campaigns.send' => [static fn(Client $c, string $id) => $c->campaigns->send($id), 'POST', '/v1/campaigns/{id}/send'];
        yield 'campaigns.unschedule' => [static fn(Client $c, string $id) => $c->campaigns->unschedule($id), 'POST', '/v1/campaigns/{id}/unschedule'];
        yield 'campaigns.pause' => [static fn(Client $c, string $id) => $c->campaigns->pause($id), 'POST', '/v1/campaigns/{id}/pause'];
        yield 'campaigns.testSend' => [static fn(Client $c, string $id) => $c->campaigns->testSend($id, 'a@b.it'), 'POST', '/v1/campaigns/{id}/test-send'];
        yield 'templates.get' => [static fn(Client $c, string $id) => $c->templates->get($id), 'GET', '/v1/templates/{id}'];
        yield 'templates.update' => [static fn(Client $c, string $id) => $c->templates->update($id, ['name' => 'N']), 'PATCH', '/v1/templates/{id}'];
        yield 'templates.delete' => [static fn(Client $c, string $id) => $c->templates->delete($id), 'DELETE', '/v1/templates/{id}'];
        yield 'templates.listVersions' => [static fn(Client $c, string $id) => $c->templates->listVersions($id), 'GET', '/v1/templates/{id}/versions'];
        // listed by hand: the id is not the only path parameter (the version follows it)
        yield 'templates.getVersion' => [static fn(Client $c, string $id) => $c->templates->getVersion($id, 3), 'GET', '/v1/templates/{id}/versions/3'];
        yield 'flows.get' => [static fn(Client $c, string $id) => $c->flows->get($id), 'GET', '/v1/flows/{id}'];
        yield 'flows.update' => [static fn(Client $c, string $id) => $c->flows->update($id, ['name' => 'N']), 'PATCH', '/v1/flows/{id}'];
        yield 'flows.delete' => [static fn(Client $c, string $id) => $c->flows->delete($id), 'DELETE', '/v1/flows/{id}'];
        yield 'flows.activate' => [static fn(Client $c, string $id) => $c->flows->activate($id), 'POST', '/v1/flows/{id}/activate'];
        yield 'flows.deactivate' => [static fn(Client $c, string $id) => $c->flows->deactivate($id), 'POST', '/v1/flows/{id}/deactivate'];
        yield 'attachments.get' => [static fn(Client $c, string $id) => $c->attachments->get($id), 'GET', '/v1/attachments/{id}'];
        yield 'attachments.delete' => [static fn(Client $c, string $id) => $c->attachments->delete($id), 'DELETE', '/v1/attachments/{id}'];
        yield 'templates.testSend' => [static fn(Client $c, string $id) => $c->templates->testSend($id, 'a@b.it'), 'POST', '/v1/templates/{id}/test-send'];
    }

    /** @param \Closure(Client, string): mixed $call */
    #[Test]
    #[DataProvider('routes')]
    public function encodesTheId(\Closure $call, string $method, string $path): void
    {
        $call($this->client, self::ID);

        $request = $this->fake->last();
        self::assertSame($method, $request['method']);
        self::assertSame(self::BASE . str_replace('{id}', self::ENCODED, $path), $request['url']);
    }

    /** Guard: every public method with an $id-like first parameter appears above, so a new route cannot skip this test. */
    #[Test]
    public function coversEveryRouteWithAnId(): void
    {
        $withId = [];
        foreach ((new \ReflectionClass(Client::class))->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $resource = $property->getValue($this->client);
            self::assertIsObject($resource);
            foreach ((new \ReflectionClass($resource))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $first = $method->getParameters()[0] ?? null;
                if ($first !== null && preg_match('/^(id|\w+Id)$/', $first->getName()) === 1) {
                    $withId[] = $property->getName() . '.' . $method->getName();
                }
            }
        }
        sort($withId);
        $covered = [];
        foreach (self::routes() as $name => $_) {
            $covered[] = $name;
        }
        sort($covered);

        self::assertNotSame([], $withId, 'positive control: routes with an id were found');
        self::assertSame($covered, $withId);
    }
}
