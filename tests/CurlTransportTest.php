<?php

declare(strict_types=1);

namespace Tratto\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Tratto\Client;
use Tratto\Http\CurlTransport;
use Tratto\TrattoException;

/**
 * Runs CurlTransport against a local `php -S` (never the real API) and, for TLS, a local `openssl s_server`.
 */
final class CurlTransportTest extends TestCase
{
    /** @var resource|null */
    private static $server;
    private static string $url;

    public static function setUpBeforeClass(): void
    {
        $port = self::freePort();
        // under phpdbg (Infection's coverage run) the built-in server needs the php binary next to it
        $php = PHP_SAPI === 'phpdbg' ? dirname(PHP_BINARY) . '/php' : PHP_BINARY;
        self::$server = self::spawn([$php, '-S', '127.0.0.1:' . $port, __DIR__ . '/fixtures/server.php']);
        self::waitFor($port);
        self::$url = 'http://127.0.0.1:' . $port;
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$server !== null) {
            proc_terminate(self::$server);
            proc_close(self::$server);
            self::$server = null;
        }
    }

    /** @return array<mixed> */
    private static function echo(string $method, string $path, ?string $body = null): array
    {
        $response = (new CurlTransport())->send($method, self::$url . $path, ['X-Test' => 'yes', 'Content-Type' => 'text/plain'], $body, 5.0);
        self::assertSame(200, $response['status']);

        return json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function sendsMethodHeadersAndNoBody(): void
    {
        $echo = self::echo('GET', '/v1/emails?limit=1');

        self::assertSame('GET', $echo['method']);
        self::assertSame('/v1/emails?limit=1', $echo['uri']);
        self::assertSame('yes', $echo['headers']['x-test']);
        self::assertSame('', $echo['body']);
    }

    #[Test]
    public function sendsTheBody(): void
    {
        $echo = self::echo('PATCH', '/v1/x', '{"a":1}');

        self::assertSame('PATCH', $echo['method']);
        self::assertSame('{"a":1}', $echo['body']);
    }

    #[Test]
    public function deleteWithoutBody(): void
    {
        self::assertSame('DELETE', self::echo('DELETE', '/v1/x')['method']);
    }

    #[Test]
    public function returns204WithEmptyBody(): void
    {
        self::assertSame(['status' => 204, 'body' => ''], (new CurlTransport())->send('DELETE', self::$url . '/no-content', [], null, 5.0));
    }

    #[Test]
    public function returnsErrorBodiesInsteadOfFailing(): void
    {
        self::assertSame(['status' => 502, 'body' => '<html>Bad Gateway</html>'], (new CurlTransport())->send('GET', self::$url . '/not-json', [], null, 5.0));
    }

    /** C4: a Location header is never followed (it would carry Authorization elsewhere). */
    #[Test]
    public function neverFollowsRedirects(): void
    {
        $response = (new CurlTransport())->send('GET', self::$url . '/redirect', ['Authorization' => 'Bearer k'], null, 5.0);

        self::assertSame(['status' => 302, 'body' => '{"redirected":false}'], $response);
        // positive control: the target exists and answers, so a followed redirect would have been visible
        self::assertSame('{"reached":"target"}', (new CurlTransport())->send('GET', self::$url . '/target', [], null, 5.0)['body']);
    }

    /** C4: only http and https. */
    #[Test]
    public function refusesOtherProtocols(): void
    {
        $file = __DIR__ . '/fixtures/server.php';
        // positive control: the file exists and is readable, so a refusal is the protocol filter
        self::assertIsReadable($file);

        $e = self::failure('file://' . $file, 5.0);
        self::assertSame('connection_error', $e->errorCode);
        self::assertSame(0, $e->statusCode);
    }

    #[Test]
    public function timesOut(): void
    {
        $start = microtime(true);
        $e = self::failure(self::$url . '/slow', 0.3);

        self::assertSame('connection_error', $e->errorCode);
        self::assertStringStartsWith('Connection error: ', $e->getMessage());
        self::assertLessThan(0.9, microtime(true) - $start);
    }

    #[Test]
    public function mapsARefusedConnection(): void
    {
        $e = self::failure('http://127.0.0.1:' . self::freePort() . '/', 5.0);

        self::assertSame('connection_error', $e->errorCode);
        self::assertSame(0, $e->getCode());
    }

    /** C4: TLS verification is on. Positive control: the same server answers when verification is off. */
    #[Test]
    public function rejectsAnUntrustedCertificate(): void
    {
        $openssl = trim((string) shell_exec('command -v openssl'));
        if ($openssl === '') {
            self::markTestSkipped('openssl CLI not available');
        }
        $dir = sys_get_temp_dir() . '/tratto-tls-' . bin2hex(random_bytes(4));
        mkdir($dir);
        exec(sprintf(
            '%s req -x509 -newkey ec -pkeyopt ec_paramgen_curve:prime256v1 -nodes -keyout %s -out %s -days 1 -subj /CN=127.0.0.1 -addext subjectAltName=IP:127.0.0.1 2>&1',
            escapeshellarg($openssl),
            escapeshellarg($dir . '/key.pem'),
            escapeshellarg($dir . '/cert.pem'),
        ), $out, $code);
        self::assertSame(0, $code, implode("\n", $out));

        $port = self::freePort();
        $tls = self::spawn([$openssl, 's_server', '-quiet', '-www', '-accept', (string) $port, '-cert', $dir . '/cert.pem', '-key', $dir . '/key.pem']);

        try {
            self::waitFor($port);
            $url = 'https://127.0.0.1:' . $port . '/';

            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_TIMEOUT => 5]);
            self::assertIsString(curl_exec($ch), 'positive control: TLS server reachable without verification');

            $e = self::failure($url, 5.0);
            self::assertSame('connection_error', $e->errorCode);
        } finally {
            proc_terminate($tls);
            proc_close($tls);
            array_map('unlink', glob($dir . '/*') ?: []);
            rmdir($dir);
        }
    }

    #[Test]
    public function endToEndThroughTheClient(): void
    {
        $client = new Client('tr_test_k', ['baseUrl' => self::$url, 'timeout' => 5]);

        // the echo server has no "data" envelope: list() returns the whole response
        $echo = $client->emails->list(['limit' => 2]);
        self::assertSame('GET', $echo['method']);
        self::assertSame('/v1/emails?limit=2', $echo['uri']);
        self::assertSame('Bearer tr_test_k', $echo['headers']['authorization']);
        self::assertSame('application/json', $echo['headers']['accept']);
        self::assertStringStartsWith('tratto-php/', $echo['headers']['user-agent']);
    }

    #[Test]
    #[TestWith(['', 'http://x'])]
    #[TestWith(['GET', ''])]
    public function rejectsAnEmptyMethodOrUrl(string $method, string $url): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('method and url are required');
        (new CurlTransport())->send($method, $url, [], null, 1.0);
    }

    private static function failure(string $url, float $timeout): TrattoException
    {
        try {
            (new CurlTransport())->send('GET', $url, [], null, $timeout);
        } catch (TrattoException $e) {
            return $e;
        }
        self::fail('expected TrattoException for ' . $url);
    }

    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0') ?: throw new \RuntimeException('no free port');
        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, strrpos($name, ':') + 1);
    }

    /**
     * @param list<string>          $command
     * @param array<string, string> $env
     *
     * @return resource
     */
    private static function spawn(array $command, array $env = [])
    {
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, null, $env + getenv());

        return is_resource($process) ? $process : throw new \RuntimeException('cannot start ' . $command[0]);
    }

    private static function waitFor(int $port): void
    {
        for ($i = 0; $i < 100; $i++) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
            if ($socket !== false) {
                fclose($socket);

                return;
            }
            usleep(50_000);
        }
        throw new \RuntimeException('server on port ' . $port . ' did not start');
    }
}
