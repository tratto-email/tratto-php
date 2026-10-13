<?php

declare(strict_types=1);

namespace Tratto;

/**
 * Every failure of an API call: HTTP errors, unreadable responses and network errors.
 *
 * getCode() returns the HTTP status (0 for network errors). It never carries request headers.
 */
final class TrattoException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $statusCode,
        public readonly ?string $docs = null,
        public readonly ?string $suggestion = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }
}
