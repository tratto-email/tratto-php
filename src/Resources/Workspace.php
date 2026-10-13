<?php

declare(strict_types=1);

namespace Tratto\Resources;

/**
 * Not ported from tratto-node: delete, inviteMember and removeMember (deprecated, 403 for every API key)
 * and updateMember (its route is being removed). Members are managed from the dashboard.
 */
final class Workspace extends Resource
{
    /** @return array<mixed> */
    public function get(): array
    {
        return $this->requestData('GET', '/v1/workspace');
    }

    /**
     * @param array<string, mixed> $params name, slug, timezone, locale, defaultFromName, defaultFromEmail, ...
     *
     * @return array<mixed>
     */
    public function update(array $params): array
    {
        return $this->requestData('PATCH', '/v1/workspace', $params);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<mixed>
     */
    public function updatePreferences(array $params): array
    {
        return $this->requestData('PATCH', '/v1/workspace/preferences', $params);
    }
}
