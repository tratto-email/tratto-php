<?php

/**
 * Symfony (6.4 or later): receive and verify Tratto webhooks. The secret comes from TRATTO_WEBHOOK_SECRET.
 */

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Tratto\Webhook;

final class TrattoWebhookController
{
    public function __construct(
        #[Autowire(env: 'TRATTO_WEBHOOK_SECRET')]
        private readonly string $webhookSecret,
    ) {}

    #[Route('/webhooks/tratto', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        // getContent() is the raw body: never a re-encoded JSON.
        $payload = $request->getContent();

        if (!Webhook::verify($payload, $request->headers->get('X-Tratto-Signature'), $this->webhookSecret)) {
            return new Response('', Response::HTTP_BAD_REQUEST);
        }

        $event = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        // Deliveries can repeat: use $event['id'] to process each event once.

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
