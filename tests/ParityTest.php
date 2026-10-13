<?php

declare(strict_types=1);

namespace Tratto\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tratto\Client;
use Tratto\Tests\Fake\FakeTransport;

/**
 * Parity with tratto-node 1.4.0 (ee97684): every public method, by resource and by name.
 * 56 methods in Node, minus the deprecated workspace delete, inviteMember, removeMember,
 * minus workspace updateMember (its route is being removed: RFC-002, user decision of 2026-10-02) = 52.
 */
final class ParityTest extends TestCase
{
    private const EXPECTED = [
        'emails' => ['get', 'list', 'listEvents', 'send'],
        'contacts' => ['create', 'getImportJob', 'importCsv', 'list', 'update'],
        'audiences' => ['addContacts', 'create', 'get', 'list'],
        'campaigns' => ['create', 'get', 'getStats', 'list', 'pause', 'send', 'testSend', 'unschedule'],
        'templates' => ['create', 'delete', 'get', 'getVersion', 'list', 'listVersions', 'testSend', 'update'],
        'webhooks' => ['create', 'delete', 'list', 'listDeliveries', 'rotateSecret', 'test'],
        'domains' => ['add', 'delete', 'get', 'list', 'verify'],
        'analytics' => ['getSummary', 'getTimeseries'],
        'flows' => ['activate', 'create', 'deactivate', 'delete', 'get', 'list', 'update'],
        'workspace' => ['get', 'update', 'updatePreferences'],
    ];

    /** Removed on purpose: none of them may come back. */
    private const EXCLUDED = ['workspace' => ['delete', 'inviteMember', 'removeMember', 'updateMember']];

    /** @return array<string, list<string>> */
    private static function actual(): array
    {
        $client = new Client('k', ['transport' => new FakeTransport()]);
        $actual = [];
        foreach ((new \ReflectionClass($client))->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $resource = $property->getValue($client);
            self::assertIsObject($resource);
            $methods = [];
            foreach ((new \ReflectionClass($resource))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if (!$method->isConstructor()) {
                    $methods[] = $method->getName();
                }
            }
            sort($methods);
            $actual[$property->getName()] = $methods;
        }

        return $actual;
    }

    #[Test]
    public function matchesNodeByResourceAndName(): void
    {
        $actual = self::actual();
        ksort($actual);
        $expected = self::EXPECTED;
        ksort($expected);

        self::assertSame($expected, $actual);
    }

    #[Test]
    public function hasExactlyFiftyTwoMethods(): void
    {
        self::assertSame(52, array_sum(array_map('count', self::actual())));
        self::assertSame(52, array_sum(array_map('count', self::EXPECTED)), 'the expected list itself');
    }

    #[Test]
    public function excludedMethodsNeverComeBack(): void
    {
        $actual = self::actual();
        foreach (self::EXCLUDED as $resource => $methods) {
            self::assertArrayHasKey($resource, $actual, 'positive control: the resource exists');
            foreach ($methods as $method) {
                self::assertNotContains($method, $actual[$resource], "$resource.$method must not exist");
            }
        }
    }
}
