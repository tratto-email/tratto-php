<?php

declare(strict_types=1);

namespace Tratto\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * C4: SSL_VERIFYHOST=2. A certificate signed by a trusted CA but issued for another host must be refused.
 * The CA is trusted only in a child process (php -d curl.cainfo=...), so the SDK needs no option to accept it.
 */
final class CurlTransportVerifyHostTest extends TestCase
{
    #[Test]
    public function refusesATrustedCertificateForAnotherHost(): void
    {
        $openssl = trim((string) shell_exec('command -v openssl'));
        if ($openssl === '') {
            self::markTestSkipped('openssl CLI not available');
        }
        $dir = sys_get_temp_dir() . '/tratto-vh-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $run = static function (string $cmd) use ($dir): void {
            exec('cd ' . escapeshellarg($dir) . ' && ' . $cmd . ' 2>&1', $out, $code);
            self::assertSame(0, $code, implode("\n", $out));
        };
        $o = escapeshellarg($openssl);
        $run("$o req -x509 -newkey ec -pkeyopt ec_paramgen_curve:prime256v1 -nodes -keyout ca.key -out ca.pem -days 1 -subj /CN=TrattoTestCA -addext basicConstraints=critical,CA:true -addext keyUsage=keyCertSign");
        $run("$o req -newkey ec -pkeyopt ec_paramgen_curve:prime256v1 -nodes -keyout srv.key -out srv.csr -subj /CN=localhost");
        file_put_contents($dir . '/ext.cnf', "subjectAltName=DNS:localhost\n");
        $run("$o x509 -req -in srv.csr -CA ca.pem -CAkey ca.key -CAcreateserial -out srv.pem -days 1 -extfile ext.cnf");

        $sock = stream_socket_server('tcp://127.0.0.1:0') ?: throw new \RuntimeException('no port');
        $port = (int) substr((string) stream_socket_get_name($sock, false), strrpos((string) stream_socket_get_name($sock, false), ':') + 1);
        fclose($sock);
        $tls = proc_open([$openssl, 's_server', '-quiet', '-www', '-accept', (string) $port, '-cert', $dir . '/srv.pem', '-key', $dir . '/srv.key'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($tls);

        try {
            for ($i = 0; $i < 100 && !@fsockopen('127.0.0.1', $port, $e1, $e2, 0.1); $i++) {
                usleep(50_000);
            }
            $call = static function (string $url) use ($dir): string {
                $code = 'require $argv[1]; try { $r = (new Tratto\Http\CurlTransport())->send("GET", $argv[2], [], null, 5.0); echo "OK " . $r["status"]; } catch (Tratto\TrattoException $e) { echo "ERR " . $e->getMessage(); }';
                return (string) shell_exec(implode(' ', array_map('escapeshellarg', [PHP_BINARY, '-d', 'curl.cainfo=' . $dir . '/ca.pem', '-r', $code, dirname(__DIR__) . '/vendor/autoload.php', $url])));
            };

            // positive control: with the CA trusted and the right name, the transport connects
            $control = $call('https://localhost:' . $port . '/');
            if (!str_starts_with($control, 'OK ')) {
                self::markTestSkipped('this curl build ignores curl.cainfo: ' . $control);
            }
            $wrongHost = $call('https://127.0.0.1:' . $port . '/');
            self::assertStringStartsWith('ERR ', $wrongHost, 'a certificate for "localhost" must not be accepted for 127.0.0.1');
        } finally {
            proc_terminate($tls);
            proc_close($tls);
            array_map('unlink', glob($dir . '/*') ?: []);
            rmdir($dir);
        }
    }
}
