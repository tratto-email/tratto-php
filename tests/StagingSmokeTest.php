<?php

declare(strict_types=1);

namespace Tratto\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * C8: scripts/staging-smoke.php must not be able to hit production by mistake. The script runs as a child
 * process; every case here stops before any request, except the positive control, which targets a closed
 * local port.
 */
final class StagingSmokeTest extends TestCase
{
    private const KEY = 'tratto_test_SmokeSecret0123456789';

    /**
     * @param array<string, string> $env
     * @param list<string>          $args
     *
     * @return array{int, string}
     */
    private static function runSmoke(array $env, array $args = []): array
    {
        // Safety net: a dead local proxy, so that even a broken guard cannot send a request off this machine
        // (libcurl reads these variables; checked: a request to an outside host fails "over proxy 127.0.0.1").
        $env += [
            'PATH' => (string) getenv('PATH'),
            'http_proxy' => 'http://127.0.0.1:1',
            'https_proxy' => 'http://127.0.0.1:1',
            'HTTPS_PROXY' => 'http://127.0.0.1:1',
        ];
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__) . '/scripts/staging-smoke.php', ...$args],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            $env,
        );
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);

        return [proc_close($process), $output];
    }

    /** @return iterable<string, array{string}> */
    public static function productionUrls(): iterable
    {
        yield 'production' => ['https://api.tratto.email'];
        yield 'production with path' => ['https://api.tratto.email/'];
        yield 'production upper case' => ['https://API.Tratto.Email'];
        yield 'production trailing dot' => ['https://api.tratto.email.'];
        yield 'production over http' => ['http://api.tratto.email'];
    }

    #[Test]
    #[DataProvider('productionUrls')]
    public function refusesProductionWithoutTheFlag(string $baseUrl): void
    {
        [$code, $output] = self::runSmoke(['TRATTO_API_KEY' => self::KEY, 'TRATTO_FROM_EMAIL' => 'a@b.it', 'TRATTO_BASE_URL' => $baseUrl]);

        self::assertSame(2, $code, $output);
        self::assertStringContainsString('Refusing to run against production', $output);
        self::assertStringNotContainsString('SmokeSecret', $output);
    }

    #[Test]
    public function refusesALiveKey(): void
    {
        [$code, $output] = self::runSmoke(['TRATTO_API_KEY' => 'tratto_live_SmokeSecret', 'TRATTO_FROM_EMAIL' => 'a@b.it', 'TRATTO_BASE_URL' => 'http://127.0.0.1:1']);

        self::assertSame(2, $code, $output);
        self::assertStringContainsString('not a test-mode key', $output);
        self::assertStringNotContainsString('SmokeSecret', $output);
    }

    #[Test]
    public function requiresKeyAndSender(): void
    {
        [$code, $output] = self::runSmoke([]);
        self::assertSame(2, $code);
        self::assertStringContainsString('Missing TRATTO_API_KEY', $output);

        [$code, $output] = self::runSmoke(['TRATTO_API_KEY' => self::KEY]);
        self::assertSame(2, $code);
        self::assertStringContainsString('Missing TRATTO_FROM_EMAIL', $output);
    }

    /**
     * Positive control: a non-production URL passes the guard and the script really tries the request
     * (a closed local port, so it fails with a connection error, exit 1, and no request leaves the machine).
     */
    #[Test]
    public function aNonProductionUrlPassesTheGuard(): void
    {
        [$code, $output] = self::runSmoke(['TRATTO_API_KEY' => self::KEY, 'TRATTO_FROM_EMAIL' => 'a@b.it', 'TRATTO_BASE_URL' => 'http://127.0.0.1:1']);

        self::assertSame(1, $code, $output);
        self::assertStringContainsString('Smoke test against http://127.0.0.1:1', $output);
        self::assertStringContainsString('connection_error', $output);
        self::assertStringNotContainsString('SmokeSecret', $output);
    }

    /** The flag is what opens production: checked on the guard only, the request goes to a closed local port. */
    #[Test]
    public function theFlagIsTheOnlyWayPastTheGuard(): void
    {
        [$code, $output] = self::runSmoke(['TRATTO_API_KEY' => self::KEY, 'TRATTO_FROM_EMAIL' => 'a@b.it', 'TRATTO_BASE_URL' => 'http://127.0.0.1:1'], ['--allow-production']);
        self::assertSame(1, $code, $output);

        [$code] = self::runSmoke(['TRATTO_API_KEY' => self::KEY, 'TRATTO_FROM_EMAIL' => 'a@b.it', 'TRATTO_BASE_URL' => 'https://api.tratto.email'], ['--allow-prod']);
        self::assertSame(2, $code, 'a similar flag is not the flag');
    }

    /**
     * Positive control for the flag (N5): with --allow-production a production URL passes the guard. The request
     * goes to the dead proxy of runSmoke(), so nothing leaves this machine; the output proves it.
     */
    #[Test]
    public function theFlagLetsProductionThrough(): void
    {
        [$code, $output] = self::runSmoke(['TRATTO_API_KEY' => self::KEY, 'TRATTO_FROM_EMAIL' => 'a@b.it', 'TRATTO_BASE_URL' => 'https://api.tratto.email'], ['--allow-production']);

        self::assertSame(1, $code, $output);
        self::assertStringContainsString('Smoke test against https://api.tratto.email', $output);
        self::assertStringContainsString('connection_error', $output);
        // The target is api.tratto.email, so a connection error naming 127.0.0.1 can only come from the dead proxy
        // (curl 8 says "over proxy 127.0.0.1", older curl "Failed to connect to 127.0.0.1 port 1").
        self::assertMatchesRegularExpression('/connection_error: .*127\.0\.0\.1/', $output, 'the request must have stopped at the dead local proxy');
        self::assertStringNotContainsString('SmokeSecret', $output);
    }

    #[Test]
    public function defaultsToStaging(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__) . '/scripts/staging-smoke.php');
        self::assertStringContainsString("const DEFAULT_BASE_URL = 'https://api-staging.tratto.email';", $source);
    }
}
