<?php

/**
 * Workspace: read and update settings and preferences.
 * Members are invited, changed and removed from the dashboard: the SDK has no member methods.
 *
 *   TRATTO_API_KEY=tratto_test_... php examples/workspace.php
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Tratto\Client;

run_example(static function (Client $tratto): void {
    $workspace = $tratto->workspace->get();
    echo "{$workspace['name']} ({$workspace['slug']}), plan {$workspace['plan']}\n";

    $updated = $tratto->workspace->update(['timezone' => 'Europe/Rome']);
    echo "Timezone: {$updated['timezone']}\n";

    $tratto->workspace->updatePreferences([
        'locale' => 'en',
        'emailNotifications' => ['bounces' => true, 'weeklyReport' => false],
    ]);
});
