<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Organisation\Domain;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Organisation\Domain\Entity\Organisation;
use SovereignStack\Lms\Organisation\Domain\Enum\OrganisationStatus;
use SovereignStack\Lms\Organisation\Domain\ValueObject\OrganisationId;

/**
 * @internal
 *
 * @coversNothing
 */
final class OrganisationTest extends TestCase
{
    private const ID = '01900000-0000-7000-8000-000000001000';

    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-11-14T10:00:00+00:00');
    }

    public function testCreateBuildsActiveOrganisation(): void
    {
        $organisation = $this->create();

        self::assertSame(self::ID, $organisation->id()->value());
        self::assertSame('Acme Learning', $organisation->name());
        self::assertSame('acme-learning', $organisation->slug());
        self::assertSame(OrganisationStatus::ACTIVE, $organisation->status());
        self::assertSame('UTC', $organisation->timezone());
        self::assertSame([], $organisation->settings());
        self::assertEquals($this->now, $organisation->createdAt());
        self::assertEquals($this->now, $organisation->updatedAt());
    }

    public function testRejectsInvalidSlug(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Organisation::create($this->organisationId(), 'Acme', 'Acme Learning', $this->now);
    }

    public function testRejectsSlugLongerThan255Characters(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Organisation::create($this->organisationId(), 'Acme', str_repeat('a', 256), $this->now);
    }

    public function testRenameUpdatesNameAndTimestamp(): void
    {
        $organisation = $this->create();
        $renamedAt = $this->now->modify('+1 hour');

        $organisation->rename('Acme Academy', $renamedAt);

        self::assertSame('Acme Academy', $organisation->name());
        self::assertEquals($renamedAt, $organisation->updatedAt());
        self::assertSame('acme-learning', $organisation->slug());
    }

    public function testSuspendChangesStatus(): void
    {
        $organisation = $this->create();
        $suspendedAt = $this->now->modify('+1 hour');

        $organisation->suspend($suspendedAt);

        self::assertSame(OrganisationStatus::SUSPENDED, $organisation->status());
        self::assertEquals($suspendedAt, $organisation->updatedAt());
    }

    private function create(): Organisation
    {
        return Organisation::create($this->organisationId(), 'Acme Learning', 'acme-learning', $this->now);
    }

    private function organisationId(): OrganisationId
    {
        return OrganisationId::fromString(self::ID);
    }
}
