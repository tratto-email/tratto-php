<?php

declare(strict_types=1);

namespace Tratto\Resources;

final class Analytics extends Resource
{
    /**
     * @param string $period 7d, 30d, 90d, 180d, 1y (180d and 1y: live data only)
     *
     * @return array<mixed>
     */
    public function getSummary(string $period = '30d'): array
    {
        return $this->requestData('GET', '/v1/analytics/summary' . self::query(['period' => $period]));
    }

    /**
     * @param string $period 7d, 30d, 90d, 180d, 1y
     *
     * @return array<mixed> time series points
     */
    public function getTimeseries(string $period = '30d'): array
    {
        return $this->requestData('GET', '/v1/analytics/timeseries' . self::query(['period' => $period]));
    }
}
