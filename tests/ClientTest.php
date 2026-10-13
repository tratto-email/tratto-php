<?php

declare(strict_types=1);

namespace Tratto\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tratto\Client;
use Tratto\Http\CurlTransport;
use Tratto\Http\HttpClient;
use Tratto\Resources\Analytics;
use Tratto\Resources\Audiences;
use Tratto\Resources\Campaigns;
use Tratto\Resources\Contacts;
use Tratto\Resources\Domains;
use Tratto\Resources\Emails;
use Tratto\Resources\Flows;
use Tratto\Resources\Resource;
use Tratto\Resources\Templates;
use Tratto\Resources\Webhooks;
use Tratto\Resources\Workspace;
use Tratto\Tests\Fake\FakeTransport;
use Tratto\TrattoException;

final class ClientTest extends TestCase
{
    private const KEY = 'tr_live_SuperSecretKey0123456789';

    #[Test]
    public function rejectsAnEmptyKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('apiKey is required');
        new Client('');
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function badOptions(): iterable
    {
        yield 'unknown option (no curl passthrough)' => [['curlOptions' => [CURLOPT_SSL_VERIFYPEER => false]], 'Unknown option(s): curlOptions'];
        yield 'baseUrl not a string' => [['baseUrl' => 42], 'baseUrl must be an http(s) URL'];
        yield 'baseUrl without scheme' => [['baseUrl' => 'api.tratto.email'], 'baseUrl must be an http(s) URL'];
        yield 'baseUrl with another scheme' => [['baseUrl' => 'file:///etc/passwd'], 'baseUrl must be an http(s) URL'];
        yield 'baseUrl with scheme not at start' => [['baseUrl' => 'x https://a'], 'baseUrl must be an http(s) URL'];
        yield 'baseUrl with NUL' => [['baseUrl' => "http://127.0.0.1:1/\0x"], 'baseUrl must be an http(s) URL'];
        yield 'baseUrl with space' => [['baseUrl' => 'https://a b'], 'baseUrl must be an http(s) URL'];
        yield 'baseUrl with trailing newline' => [['baseUrl' => "https://a\n"], 'baseUrl must be an http(s) URL'];
        yield 'baseUrl with DEL' => [['baseUrl' => "https://a\x7f"], 'baseUrl must be an http(s) URL'];
        yield 'baseUrl scheme only' => [['baseUrl' => 'https://'], 'baseUrl must be an http(s) URL'];
        yield 'timeout zero' => [['timeout' => 0], 'timeout must be a positive number of seconds'];
        yield 'timeout negative' => [['timeout' => -1.5], 'timeout must be a positive number of seconds'];
        yield 'timeout string' => [['timeout' => '30'], 'timeout must be a positive number of seconds'];
        yield 'transport wrong type' => [['transport' => new \stdClass()], 'transport must implement Tratto\Http\Transport'];
    }

    /** @param array<string, mixed> $options */
    #[Test]
    #[DataProvider('badOptions')]
    public function rejectsBadOptions(array $options, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        new Client('k', $options);
    }

