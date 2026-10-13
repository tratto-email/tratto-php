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
