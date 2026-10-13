<?php

/**
 * Analytics: summary and daily timeseries. Periods: 7d, 30d (default), 90d, 180d, 1y.
 * 180d and 1y hold live data only and are rejected for test keys.
 *
 *   TRATTO_API_KEY=tratto_live_... php examples/analytics.php
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Tratto\Client;

run_example(static function (Client $tratto): void {
    $summary = $tratto->analytics->getSummary();
    echo "Last 30 days: {$summary['totalSent']} sent, {$summary['delivered']} delivered, {$summary['opened']} opened\n";

    foreach ($tratto->analytics->getTimeseries('7d') as $point) {
        echo "  {$point['date']}  sent {$point['sent']}  delivered {$point['delivered']}\n";
    }
});
