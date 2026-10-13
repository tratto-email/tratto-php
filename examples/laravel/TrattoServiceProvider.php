<?php

/**
 * Laravel: one Tratto\Client for the whole app, built from config.
 *
 * 1. config/services.php:
 *        'tratto' => [
 *            'key' => env('TRATTO_API_KEY'),
 *            'webhook_secret' => env('TRATTO_WEBHOOK_SECRET'),
 *        ],
 * 2. Register this provider (bootstrap/providers.php, or config/app.php on older apps).
 * 3. Type-hint Tratto\Client wherever you need it: controllers, jobs, listeners.
 *
 *        public function store(Request $request, Client $tratto) {
 *            $tratto->emails->send([...], 'welcome-' . $user->id);
 *        }
 */

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Tratto\Client;

final class TrattoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Client::class, static function (): Client {
            $apiKey = config('services.tratto.key');
            if (!is_string($apiKey) || $apiKey === '') {
                throw new \RuntimeException('Set TRATTO_API_KEY (config/services.php, tratto.key).');
            }

            return new Client($apiKey);
        });
    }
}
