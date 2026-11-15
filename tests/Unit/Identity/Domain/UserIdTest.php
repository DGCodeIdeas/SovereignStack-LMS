<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Identity\Domain;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;

/**
 * @internal
 *
 * @coversNothing
 */
final class UserIdTest extends TestCase
{
    private const VALID = '01900000-0000-7000-8000-000000000001';

    public function testAcceptsUuidV7(): void
    {
        $id = UserId::fromString(self::VALID);

        self::assertSame(self::VALID, $id->value());
        self::assertSame(self::VALID, (string) $id);
    }

    public function testNormalizesCaseAndWhitespace(): void
    {
        $id = UserId::fromString('  01900000-0000-7000-8000-0000000000AA  ');

        self::assertSame('01900000-0000-7000-8000-0000000000aa', $id->value());
    }

    public function testRejectsNonUuidV7(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
    }

    public function testRejectsMalformedValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        UserId::fromString('not-a-uuid');
    }

    public function testEqualsComparesValues(): void
    {
        $id = UserId::fromString(self::VALID);

        self::assertTrue($id->equals(UserId::fromString(self::VALID)));
        self::assertFalse($id->equals(UserId::fromString('01900000-0000-7000-8000-000000000002')));
    }
}
