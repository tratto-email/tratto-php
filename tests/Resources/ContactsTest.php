<?php

declare(strict_types=1);

namespace Tratto\Tests\Resources;

use PHPUnit\Framework\Attributes\Test;
use Tratto\TrattoException;

final class ContactsTest extends ResourceTestCase
{
    #[Test]
    public function createPostsTheParamsAndUnwrapsData(): void
    {
        $this->fake->queue(201, ['data' => ['id' => 'ct_1']]);

        self::assertSame(['id' => 'ct_1'], $this->client->contacts->create(['email' => 'ann@x.it', 'tags' => ['vip'], 'customFields' => ['plan' => 'pro']]));
        $this->assertRequest('POST', '/v1/contacts', '{"email":"ann@x.it","tags":["vip"],"customFields":{"plan":"pro"}}');
    }

    #[Test]
    public function listReturnsTheWholeEnvelope(): void
    {
        $page = ['data' => [['id' => 'ct_1', 'email' => 'ann@x.it']], 'pagination' => ['hasMore' => false, 'nextCursor' => null]];
        $this->fake->queue(200, $page);

        self::assertSame($page, $this->client->contacts->list());
        $this->assertRequest('GET', '/v1/contacts', null);
    }

    /** q (email prefix search) is passed as in tratto-node 1.4.0 (963ca98). */
    #[Test]
    public function listPassesQAndTheFilters(): void
    {
        $this->client->contacts->list(['q' => 'ann+promo@x.it', 'status' => 'subscribed', 'audienceId' => null, 'tag' => 'vip', 'after' => 'ct_9', 'limit' => 20]);
        $this->assertRequest('GET', '/v1/contacts?q=ann%2Bpromo%40x.it&status=subscribed&tag=vip&after=ct_9&limit=20', null);
    }

    #[Test]
    public function updatePatchesAndEncodesTheId(): void
    {
        $this->fake->queue(200, ['data' => ['id' => 'ct/1']]);

        self::assertSame(['id' => 'ct/1'], $this->client->contacts->update('ct/1', ['firstName' => 'Ann']));
        $this->assertRequest('PATCH', '/v1/contacts/ct%2F1', '{"firstName":"Ann"}');
    }

    /** The CSV goes out byte for byte as text/csv: never JSON-encoded (no quotes, no escaped slashes or unicode). */
    #[Test]
    public function importCsvSendsTheRawCsv(): void
    {
        $csv = "\u{FEFF}email,firstName,note\r\nann@x.it,Zoë,\"a/b, \"\"quoted\"\"\"\r\nbob@y.it,Bob,\n";
        $this->fake->queue(202, ['data' => ['jobId' => 'imp_1', 'totalRows' => 2]]);

        self::assertSame(['jobId' => 'imp_1', 'totalRows' => 2], $this->client->contacts->importCsv($csv));
        $this->assertRequest('POST', '/v1/contacts/import', $csv, 'text/csv');
    }

    /**
     * An empty CSV is not rejected by the SDK: it is sent (empty body, text/csv), and the API answers
     * 400 VALIDATION_ERROR (tratto-api contacts.ts, "Request body must be text/csv"), which surfaces
     * as a TrattoException. One place validates the CSV: the API.
     */
    #[Test]
    public function importCsvSendsAnEmptyCsvAndSurfacesTheApiError(): void
    {
        $this->fake->queue(400, ['error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Request body must be text/csv']]);

        try {
            $this->client->contacts->importCsv('');
            self::fail('expected TrattoException');
        } catch (TrattoException $e) {
            self::assertSame('VALIDATION_ERROR', $e->errorCode);
            self::assertSame(400, $e->statusCode);
        }
        $this->assertRequest('POST', '/v1/contacts/import', '', 'text/csv');
    }

    /** A non-string CSV is a TypeError before any request (the SDK declares strict_types and a string parameter). */
    #[Test]
    public function importCsvRejectsANonString(): void
    {
        try {
            // @phpstan-ignore argument.type (the point of the test)
            $this->client->contacts->importCsv(['email' => 'ann@x.it']);
            self::fail('expected TypeError');
        } catch (\TypeError $e) {
            self::assertStringContainsString('must be of type string', $e->getMessage());
        }
        self::assertSame([], $this->fake->requests);
    }

    #[Test]
    public function getImportJobUnwrapsData(): void
    {
        $job = ['jobId' => 'imp_1', 'status' => 'completed', 'totalRows' => 2, 'processedRows' => 2, 'failedRows' => 0, 'errors' => [], 'completedAt' => '2026-10-13T10:00:00.000Z'];
        $this->fake->queue(200, ['data' => $job]);

        self::assertSame($job, $this->client->contacts->getImportJob('imp 1'));
        $this->assertRequest('GET', '/v1/contacts/import/imp%201', null);
    }

    #[Test]
    public function exposesExactlyTheNodeMethods(): void
    {
        self::assertPublicMethods($this->client->contacts, ['create', 'getImportJob', 'importCsv', 'list', 'update']);
    }
}
