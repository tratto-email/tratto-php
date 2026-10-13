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
        self::$server = self::spawn([PHP_BINARY, '-q', '-S', '127.0.0.1:' . $port, __DIR__ . '/fixtures/server.php']);
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
        self::assertStringContainsString('timed out', $e->getMessage());
        self::assertLessThan(0.9, microtime(true) - $start);
    }

    #[Test]
    public function mapsARefusedConnection(): void
    {
        $port = self::freePort();
        $e = self::failure('http://127.0.0.1:' . $port . '/', 5.0);

        self::assertSame('connection_error', $e->errorCode);
        self::assertStringContainsString((string) $port, $e->getMessage(), 'the curl error is kept');
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

    /** @return array<int, mixed> */
    private static function options(float $timeout): array
    {
        return (new \ReflectionMethod(CurlTransport::class, 'options'))->invoke(null, $timeout);
    }

    /** C4 and timeouts, checked on the exact options given to curl. */
    #[Test]
    public function fixesTheCurlOptions(): void
    {
        self::assertSame([
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => 30000,
            CURLOPT_CONNECTTIMEOUT_MS => 10000,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_FAILONERROR => false,
        ], self::options(30.0));
    }

    #[Test]
    #[TestWith([1.2344, 1235, 1235])]
    #[TestWith([0.0004, 1, 1])]
    #[TestWith([10.0, 10000, 10000])]
    #[TestWith([45.5, 45500, 10000])]
    public function roundsTimeoutsUpToMilliseconds(float $timeout, int $total, int $connect): void
    {
        $options = self::options($timeout);
        self::assertSame($total, $options[CURLOPT_TIMEOUT_MS]);
        self::assertSame($connect, $options[CURLOPT_CONNECTTIMEOUT_MS]);
    }

    /** D1 with real curl: a CR/LF in a header value never reaches the wire. */
    #[Test]
    public function headerInjectionNeverReachesTheServer(): void
    {
        $client = new Client('tr_test_k', ['baseUrl' => self::$url, 'timeout' => 5]);
        // positive control: a clean key arrives as one header
        $echo = $client->emails->list();
        self::assertSame('/v1/emails', $echo['uri']);

        try {
            $client->emails->send(['to' => 'a@b.it'], "abc\r\nX-Injected: yes");
            self::fail('expected TrattoException');
        } catch (TrattoException $e) {
            self::assertSame('invalid_header', $e->errorCode);
            self::assertSame('Header "Idempotency-Key" contains CR, LF or NUL', $e->getMessage());
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
     * Pipes, not files, for the child's output: under Infection a 'file' descriptor goes through its stream
     * wrapper and posix_spawn fails. Both servers run quiet, so the pipes never fill.
     *
     * @param list<string>          $command
     * @param array<string, string> $env
     *
     * @return resource
     */
    private static function spawn(array $command, array $env = [])
    {
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env + getenv());

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