    /**
     * D4/D5: constructor errors must not carry the key in their trace. PHP 8.1 ignores #[\SensitiveParameter],
     * so on 8.1 this is the only protection; on 8.2+ the attribute covers it as well.
     *
     * @param array<string, mixed> $options
     */
    #[Test]
    #[DataProvider('badOptions')]
    public function constructorErrorsNeverCarryTheKey(array $options, string $message): void
    {
        $previous = ini_set('zend.exception_ignore_args', '0');
        try {
            new Client(self::KEY, $options);
            self::fail('expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            self::assertSame($message, $e->getMessage());
            // positive control: the trace does record arguments (the options array is there)
            self::assertArrayHasKey('args', $e->getTrace()[0]);
            // assertFalse, not assertStringNotContainsString: on failure the haystack (the whole trace) is too big to export
            self::assertFalse(str_contains(print_r($e->getTrace(), true), 'SuperSecret'), 'the trace carries the key');
            self::assertFalse(str_contains((string) $e, 'SuperSecret'), '__toString carries the key');
        } finally {
            ini_set('zend.exception_ignore_args', (string) $previous);
        }
    }

    #[Test]
    public function acceptsAnHttpBaseUrlWithPort(): void
    {
        $fake = new FakeTransport();
        (new Client('k', ['baseUrl' => 'http://127.0.0.1:8080', 'transport' => $fake]))->emails->get('e');
        self::assertSame('http://127.0.0.1:8080/v1/emails/e', $fake->last()['url']);
    }

    #[Test]
    public function defaultsToProductionCurlAndThirtySeconds(): void
    {
        $client = new Client('k');
        $http = (new \ReflectionProperty($client->emails, 'http'))->getValue($client->emails);
        self::assertInstanceOf(HttpClient::class, $http);
        self::assertSame('https://api.tratto.email', $http->baseUrl);
        self::assertSame(30.0, $http->timeout);
        self::assertInstanceOf(CurlTransport::class, (new \ReflectionProperty($http, 'transport'))->getValue($http));
    }

    #[Test]
    public function usesBaseUrlWithoutTrailingSlashAndTimeout(): void
    {
        $fake = new FakeTransport();
        $client = new Client('k', ['baseUrl' => 'http://localhost:8080/', 'timeout' => 5, 'transport' => $fake]);
        $client->emails->get('e1');

        self::assertSame('http://localhost:8080/v1/emails/e1', $fake->last()['url']);
        self::assertSame(5.0, $fake->last()['timeout']);
    }

    #[Test]
    public function sendsAuthUserAgentAndAccept(): void
    {
        $fake = new FakeTransport();
        (new Client(self::KEY, ['transport' => $fake]))->emails->get('e1');

        self::assertSame([
            'Authorization' => 'Bearer ' . self::KEY,
            'User-Agent' => 'tratto-php/' . Client::VERSION . ' php/' . PHP_VERSION,
            'Accept' => 'application/json',
        ], $fake->last()['headers']);
        self::assertNull($fake->last()['body']);
    }

    #[Test]
    public function versionIsSemver(): void
    {
        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', Client::VERSION);
    }

    /**
     * C3: the key must not appear in any dump of the client, of a resource, or of an exception
     * raised by a request. Positive control: the same dumps must contain the base URL.
     */
    #[Test]
    public function neverLeaksTheKeyInDumps(): void
    {
        $fake = (new FakeTransport())->queue(401, ['error' => ['message' => 'Invalid API key', 'code' => 'unauthorized']]);
        $client = new Client(self::KEY, ['baseUrl' => 'https://api-staging.example.test', 'transport' => $fake]);

        try {
            $client->emails->get('e1');
            self::fail('expected TrattoException');
        } catch (TrattoException $e) {
            $exception = $e;
        }
        // the key was really sent (the redaction is not an empty client); then forget what the fake recorded
        self::assertSame('Bearer ' . self::KEY, $fake->last()['headers']['Authorization']);
        $fake->requests = [];

        // a network error raised inside CurlTransport, whose own trace holds the Authorization header
        try {
            (new Client(self::KEY, ['baseUrl' => 'http://127.0.0.1:1', 'timeout' => 2]))->emails->get('e1');
            self::fail('expected TrattoException');
        } catch (TrattoException $e) {
            self::assertSame('connection_error', $e->errorCode);
            $networkException = $e;
        }

        // a header-injection error, raised before the transport
        try {
            $client->emails->send(['to' => 'a@b.it'], "x\r\nY: z");
            self::fail('expected TrattoException');
        } catch (TrattoException $e) {
            self::assertSame('invalid_header', $e->errorCode);
            $headerException = $e;
        }

        foreach (['client' => $client, 'resource' => $client->emails, 'webhooks' => $client->webhooks, 'contacts' => $client->contacts, 'exception' => $exception, 'networkException' => $networkException, 'headerException' => $headerException] as $label => $subject) {
            ob_start();
            var_dump($subject);
            $dumps = [
                'print_r' => print_r($subject, true),
                'var_export' => var_export($subject, true),
                'var_dump' => (string) ob_get_clean(),
                'json_encode' => json_encode($subject, JSON_THROW_ON_ERROR),
            ];
            if ($subject instanceof \Throwable) {
                $dumps['__toString'] = (string) $subject;
            }
            foreach ($dumps as $how => $dump) {
                self::assertStringNotContainsString(self::KEY, $dump, "$how($label) leaks the key");
                self::assertStringNotContainsString('SuperSecret', $dump, "$how($label) leaks part of the key");
            }
            if ($subject instanceof Client || $subject instanceof Resource) {
                foreach (['print_r', 'var_export', 'var_dump'] as $how) {
                    self::assertStringContainsString('api-staging.example.test', $dumps[$how], "$how($label) positive control");
                }
            }
        }
    }

    #[Test]
    public function serializingTheClientFails(): void
    {
        $this->expectException(\Exception::class);
        serialize(new Client(self::KEY, ['transport' => new FakeTransport()]));
    }

    #[Test]
    public function exposesTheResourcesSharingOneKeyHolder(): void
    {
        $client = new Client('k', ['transport' => new FakeTransport()]);
        $expected = [
            'emails' => Emails::class,
            'contacts' => Contacts::class,
            'audiences' => Audiences::class,
            'campaigns' => Campaigns::class,
            'templates' => Templates::class,
            'webhooks' => Webhooks::class,
            'domains' => Domains::class,
            'analytics' => Analytics::class,
            'flows' => Flows::class,
            'workspace' => Workspace::class,
        ];
        $actual = [];
        foreach ((new \ReflectionClass($client))->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $actual[$property->getName()] = $property->getValue($client)::class;
        }
        self::assertSame($expected, $actual);

        $http = static fn(object $resource): mixed => (new \ReflectionProperty(Resource::class, 'http'))->getValue($resource);
        foreach (array_keys($expected) as $name) {
            self::assertSame($http($client->emails), $http($client->{$name}), $name);
        }
    }
}
