<?php

declare(strict_types=1);

namespace Tratto\Tests\Resources;

use PHPUnit\Framework\Attributes\Test;

final class AnalyticsTest extends ResourceTestCase
{
    #[Test]
    public function getSummaryDefaultsToThirtyDays(): void
    {
        $this->fake->queue(200, ['data' => ['period' => '30d', 'totalSent' => 10]]);

        self::assertSame(['period' => '30d', 'totalSent' => 10], $this->client->analytics->getSummary());
        $this->assertRequest('GET', '/v1/analytics/summary?period=30d', null);

        $this->client->analytics->getSummary('1y');
        $this->assertRequest('GET', '/v1/analytics/summary?period=1y', null);
    }

    #[Test]
    public function getTimeseriesDefaultsToThirtyDays(): void
    {
        $this->fake->queue(200, ['data' => [['date' => '2026-10-12', 'sent' => 3]]]);

        self::assertSame([['date' => '2026-10-12', 'sent' => 3]], $this->client->analytics->getTimeseries());
        $this->assertRequest('GET', '/v1/analytics/timeseries?period=30d', null);

        $this->client->analytics->getTimeseries('7d');
        $this->assertRequest('GET', '/v1/analytics/timeseries?period=7d', null);
    }
}
