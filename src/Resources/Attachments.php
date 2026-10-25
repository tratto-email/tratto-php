<?php

declare(strict_types=1);

namespace Tratto\Resources;

final class Attachments extends Resource
{
    /**
     * Uploads a file and returns its metadata. Reference it from emails->send() or emails->sendBatch() with
     * 'attachments' => [['id' => $attachment['id']]]. The bytes are sent as they are, with $contentType,
     * never JSON-encoded (a .json file included). Not available on the Free plan (403 PLAN_UPGRADE_REQUIRED);
     * a full workspace storage answers 429 QUOTA_EXCEEDED.
     *
     * @param string|open-resource $content     the file bytes, or a readable stream (read from its current position)
     * @param string               $filename    as the recipient will see it, UTF-8; non-ASCII names are supported
     * @param string               $contentType e.g. application/pdf
     *
     * @return array<mixed> {id, filename, contentType, size, sha256, livemode, expiresAt}
     */
    public function upload(mixed $content, string $filename, string $contentType): array
    {
        if (is_resource($content)) {
            $content = stream_get_contents($content);
            if ($content === false) {
                throw new \InvalidArgumentException('Could not read the attachment stream');
            }
        } elseif (!is_string($content)) {
            throw new \InvalidArgumentException('content must be a string or a readable stream');
        }

        return $this->http->request(
            'POST',
            '/v1/attachments',
            $content,
            ['Content-Disposition' => self::contentDisposition($filename)],
            $contentType,
            unwrap: true,
        ) ?? [];
    }

    /**
     * Metadata only: the bytes are never served back.
     *
     * @return array<mixed> {id, filename, contentType, size, sha256, livemode, expiresAt}
     */
    public function get(string $id): array
    {
        return $this->requestData('GET', '/v1/attachments/' . self::id($id));
    }

    /** Frees its storage at once. Emails already queued with it fail with failureReason attachment_unavailable. */
    public function delete(string $id): void
    {
        $this->request('DELETE', '/v1/attachments/' . self::id($id));
    }

    /**
     * Printable ASCII goes in a quoted filename; anything else also in filename*=UTF-8''... (RFC 6266, RFC 5987),
     * with an ASCII fallback, because header values must be ASCII.
     */
    private static function contentDisposition(string $filename): string
    {
        $quoted = static fn(string $s): string => '"' . addcslashes($s, '"\\') . '"';
        if (preg_match('/^[\x20-\x7e]*$/D', $filename) === 1) {
            return 'attachment; filename=' . $quoted($filename);
        }
        $fallback = preg_replace('/[^\x20-\x7e]/u', '_', $filename);
        if ($fallback === null) {
            throw new \InvalidArgumentException('filename must be valid UTF-8');
        }

        // rawurlencode leaves only A-Z a-z 0-9 - _ . ~ as they are: all of them are RFC 5987 attr-chars.
        return 'attachment; filename=' . $quoted($fallback) . "; filename*=UTF-8''" . rawurlencode($filename);
    }
}
