<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Shared\Uuid;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Shared\Infrastructure\Uuid\SymfonyUidUuidGenerator;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

/**
 * @internal
 *
 * @coversNothing
 */
final class Uuidv7Test extends TestCase
{
    public function testSymfonyUuidV7(): void
    {
        $uuid = Uuid::v7();

        self::assertInstanceOf(UuidV7::class, $uuid);
        self::assertSame('7', $uuid->toRfc4122()[14]);
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid->toRfc4122());
        self::assertSame(36, \strlen($uuid->toRfc4122()));
    }

    public function testGeneratorReturnsValidUuidV7(): void
    {
        $id = (new SymfonyUidUuidGenerator())->generate();

        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $id);
        self::assertSame(36, \strlen($id));
        self::assertSame('7', Uuid::fromString($id)->toRfc4122()[14]);
    }

    public function testTimestampIsNonDecreasing(): void
    {
        $generator = new SymfonyUidUuidGenerator();
        $previous = 0;

        for ($i = 0; $i < 20; ++$i) {
            $milliseconds = (int) hexdec(str_replace('-', '', substr($generator->generate(), 0, 13)));

            self::assertGreaterThanOrEqual($previous, $milliseconds);
            $previous = $milliseconds;

            usleep(1000);
        }
    }

    public function testUniquenessAcrossManyGeneratedIds(): void
    {
        $generator = new SymfonyUidUuidGenerator();
        $ids = [];

        for ($i = 0; $i < 100; ++$i) {
            $ids[] = $generator->generate();
        }

        self::assertCount(100, $ids);
        self::assertCount(100, array_unique($ids));
    }
}
