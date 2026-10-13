<?php

/**
 * Symfony: a service that uses Tratto\Client, injected by type (see config/services.yaml).
 */

declare(strict_types=1);

namespace App\Service;

use Tratto\Client;

final class WelcomeMailer
{
    public function __construct(private readonly Client $tratto) {}

    public function send(string $email, string $firstName, string $userId): string
    {
        $sent = $this->tratto->emails->send([
            'from' => 'Acme <hello@mail.acme.com>',
            'to' => $email,
            'subject' => 'Welcome to Acme',
            'markdown' => "# Welcome, {{firstName}}\n\nGlad to have you on board.",
            'variables' => ['firstName' => $firstName],
        ], 'welcome-' . $userId);

        return (string) $sent['id'];
    }
}
