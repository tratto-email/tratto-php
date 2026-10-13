<?php

declare(strict_types=1);

namespace Tratto\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tratto\Webhook;

final class WebhookTest extends TestCase
{
    /**
     * Fixed vector, computed with the signer's own code path (node: createHmac('sha256', secret)
     * .update(`${t}.${body}`).digest('hex'), as in tratto-api webhookFanout signPayload) and with PHP hash_hmac.
     */
    private const SECRET = 'whsec_abababababababababababababababababababababababab';
    private const T = '1784977714054';
    private const BODY = '{"id":"evt_1","type":"delivered"}';
    private const V1 = '05e8e3cddebbeca892e5fd50622431ca3059594f22001ef9f7a2c0566a44fcc8';

    private static function verify(string $header, string $body = self::BODY, string $secret = self::SECRET, int $tolerance = 300, int $nowMs = 1784977714054): bool
    {
        return Webhook::verify($body, $header, $secret, $tolerance, $nowMs);
    }

    private static function sign(string $t, string $body = self::BODY): string
    {
        return hash_hmac('sha256', $t . '.' . $body, self::SECRET);
    }

    #[Test]
    public function acceptsTheFixedVector(): void
    {
        self::assertSame(self::V1, self::sign(self::T), 'the vector itself');
        self::assertTrue(self::verify('t=' . self::T . ',v1=' . self::V1));
    }

    #[Test]
    public function acceptsWhitespaceUppercaseHexAndUnknownFields(): void
    {
        self::assertTrue(self::verify(' t = ' . self::T . ' , v1 = ' . strtoupper(self::V1) . ' ,v2=future,junk'));
        self::assertTrue(self::verify('junk,t=' . self::T . ',,x=1=2,v1=' . self::V1), 'a malformed part is skipped, not the end of parsing');
    }

    #[Test]
    public function acceptsAnyOfSeveralV1(): void
    {
        $other = str_repeat('0', 64);
        self::assertTrue(self::verify('t=' . self::T . ',v1=' . $other . ',v1=' . self::V1));
        self::assertTrue(self::verify('t=' . self::T . ',v1=' . self::V1 . ',v1=' . $other));
        self::assertTrue(self::verify('v1=' . self::V1 . ',t=' . self::T), 'order of fields does not matter');
    }

    /** Only v1 is a signature: a future scheme must not be accepted as v1 just because the value matches. */
    #[Test]
    public function acceptsTheSignatureOnlyUnderV1(): void
    {
        foreach (['v0', 'v2', 'V1', 'sig', 't1'] as $field) {
            self::assertFalse(self::verify('t=' . self::T . ',' . $field . '=' . self::V1), $field);
        }
    }

    /** A t that is not purely digits is rejected even when signed: (int) would otherwise read "+123" or "123x" as 123. */
    #[Test]
    public function rejectsSignedTimestampsThatAreNotPureDigits(): void
    {
        foreach (['+' . self::T, self::T . 'x', self::T . ' 1', 'x' . self::T, self::T . "\n"] as $t) {
            self::assertFalse(self::verify('t=' . $t . ',v1=' . self::sign($t)), var_export($t, true));
        }
        self::assertFalse(self::verify('t=' . self::T . '=,v1=' . self::sign(self::T . '=')), 'value with "="');
    }

    #[Test]
    public function rejectsWhenNoV1Matches(): void
    {
        self::assertFalse(self::verify('t=' . self::T . ',v1=' . str_repeat('0', 64) . ',v1=' . str_repeat('f', 64)));
    }

    #[Test]
    public function rejectsAWrongSecret(): void
    {
        self::assertFalse(self::verify('t=' . self::T . ',v1=' . self::V1, secret: 'whsec_' . str_repeat('ab', 23) . 'ac'));
        self::assertFalse(self::verify('t=' . self::T . ',v1=' . self::V1, secret: str_repeat('ab', 24)), 'the whsec_ prefix is part of the key');
    }

    #[Test]
    public function rejectsABodyAlteredByOneByte(): void
    {
        self::assertFalse(self::verify('t=' . self::T . ',v1=' . self::V1, body: '{"id":"evt_2","type":"delivered"}'));
        self::assertFalse(self::verify('t=' . self::T . ',v1=' . self::V1, body: self::BODY . ' '));
    }

    #[Test]
    public function rejectsTheBodySignedAlone(): void
    {
        self::assertFalse(self::verify('t=' . self::T . ',v1=' . hash_hmac('sha256', self::BODY, self::SECRET)));
    }

    /**
     * The signer writes t with `${Date.now()}`: never leading zeros. A zero-padded t is the same number but a
     * different string, so the genuine v1 (signed over "1784977714054.") must not match: verify() signs t exactly
     * as received and never re-formats it.
     * - "0001784977714054" (the verdict's case, 16 chars) fails, and would fail on the HMAC alone too.
     * - "01784977714054" (14 chars, within the 15-digit limit) isolates the HMAC: the genuine v1 fails, while a v1
     *   computed over the padded string matches, which shows the raw string is what gets signed. Producing that
     *   v1 requires the secret, so accepting it is not a weakness.
     */
    #[Test]
    public function signsTheTimestampStringAsReceived(): void
    {
        self::assertFalse(self::verify('t=0001784977714054,v1=' . self::V1));
        self::assertFalse(self::verify('t=01784977714054,v1=' . self::V1));
        self::assertTrue(self::verify('t=01784977714054,v1=' . self::sign('01784977714054')));
    }

