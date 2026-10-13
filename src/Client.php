<?php

declare(strict_types=1);

namespace Tratto;

use Tratto\Http\CurlTransport;
use Tratto\Http\HttpClient;
use Tratto\Http\Transport;
use Tratto\Resources\Emails;

final class Client
{
    public const VERSION = '1.0.0';
    public const DEFAULT_BASE_URL = 'https://api.tratto.email';
    public const DEFAULT_TIMEOUT = 30.0;

    public readonly Emails $emails;

    /**
     * @param array{baseUrl?: string, timeout?: int|float, transport?: Transport} $options
     */
    public function __construct(#[\SensitiveParameter] string $apiKey, array $options = [])
    {
        if ($apiKey === '') {
            throw new \InvalidArgumentException('apiKey is required');
        }
        $unknown = array_diff(array_keys($options), ['baseUrl', 'timeout', 'transport']);
        if ($unknown !== []) {
            throw new \InvalidArgumentException('Unknown option(s): ' . implode(', ', $unknown));
        }

        $baseUrl = $options['baseUrl'] ?? self::DEFAULT_BASE_URL;
        $timeout = $options['timeout'] ?? self::DEFAULT_TIMEOUT;
        $transport = $options['transport'] ?? new CurlTransport();
        if (!is_string($baseUrl) || !preg_match('#^https?://#', $baseUrl)) {
            throw new \InvalidArgumentException('baseUrl must be an http(s) URL');
        }
        if (!(is_int($timeout) || is_float($timeout)) || $timeout <= 0) {
            throw new \InvalidArgumentException('timeout must be a positive number of seconds');
        }
        if (!$transport instanceof Transport) {
            throw new \InvalidArgumentException('transport must implement ' . Transport::class);
        }

        $http = new HttpClient($apiKey, rtrim($baseUrl, '/'), (float) $timeout, $transport);
        $this->emails = new Emails($http);
    }
}
