<?php

declare(strict_types=1);

namespace Tratto\Resources;

/** The API accepts flow calls only from keys with full ("*") permission: there is no flows scope. */
final class Flows extends Resource
{
    /**
     * @param array<string, mixed> $params after, limit
     *
     * @return array<mixed> {data, pagination}
     */
    public function list(array $params = []): array
    {
        return $this->request('GET', '/v1/flows' . self::query($params)) ?? [];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<mixed> {id}
     */
    public function create(array $params): array
    {
        return $this->requestData('POST', '/v1/flows', $params);
    }

    /** @return array<mixed> */
    public function get(string $id): array
    {
        return $this->requestData('GET', '/v1/flows/' . self::id($id));
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<mixed>
     */
    public function update(string $id, array $params): array
    {
        return $this->requestData('PATCH', '/v1/flows/' . self::id($id), $params);
    }

    /** @return array<mixed> {id} */
    public function delete(string $id): array
    {
        return $this->requestData('DELETE', '/v1/flows/' . self::id($id));
    }

    /** @return array<mixed> */
    public function activate(string $id): array
    {
        return $this->requestData('POST', '/v1/flows/' . self::id($id) . '/activate', []);
    }

    /** @return array<mixed> */
    public function deactivate(string $id): array
    {
        return $this->requestData('POST', '/v1/flows/' . self::id($id) . '/deactivate', []);
    }
}
