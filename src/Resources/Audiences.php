<?php

declare(strict_types=1);

namespace Tratto\Resources;

final class Audiences extends Resource
{
    /**
     * @param array<string, mixed> $params name, ...
     *
     * @return array<mixed> {id}
     */
    public function create(array $params): array
    {
        return $this->requestData('POST', '/v1/audiences', $params);
    }

    /**
     * @param array<string, mixed> $params after, limit
     *
     * @return array<mixed> {data, pagination}
     */
    public function list(array $params = []): array
    {
        return $this->request('GET', '/v1/audiences' . self::query($params)) ?? [];
    }

    /** @return array<mixed> */
    public function get(string $id): array
    {
        return $this->requestData('GET', '/v1/audiences/' . self::id($id));
    }

    /**
     * @param list<string> $contactIds
     *
     * @return array<mixed> {added, alreadyInAudience, notFound}
     */
    public function addContacts(string $audienceId, array $contactIds): array
    {
        // array_values: always a JSON array, even if the caller's array has keys
        return $this->requestData('POST', '/v1/audiences/' . self::id($audienceId) . '/contacts', ['contactIds' => array_values($contactIds)]);
    }
}
