<?php
declare(strict_types=1);

namespace PowerDiscount\Tests\Unit\Persistence;

use PHPUnit\Framework\TestCase;
use PowerDiscount\Persistence\JsonSerializer;

final class JsonSerializerTest extends TestCase
{
    public function testEncodeArray(): void
    {
        $json = JsonSerializer::encode(['a' => 1, 'b' => ['c' => 2]]);
        self::assertSame('{"a":1,"b":{"c":2}}', $json);
    }

    public function testEncodeEmptyArrayProducesEmptyObject(): void
    {
        self::assertSame('[]', JsonSerializer::encode([]));
    }

    public function testDecodeValidJson(): void
    {
        self::assertSame(['x' => 1], JsonSerializer::decode('{"x":1}'));
    }

    public function testDecodeEmptyOrNullReturnsEmptyArray(): void
    {
        self::assertSame([], JsonSerializer::decode(''));
        self::assertSame([], JsonSerializer::decode(null));
    }

    public function testDecodeInvalidJsonReturnsEmptyArray(): void
    {
        self::assertSame([], JsonSerializer::decode('{not json'));
    }

    public function testDecodeNonArrayJsonReturnsEmptyArray(): void
    {
        self::assertSame([], JsonSerializer::decode('"string"'));
        self::assertSame([], JsonSerializer::decode('42'));
    }
}
