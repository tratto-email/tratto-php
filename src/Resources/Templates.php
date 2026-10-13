<?php

declare(strict_types=1);

namespace Tratto\Resources;

final class Templates extends Resource
{
    /**
     * @param array<string, mixed> $params limit, after, status
     *
     * @return array<mixed> {data, pagination}
     */
    public function list(array $params = []): array
    {
        return $this->request('GET', '/v1/templates' . self::query($params)) ?? [];
    }

    /**
     * With "markdown", format "emailmd" is added: the API requires it, so the caller only chooses the content field.
     *
     * @param array<string, mixed> $params name, html | markdown
     *
     * @return array<mixed>
     */
    public function create(array $params): array
    {
        if (array_key_exists('markdown', $params)) {
            $params = array_merge($params, ['format' => 'emailmd']);
        }

        return $this->requestData('POST', '/v1/templates', $params);
    }

    /** @return array<mixed> */
    public function get(string $id): array
    {
        return $this->requestData('GET', '/v1/templates/' . self::id($id));
    }

    /**
     * @param array<string, mixed> $params name, html | markdown
     *
     * @return array<mixed>
     */
    public function update(string $id, array $params): array
    {
        return $this->requestData('PATCH', '/v1/templates/' . self::id($id), $params);
    }

    public function delete(string $id): void
    {
        $this->request('DELETE', '/v1/templates/' . self::id($id));
    }

    /** @return array<mixed> [{version, savedAt}] */
    public function listVersions(string $id): array
    {
        return $this->requestData('GET', '/v1/templates/' . self::id($id) . '/versions');
    }

    /** @return array<mixed> {version, savedAt, html} */
    public function getVersion(string $id, int $version): array
    {
        return $this->requestData('GET', '/v1/templates/' . self::id($id) . '/versions/' . $version);
    }

    /**
     * @param array<string, string> $variables always sent as a JSON object, {} when empty
     *
     * @return array<mixed> {queued}
     */
    public function testSend(string $id, string $to, array $variables = []): array
    {
        return $this->requestData('POST', '/v1/templates/' . self::id($id) . '/test-send', ['to' => $to, 'variables' => (object) $variables]);
    }
}
