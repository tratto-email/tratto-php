<?php

declare(strict_types=1);

namespace Tratto\Tests\Resources;

use PHPUnit\Framework\Attributes\Test;
use Tratto\TrattoException;

final class AttachmentsTest extends ResourceTestCase
{
    private const ATT = [
        'id' => 'att_123456789012345678901', 'filename' => 'invoice.pdf', 'contentType' => 'application/pdf',
        'size' => 3, 'sha256' => 'ab', 'livemode' => false, 'expiresAt' => '2030-01-01T00:00:00.000Z',
    ];

    #[Test]
    public function uploadPostsTheRawBytesWithContentTypeAndDisposition(): void
    {
        $this->fake->queue(201, ['data' => self::ATT]);

        self::assertSame(self::ATT, $this->client->attachments->upload("%PD\x00\xff", 'invoice.pdf', 'application/pdf'));
        $this->assertRequest('POST', '/v1/attachments', "%PD\x00\xff", 'application/pdf');
        self::assertSame('attachment; filename="invoice.pdf"', $this->fake->last()['headers']['Content-Disposition']);
    }

    #[Test]
    public function uploadNeverJsonEncodesAJsonFile(): void
    {
        $this->client->attachments->upload('{"a":1}', 'data.json', 'application/json');
        $this->assertRequest('POST', '/v1/attachments', '{"a":1}', 'application/json');
    }

    #[Test]
    public function uploadReadsAStreamFromItsCurrentPosition(): void
    {
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, 'skip:bytes');
        fseek($stream, 5);

        $this->client->attachments->upload($stream, 'a.bin', 'application/octet-stream');
        fclose($stream);
        $this->assertRequest('POST', '/v1/attachments', 'bytes', 'application/octet-stream');
    }

    #[Test]
    public function uploadRejectsOtherContentTypesBeforeSending(): void
    {
        try {
            // @phpstan-ignore argument.type (the point of the test)
            $this->client->attachments->upload(['a'], 'a.bin', 'application/octet-stream');
            self::fail('expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('content must be a string or a readable stream', $e->getMessage());
        }
        self::assertSame([], $this->fake->requests);
    }

    #[Test]
    public function uploadRejectsAContentTypeWithALineBreak(): void
    {
        $this->expectException(TrattoException::class);
        try {
            $this->client->attachments->upload('x', 'a.txt', "text/plain\r\nX-Evil: 1");
        } finally {
            self::assertSame([], $this->fake->requests);
        }
    }

    #[Test]
    public function uploadSurfacesQuotaExceeded(): void
    {
        $this->fake->queue(429, ['error' => ['code' => 'QUOTA_EXCEEDED', 'message' => 'storage full']]);
        try {
            $this->client->attachments->upload('x', 'a.bin', 'application/octet-stream');
            self::fail('expected TrattoException');
        } catch (TrattoException $e) {
            self::assertSame(429, $e->statusCode);
            self::assertSame('QUOTA_EXCEEDED', $e->errorCode);
        }
    }

    /** Same vectors as tratto-node contentDisposition(). */
    #[Test]
    public function encodesTheFilename(): void
    {
        $cases = [
            'a "b"\\c.pdf' => 'attachment; filename="a \\"b\\"\\\\c.pdf"',
            "fattura n°1 (l'ultima).pdf" => "attachment; filename=\"fattura n_1 (l'ultima).pdf\"; filename*=UTF-8''fattura%20n%C2%B01%20%28l%27ultima%29.pdf",
            "a\r\nb.pdf" => "attachment; filename=\"a__b.pdf\"; filename*=UTF-8''a%0D%0Ab.pdf",
        ];
        foreach ($cases as $filename => $expected) {
            $this->client->attachments->upload('x', $filename, 'application/pdf');
            self::assertSame($expected, $this->fake->last()['headers']['Content-Disposition']);
        }
    }

    #[Test]
    public function rejectsAFilenameThatIsNotUtf8(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->client->attachments->upload('x', "a\xff.pdf", 'application/pdf');
    }

    #[Test]
    public function getUnwrapsData(): void
    {
        $this->fake->queue(200, ['data' => self::ATT]);

        self::assertSame(self::ATT, $this->client->attachments->get(self::ATT['id']));
        $this->assertRequest('GET', '/v1/attachments/' . self::ATT['id'], null);
    }

    #[Test]
    public function deleteSendsDeleteAndAccepts204(): void
    {
        $this->fake->queue(204);

        $this->client->attachments->delete(self::ATT['id']);
        $this->assertRequest('DELETE', '/v1/attachments/' . self::ATT['id'], null);
    }

    #[Test]
    public function exposesExactlyTheNodeMethods(): void
    {
        self::assertPublicMethods($this->client->attachments, ['delete', 'get', 'upload']);
    }
}
