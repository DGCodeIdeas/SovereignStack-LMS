<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Shared;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Shared\Infrastructure\Uuid\SymfonyUidUuidGenerator;

final class Uuidv7Test extends TestCase
{
    public function testGeneratesUuidv7Format(): void
    {
        $generator = new SymfonyUidUuidGenerator();
        $uuid = $generator->generate();

        $this->assertSame(36, strlen($uuid), 'UUID must be 36 characters');
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuid,
            'UUID must match RFC 9562 UUIDv7 format',
        );
    }

    public function testUuidVersionIs7(): void
    {
        $generator = new SymfonyUidUuidGenerator();
        $uuid = $generator->generate();

        $version = substr($uuid, 14, 1);
        $this->assertSame('7', $version, 'UUID version nibble must be 7');
    }

    public function testGeneratedUuidsAreUnique(): void
    {
        $generator = new SymfonyUidUuidGenerator();
        $uuids = [];
        for ($i = 0; $i < 1000; $i++) {
            $uuids[] = $generator->generate();
        }

        $this->assertSame(1000, count(array_unique($uuids)), '1000 UUIDs must be unique');
    }

    public function testUuidsAreChronologicallySortable(): void
    {
        $generator = new SymfonyUidUuidGenerator();

        $first = $generator->generate();
        usleep(1000); // 1ms
        $second = $generator->generate();

        $this->assertLessThan(
            0,
            strcmp($first, $second),
            'UUIDv7 strings should be chronologically sortable (first < second)',
        );
    }

    public function testUuidContainsTimestampComponent(): void
    {
        $generator = new SymfonyUidUuidGenerator();
        $before = time();
        $uuid = $generator->generate();
        $after = time();

        // UUIDv7 encodes a 48-bit Unix-ms timestamp in the first 12 hex chars
        $timestampHex = substr($uuid, 0, 8) . substr($uuid, 9, 4);
        $timestampMs = hexdec($timestampHex);
        $timestampSec = intdiv($timestampMs, 1000);

        $this->assertGreaterThanOrEqual($before, $timestampSec, 'UUID timestamp must be >= before()');
        $this->assertLessThanOrEqual($after + 1, $timestampSec, 'UUID timestamp must be <= after() + 1');
    }
}
