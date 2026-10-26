<?php

declare(strict_types=1);

namespace Tratto;

use Tratto\Http\CurlTransport;
use Tratto\Http\HttpClient;
use Tratto\Http\Transport;
use Tratto\Resources\Analytics;
use Tratto\Resources\Attachments;
use Tratto\Resources\Audiences;
use Tratto\Resources\Campaigns;
use Tratto\Resources\Contacts;
use Tratto\Resources\Domains;
use Tratto\Resources\Emails;
use Tratto\Resources\Flows;
use Tratto\Resources\Templates;
use Tratto\Resources\Webhooks;
use Tratto\Resources\Workspace;

final class Client
{
    public const VERSION = '1.2.0';
    public const DEFAULT_BASE_URL = 'https://api.tratto.email';
    public const DEFAULT_TIMEOUT = 30.0;

    public readonly Emails $emails;
    public readonly Contacts $contacts;
    public readonly Audiences $audiences;
    public readonly Campaigns $campaigns;
    public readonly Templates $templates;
    public readonly Webhooks $webhooks;
    public readonly Domains $domains;
    public readonly Analytics $analytics;
    public readonly Flows $flows;
    public readonly Workspace $workspace;
    public readonly Attachments $attachments;

    /**
     * @param array{baseUrl?: string, timeout?: int|float, transport?: Transport} $options
     */
    public function __construct(#[\SensitiveParameter] string $apiKey, array $options = [])
    {
        // Exception traces show the current value of parameters, and PHP 8.1 ignores #[\SensitiveParameter]:
        // move the key into a local before anything can throw.
        $key = $apiKey;
        $apiKey = '[redacted]';
        if ($key === '') {
            throw new \InvalidArgumentException('apiKey is required');
        }
        $unknown = array_diff(array_keys($options), ['baseUrl', 'timeout', 'transport']);
        if ($unknown !== []) {
            throw new \InvalidArgumentException('Unknown option(s): ' . implode(', ', $unknown));
        }

        $baseUrl = $options['baseUrl'] ?? self::DEFAULT_BASE_URL;
        $timeout = $options['timeout'] ?? self::DEFAULT_TIMEOUT;
        $transport = $options['transport'] ?? new CurlTransport();
        if (!is_string($baseUrl) || preg_match('#^https?://[^\x00-\x20\x7f]+$#D', $baseUrl) !== 1) {
            throw new \InvalidArgumentException('baseUrl must be an http(s) URL');
        }
        if (!(is_int($timeout) || is_float($timeout)) || $timeout <= 0) {
            throw new \InvalidArgumentException('timeout must be a positive number of seconds');
        }
        if (!$transport instanceof Transport) {
            throw new \InvalidArgumentException('transport must implement ' . Transport::class);
        }

        $http = new HttpClient($key, rtrim($baseUrl, '/'), (float) $timeout, $transport);
        $this->emails = new Emails($http);
        $this->webhooks = new Webhooks($http);
        $this->contacts = new Contacts($http);
        $this->audiences = new Audiences($http);
        $this->domains = new Domains($http);
        $this->campaigns = new Campaigns($http);
        $this->templates = new Templates($http);
        $this->analytics = new Analytics($http);
        $this->flows = new Flows($http);
        $this->workspace = new Workspace($http);
        $this->attachments = new Attachments($http);
    }
}
