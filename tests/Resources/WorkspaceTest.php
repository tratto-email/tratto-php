<?php

declare(strict_types=1);

namespace Tratto\Tests\Resources;

use PHPUnit\Framework\Attributes\Test;

final class WorkspaceTest extends ResourceTestCase
{
    #[Test]
    public function getUnwrapsData(): void
    {
        $this->fake->queue(200, ['data' => ['id' => 'ws_1', 'name' => 'Acme']]);

        self::assertSame(['id' => 'ws_1', 'name' => 'Acme'], $this->client->workspace->get());
        $this->assertRequest('GET', '/v1/workspace', null);
    }

    #[Test]
    public function updatePatchesTheWorkspace(): void
    {
        $this->fake->queue(200, ['data' => ['id' => 'ws_1', 'timezone' => 'Europe/Rome']]);

        self::assertSame(['id' => 'ws_1', 'timezone' => 'Europe/Rome'], $this->client->workspace->update(['timezone' => 'Europe/Rome']));
        $this->assertRequest('PATCH', '/v1/workspace', '{"timezone":"Europe/Rome"}');
    }

    #[Test]
    public function updatePreferencesPatchesThePreferences(): void
    {
        $this->fake->queue(200, ['data' => ['marketing' => false]]);

        self::assertSame(['marketing' => false], $this->client->workspace->updatePreferences(['marketing' => false]));
        $this->assertRequest('PATCH', '/v1/workspace/preferences', '{"marketing":false}');
    }
}
