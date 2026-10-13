<?php

declare(strict_types=1);

namespace Tratto\Resources;

final class Domains extends Resource
{
    /** @return array<mixed> the domain, with its DNS records */
    public function add(string $domain): array
    {
        return $this->requestData('POST', '/v1/domains', ['domain' => $domain]);
    }

    /**
     * @param array<string, mixed> $params after, limit
     *
     * @return array<mixed> {data, pagination}
     */
    public function list(array $params = []): array
    {
        return $this->request('GET', '/v1/domains' . self::query($params)) ?? [];
    }

    /** @return array<mixed> */
    public function get(string $id): array
    {
        return $this->requestData('GET', '/v1/domains/' . self::id($id));
    }

    /** @return array<mixed> */
    public function verify(string $id): array
    {
        return $this->requestData('POST', '/v1/domains/' . self::id($id) . '/verify', []);
    }

    /** @return array<mixed> {id, deletedAt} */
    public function delete(string $id): array
    {
        return $this->requestData('DELETE', '/v1/domains/' . self::id($id));
    }
}
