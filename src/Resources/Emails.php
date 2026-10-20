<?php

declare(strict_types=1);

namespace Tratto\Resources;

final class Emails extends Resource
{
    /**
     * @param array<string, mixed> $params from, to, subject, html|markdown|text, ...
     *
     * @return array<mixed> {id}
     */
    public function send(array $params, ?string $idempotencyKey = null): array
    {
        $headers = $idempotencyKey === null || $idempotencyKey === '' ? [] : ['Idempotency-Key' => $idempotencyKey];

        return $this->requestData('POST', '/v1/emails', $params, $headers);
    }

    /**
     * Sends 1 to 100 emails in one request (requires API 0.22.0). Each element takes the fields of send()
     * except scheduledAt and attachments (the API answers 422). One idempotency key covers the whole batch.
     *
     * A 202 with failed > 0 is returned, not thrown: resend only the failed emails, in a new batch with a new key.
     *
     * @param array<array<string, mixed>> $emails sent as a JSON list, whatever the keys
     *
     * @return array<mixed> {batchId, livemode, accepted, failed, emails: list<{id?, status: 'queued'|'failed', to, error?: {code, message}}>}
     *                      in the order of the request; a failed email has no id
     */
    public function sendBatch(array $emails, ?string $idempotencyKey = null): array
    {
        $headers = $idempotencyKey === null || $idempotencyKey === '' ? [] : ['Idempotency-Key' => $idempotencyKey];

        return $this->requestData('POST', '/v1/emails/batch', ['emails' => array_values($emails)], $headers);
    }

    /**
     * @param array<string, mixed> $params after, limit, status, domainId, tags, dateFrom, dateTo
     *
     * @return array<mixed> {data, pagination}
     */
    public function list(array $params = []): array
    {
        return $this->request('GET', '/v1/emails' . self::query($params)) ?? [];
    }

    /** @return array<mixed> */
    public function get(string $id): array
    {
        return $this->requestData('GET', '/v1/emails/' . self::id($id));
    }

    /** @return array<mixed> */
    public function listEvents(string $id): array
    {
        return $this->requestData('GET', '/v1/emails/' . self::id($id) . '/events');
    }
}
