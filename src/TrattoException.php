<?php

declare(strict_types=1);

namespace Tratto;

/**
 * Every failure of an API call: HTTP errors, unreadable responses and network errors.
 *
 * getCode() returns the HTTP status (0 for network errors). It never carries request headers.
 * $details is the API's error.details as it is (for a 422: a list of {path, message}), null when absent.
 */
final class TrattoException extends \RuntimeException
{
    /** @param array<mixed>|null $details */
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $statusCode,
        public readonly ?string $docs = null,
        public readonly ?string $suggestion = null,
        ?\Throwable $previous = null,
        public readonly ?array $details = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }
}
