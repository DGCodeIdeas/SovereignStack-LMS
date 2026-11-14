<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Organisation\Domain;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Organisation\Domain\ValueObject\OrganisationId;

/**
 * @internal
 *
 * @coversNothing
 */
final class OrganisationIdTest extends TestCase
{
    private const VALID = '01900000-0000-7000-8000-000000001000';

    public function testAcceptsUuidV7(): void
    {
        $id = OrganisationId::fromString(self::VALID);

        self::assertSame(self::VALID, $id->value());
        self::assertSame(self::VALID, (string) $id);
    }

    public function testNormalizesCaseAndWhitespace(): void
    {
        $id = OrganisationId::fromString(' 01900000-0000-7000-8000-0000000010AA ');

        self::assertSame('01900000-0000-7000-8000-0000000010aa', $id->value());
    }

    public function testRejectsNonUuidV7(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        OrganisationId::fromString('550e8400-e29b-41d4-a716-446655440000');
    }

    public function testRejectsMalformedValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        OrganisationId::fromString('org-1');
    }

    public function testEqualsComparesValues(): void
    {
        $id = OrganisationId::fromString(self::VALID);

        self::assertTrue($id->equals(OrganisationId::fromString(self::VALID)));
        self::assertFalse($id->equals(OrganisationId::fromString('01900000-0000-7000-8000-000000001001')));
    }
}
