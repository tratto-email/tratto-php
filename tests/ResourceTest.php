<?php

declare(strict_types=1);

namespace Tratto\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tratto\Resources\Resource;

final class ResourceTest extends TestCase
{
    /** @param array<string, mixed> $params */
    private static function query(array $params): string
    {
        return (new \ReflectionMethod(Resource::class, 'query'))->invoke(null, $params);
    }

    private static function id(string $id): string
    {
        return (new \ReflectionMethod(Resource::class, 'id'))->invoke(null, $id);
    }

    #[Test]
    public function buildsAnEmptyQuery(): void
    {
        self::assertSame('', self::query([]));
        self::assertSame('', self::query(['after' => null, 'limit' => null]));
    }

    #[Test]
    public function buildsAQueryLikeNode(): void
    {
        $date = new \DateTime('2026-10-13T12:00:00.5+02:00');
        $query = self::query([
            'after' => 'cur_1',
            'limit' => 10,
            'skip' => null,
            'ratio' => 1.5,
            'active' => true,
            'archived' => false,
            'dateFrom' => $date,
            'q' => 'a b&c=d/é',
        ]);

        self::assertSame('?after=cur_1&limit=10&ratio=1.5&active=true&archived=false&dateFrom=2026-10-13T10%3A00%3A00.500Z&q=a%20b%26c%3Dd%2F%C3%A9', $query);
        self::assertSame('+02:00', $date->getTimezone()->getName(), 'the caller\'s date must not be mutated');
    }

    #[Test]
    public function rejectsNonScalarQueryValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Query parameter "tags" must be a scalar, a date or null');
        self::query(['tags' => ['a', 'b']]);
    }

    /** @return iterable<string, array{string, string}> */
    public static function ids(): iterable
    {
        yield 'plain' => ['em_123', 'em_123'];
        yield 'slash' => ['a/../b', 'a%2F..%2Fb'];
        yield 'query' => ['x?y=1#z', 'x%3Fy%3D1%23z'];
        yield 'space' => ['a b', 'a%20b'];
        yield 'dots inside' => ['a..b', 'a..b'];
    }

    #[Test]
    #[DataProvider('ids')]
    public function encodesIdsAsOnePathSegment(string $id, string $expected): void
    {
        self::assertSame($expected, self::id($id));
    }

    /** @return iterable<string, array{string}> */
    public static function badIds(): iterable
    {
        yield 'empty' => [''];
        yield 'dot' => ['.'];
        yield 'dot dot' => ['..'];
    }

    #[Test]
    #[DataProvider('badIds')]
    public function rejectsIdsThatWouldChangeTheRoute(string $id): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid id: \'' . $id . '\'');
        self::id($id);
    }
}
