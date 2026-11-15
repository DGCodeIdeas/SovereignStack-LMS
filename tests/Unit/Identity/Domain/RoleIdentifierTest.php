<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Identity\Domain;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Identity\Domain\ValueObject\RoleIdentifier;

/**
 * @internal
 *
 * @coversNothing
 */
final class RoleIdentifierTest extends TestCase
{
    public function testConstantsAreValid(): void
    {
        foreach ([
            RoleIdentifier::PLATFORM_ADMIN,
            RoleIdentifier::LMS_ADMIN,
            RoleIdentifier::LMS_INSTRUCTOR,
            RoleIdentifier::LMS_LEARNER,
        ] as $code) {
            self::assertSame($code, RoleIdentifier::fromString($code)->value());
        }
    }

    public function testRejectsInvalidFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        RoleIdentifier::fromString('LMS Admin');
    }

    public function testRejectsTooLongValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        RoleIdentifier::fromString(str_repeat('a', 65));
    }

    public function testEqualsComparesValues(): void
    {
        $role = RoleIdentifier::fromString(RoleIdentifier::LMS_LEARNER);

        self::assertTrue($role->equals(RoleIdentifier::fromString(RoleIdentifier::LMS_LEARNER)));
        self::assertFalse($role->equals(RoleIdentifier::fromString(RoleIdentifier::LMS_ADMIN)));
    }
}