    /** @return iterable<string, array{int, bool}> */
    public static function clockSkews(): iterable
    {
        yield 'exactly 5 min in the past' => [-300_000, true];
        yield '1 ms beyond in the past' => [-300_001, false];
        yield 'exactly 5 min in the future' => [300_000, true];
        yield '1 ms beyond in the future' => [300_001, false];
        yield 'a day old (replay)' => [-86_400_000, false];
    }

    #[Test]
    #[DataProvider('clockSkews')]
    public function enforcesTheToleranceBothWays(int $skewMs, bool $expected): void
    {
        $t = (string) (1784977714054 + $skewMs);
        self::assertSame($expected, self::verify('t=' . $t . ',v1=' . self::sign($t)));
    }

    #[Test]
    public function toleranceIsConfigurable(): void
    {
        $t = (string) (1784977714054 - 60_001);
        $header = 't=' . $t . ',v1=' . self::sign($t);
        self::assertFalse(self::verify($header, tolerance: 60));
        self::assertTrue(self::verify($header, tolerance: 61));
        self::assertTrue(self::verify('t=' . self::T . ',v1=' . self::V1, tolerance: 0), 'zero tolerance accepts the same millisecond');
    }

    #[Test]
    public function defaultsToFiveMinutesAndTheRealClock(): void
    {
        self::assertSame(300, Webhook::DEFAULT_TOLERANCE_SECONDS);
        $now = (int) floor(microtime(true) * 1000);

        $fresh = (string) ($now - 1_000);
        self::assertTrue(Webhook::verify(self::BODY, 't=' . $fresh . ',v1=' . self::sign($fresh), self::SECRET));

        $stale = (string) ($now - 310_000);
        self::assertFalse(Webhook::verify(self::BODY, 't=' . $stale . ',v1=' . self::sign($stale), self::SECRET));

        $justInside = (string) ($now - 290_000);
        self::assertTrue(Webhook::verify(self::BODY, 't=' . $justInside . ',v1=' . self::sign($justInside), self::SECRET));
    }

    /** @return iterable<string, array{?string}> */
    public static function malformedHeaders(): iterable
    {
        $v1 = ',v1=' . self::V1;
        yield 'missing (null)' => [null];
        yield 'empty' => [''];
        yield 'garbage' => ['nonsense'];
        yield 'no t' => ['v1=' . self::V1];
        yield 'no v1' => ['t=' . self::T];
        yield 'empty v1' => ['t=' . self::T . ',v1='];
        yield 'empty t' => ['t=' . $v1];
        yield 't not numeric' => ['t=abc' . $v1];
        yield 't negative' => ['t=-1784977714054' . $v1];
        yield 't with a decimal' => ['t=1784977714054.0' . $v1];
        yield 't in exponent form' => ['t=1.784977714054e12' . $v1];
        yield 't too long (16 digits)' => ['t=1784977714054000' . $v1];
        yield 't with a trailing newline inside' => ["t=1784977714054\n0" . $v1];
        yield 'two t' => ['t=' . self::T . ',t=' . self::T . $v1];
        yield 'v1 too short' => ['t=' . self::T . ',v1=' . substr(self::V1, 0, 63)];
        yield 'v1 too long' => ['t=' . self::T . ',v1=' . self::V1 . '0'];
        yield 'semicolons' => ['t=' . self::T . ';v1=' . self::V1];
        yield 'key without =' => ['t' . self::T . $v1];
    }

    #[Test]
    #[DataProvider('malformedHeaders')]
    public function rejectsMalformedHeaders(?string $header): void
    {
        self::assertFalse(Webhook::verify(self::BODY, $header, self::SECRET, 300, 1784977714054));
    }

    /** C2: ext-ctype is not a declared requirement, so the verifier must not call it (a missing ctype = fatal 500). */
    #[Test]
    public function doesNotDependOnCtype(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__) . '/src/Webhook.php');
        self::assertStringContainsString('preg_match(', $source, 'positive control: the source was read');
        self::assertStringNotContainsString('ctype_', $source);
        self::assertArrayNotHasKey('ext-ctype', json_decode((string) file_get_contents(dirname(__DIR__) . '/composer.json'), true)['require']);
    }

    #[Test]
    public function acceptsFifteenDigitTimestamps(): void
    {
        $t = '100000000000000';
        self::assertTrue(self::verify('t=' . $t . ',v1=' . self::sign($t), nowMs: 100000000000000));
    }

    /** @return iterable<string, array{string, int, string}> */
    public static function configurationErrors(): iterable
    {
        yield 'empty secret' => ['', 300, 'secret is required'];
        yield 'negative tolerance' => [self::SECRET, -1, 'toleranceSeconds must not be negative'];
    }

    /** The secret never appears in an exception or its trace, also on PHP 8.1 where #[\SensitiveParameter] is ignored. */
    #[Test]
    #[DataProvider('configurationErrors')]
    public function configurationErrorsNeverCarryTheSecret(string $secret, int $tolerance, string $message): void
    {
        $previous = ini_set('zend.exception_ignore_args', '0');
        try {
            Webhook::verify(self::BODY, 't=' . self::T . ',v1=' . self::V1, $secret, $tolerance);
            self::fail('expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            self::assertSame($message, $e->getMessage());
            // Frame 0 is the call to Webhook::verify (the outer frames are this test, whose own arguments hold the
            // secret). Positive control: the frame does record arguments (the body is there).
            $frame = print_r($e->getTrace()[0], true);
            self::assertTrue(str_contains($frame, 'evt_1'), 'the trace records arguments');
            self::assertFalse(str_contains($frame, 'ababab'), 'the trace carries the secret');
            self::assertFalse(str_contains(explode("\n", $e->getTraceAsString())[0], 'ababab'), 'the trace string carries the secret');
        } finally {
            ini_set('zend.exception_ignore_args', (string) $previous);
        }
    }
}
