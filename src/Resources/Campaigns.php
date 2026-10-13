<?php

declare(strict_types=1);

namespace Tratto\Resources;

final class Campaigns extends Resource
{
    /**
     * @param array<string, mixed> $params
     *
     * @return array<mixed> {id}
     */
    public function create(array $params): array
    {
        return $this->requestData('POST', '/v1/campaigns', $params);
    }

    /**
     * @param array<string, mixed> $params status, after, limit
     *
     * @return array<mixed> {data, pagination}
     */
    public function list(array $params = []): array
    {
        return $this->request('GET', '/v1/campaigns' . self::query($params)) ?? [];
    }

    /** @return array<mixed> */
    public function get(string $id): array
    {
        return $this->requestData('GET', '/v1/campaigns/' . self::id($id));
    }

    /** @return array<mixed> */
    public function getStats(string $id): array
    {
        return $this->requestData('GET', '/v1/campaigns/' . self::id($id) . '/stats');
    }

    /**
     * Sends now, or schedules with "scheduledAt" (a \DateTimeInterface, sent in UTC, or an ISO 8601 string).
     * Like tratto-node, only scheduledAt is sent.
     *
     * @param array{scheduledAt?: \DateTimeInterface|string|null} $params
     *
     * @return array<mixed> {status}
     */
    public function send(string $id, array $params = []): array
    {
        $scheduledAt = $params['scheduledAt'] ?? null;
        if ($scheduledAt !== null && !is_string($scheduledAt) && !$scheduledAt instanceof \DateTimeInterface) {
            throw new \InvalidArgumentException('scheduledAt must be a \DateTimeInterface or an ISO 8601 string');
        }

        return $this->requestData('POST', '/v1/campaigns/' . self::id($id) . '/send', $scheduledAt === null || $scheduledAt === '' ? [] : ['scheduledAt' => $scheduledAt]);
    }

    /**
     * Cancels a scheduled send: the campaign goes back to draft. Only a campaign still waiting for its date
     * can be unscheduled; anything else answers 409 (use pause() to stop a running send).
     *
     * @return array<mixed> {id, status, scheduledAt}
     */
    public function unschedule(string $id): array
    {
        return $this->requestData('POST', '/v1/campaigns/' . self::id($id) . '/unschedule', []);
    }

    /** @return array<mixed> {status} */
    public function pause(string $id): array
    {
        return $this->requestData('POST', '/v1/campaigns/' . self::id($id) . '/pause', []);
    }

    /** @return array<mixed> {emailId} */
    public function testSend(string $id, string $to): array
    {
        return $this->requestData('POST', '/v1/campaigns/' . self::id($id) . '/test-send', ['to' => $to]);
    }
}
