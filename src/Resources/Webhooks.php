<?php

declare(strict_types=1);

namespace Tratto\Resources;

final class Webhooks extends Resource
{
    /**
     * @param array<string, mixed> $params url, events
     *
     * @return array<mixed> {id, secret}
     */
    public function create(array $params): array
    {
        return $this->requestData('POST', '/v1/webhooks', $params);
    }

    /** @return array<mixed> the webhooks (not paginated) */
    public function list(): array
    {
        return $this->requestData('GET', '/v1/webhooks');
    }

    public function delete(string $id): void
    {
        $this->request('DELETE', '/v1/webhooks/' . self::id($id));
    }

    /**
     * @param array<string, mixed> $params after, limit
     *
     * @return array<mixed> {data, pagination}
     */
    public function listDeliveries(string $id, array $params = []): array
    {
        return $this->request('GET', '/v1/webhooks/' . self::id($id) . '/deliveries' . self::query($params)) ?? [];
    }

    /** @return array<mixed> {queued} */
    public function test(string $id): array
    {
        return $this->requestData('POST', '/v1/webhooks/' . self::id($id) . '/test', []);
    }

    /** @return array<mixed> {secret} */
    public function rotateSecret(string $id): array
    {
        return $this->requestData('POST', '/v1/webhooks/' . self::id($id) . '/rotate-secret', []);
    }
}
