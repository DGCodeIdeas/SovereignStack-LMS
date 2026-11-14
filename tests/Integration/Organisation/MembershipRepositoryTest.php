<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Integration\Organisation;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;
use SovereignStack\Lms\Organisation\Domain\Entity\Membership;
use SovereignStack\Lms\Organisation\Domain\Enum\MembershipStatus;
use SovereignStack\Lms\Organisation\Domain\ValueObject\OrganisationId;
use SovereignStack\Lms\Organisation\Infrastructure\Persistence\DBALMembershipRepository;
use SovereignStack\Lms\Tests\Integration\DatabaseIntegrationTestCase;
use SovereignStack\Lms\Tests\Integration\IdentityOrganisationFixtures;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 *
 * @coversNothing
 */
final class MembershipRepositoryTest extends DatabaseIntegrationTestCase
{
    use IdentityOrganisationFixtures;

    private OrganisationId $organisationA;

    private OrganisationId $organisationB;

    private UserId $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->truncate('user_tokens', 'organisation_memberships', 'user_roles', 'user_profiles', 'users', 'organisations');

        $this->organisationA = OrganisationId::fromString($this->insertOrganisation('org-a-'.bin2hex(random_bytes(4))));
        $this->organisationB = OrganisationId::fromString($this->insertOrganisation('org-b-'.bin2hex(random_bytes(4))));
        $this->userId = UserId::fromString($this->insertUser('member-'.bin2hex(random_bytes(4)).'@example.com'));
    }

    public function testJoinFindAndList(): void
    {
        $repository = $this->repository();
        $membership = $this->membership($this->organisationA);

        $repository->save($membership);

        $found = $repository->findByOrganisationAndUser($this->organisationA, $this->userId);
        self::assertNotNull($found);
        self::assertSame($membership->id(), $found->id());
        self::assertSame(MembershipStatus::ACTIVE, $found->status());
        self::assertNotNull($found->joinedAt());

        self::assertCount(1, $repository->listForUser($this->userId));
        self::assertCount(1, $repository->listForOrganisation($this->organisationA));
        self::assertCount(0, $repository->listForOrganisation($this->organisationB));
    }

    public function testEndedMembershipPersists(): void
    {
        $repository = $this->repository();
        $membership = $this->membership($this->organisationA);
        $repository->save($membership);

        $membership->end(new \DateTimeImmutable('2026-12-01T00:00:00+00:00'));
        $repository->save($membership);

        $loaded = $repository->findByOrganisationAndUser($this->organisationA, $this->userId);
        self::assertNotNull($loaded);
        self::assertSame(MembershipStatus::ENDED, $loaded->status());
        self::assertNotNull($loaded->endedAt());
    }

    public function testDuplicateOrganisationAndUserIsRejected(): void
    {
        $repository = $this->repository();
        $repository->save($this->membership($this->organisationA));

        $this->expectException(UniqueConstraintViolationException::class);

        $repository->save($this->membership($this->organisationA));
    }

    public function testOrganisationIsolation(): void
    {
        $repository = $this->repository();
        $repository->save($this->membership($this->organisationA));

        $otherUser = UserId::fromString($this->insertUser('isolated-'.bin2hex(random_bytes(4)).'@example.com'));
        $repository->save($this->membership($this->organisationB, $otherUser));

        $organisationA = $repository->listForOrganisation($this->organisationA);
        $organisationB = $repository->listForOrganisation($this->organisationB);

        self::assertCount(1, $organisationA);
        self::assertCount(1, $organisationB);
        self::assertSame($this->userId->value(), $organisationA[0]->userId());
        self::assertSame($otherUser->value(), $organisationB[0]->userId());
        self::assertNotSame($organisationA[0]->id(), $organisationB[0]->id());
    }

    private function repository(): DBALMembershipRepository
    {
        return new DBALMembershipRepository($this->connection());
    }

    private function membership(OrganisationId $organisationId, ?UserId $userId = null): Membership
    {
        return Membership::join(
            Uuid::v7()->toRfc4122(),
            $organisationId,
            $userId ?? $this->userId,
            new \DateTimeImmutable('2026-11-14T10:00:00+00:00'),
        );
    }
}
