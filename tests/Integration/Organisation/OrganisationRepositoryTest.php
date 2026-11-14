<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Integration\Organisation;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use SovereignStack\Lms\Identity\Domain\ValueObject\UserId;
use SovereignStack\Lms\Organisation\Domain\Entity\Organisation;
use SovereignStack\Lms\Organisation\Domain\Enum\OrganisationStatus;
use SovereignStack\Lms\Organisation\Domain\ValueObject\OrganisationId;
use SovereignStack\Lms\Organisation\Infrastructure\Persistence\DBALOrganisationRepository;
use SovereignStack\Lms\Tests\Integration\DatabaseIntegrationTestCase;
use SovereignStack\Lms\Tests\Integration\IdentityOrganisationFixtures;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 *
 * @coversNothing
 */
final class OrganisationRepositoryTest extends DatabaseIntegrationTestCase
{
    use IdentityOrganisationFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->truncate('user_tokens', 'organisation_memberships', 'user_roles', 'user_profiles', 'users', 'organisations');
    }

    public function testCreateAndFindBySlug(): void
    {
        $repository = $this->repository();
        $organisation = $this->organisation('acme-'.bin2hex(random_bytes(4)));

        $repository->save($organisation);

        $byId = $repository->findById($organisation->id());
        self::assertNotNull($byId);
        self::assertSame($organisation->id()->value(), $byId->id()->value());
        self::assertSame($organisation->slug(), $byId->slug());
        self::assertSame(OrganisationStatus::ACTIVE, $byId->status());

        $bySlug = $repository->findBySlug($organisation->slug());
        self::assertNotNull($bySlug);
        self::assertSame($organisation->id()->value(), $bySlug->id()->value());
        self::assertNull($repository->findBySlug('missing-'.bin2hex(random_bytes(4))));
    }

    public function testRenameAndSuspendPersist(): void
    {
        $repository = $this->repository();
        $organisation = $this->organisation('rename-'.bin2hex(random_bytes(4)));
        $repository->save($organisation);

        $organisation->rename('Renamed Organisation', $organisation->updatedAt()->modify('+1 hour'));
        $organisation->suspend($organisation->updatedAt()->modify('+2 hours'));
        $repository->save($organisation);

        $loaded = $repository->findById($organisation->id());
        self::assertNotNull($loaded);
        self::assertSame('Renamed Organisation', $loaded->name());
        self::assertSame(OrganisationStatus::SUSPENDED, $loaded->status());
    }

    public function testDuplicateSlugIsRejected(): void
    {
        $repository = $this->repository();
        $slug = 'duplicate-'.bin2hex(random_bytes(4));

        $repository->save($this->organisation($slug));

        $this->expectException(UniqueConstraintViolationException::class);

        $repository->save($this->organisation($slug));
    }

    public function testListForUserReturnsMemberships(): void
    {
        $repository = $this->repository();
        $userId = $this->insertUser('list-'.bin2hex(random_bytes(4)).'@example.com');

        $first = $this->organisation('first-'.bin2hex(random_bytes(4)));
        $second = $this->organisation('second-'.bin2hex(random_bytes(4)));
        $repository->save($first);
        $repository->save($second);

        $otherUser = $this->insertUser('other-'.bin2hex(random_bytes(4)).'@example.com');

        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
        $this->insertMembership($first->id()->value(), $userId, $now);
        $this->insertMembership($second->id()->value(), $userId, $now);
        $this->insertMembership($second->id()->value(), $otherUser, $now);

        $organisations = $repository->listForUser(
            UserId::fromString($userId)
        );

        $slugs = array_map(static fn (Organisation $organisation): string => $organisation->slug(), $organisations);
        sort($slugs);

        self::assertSame([$first->slug(), $second->slug()], $slugs);
    }

    private function repository(): DBALOrganisationRepository
    {
        return new DBALOrganisationRepository($this->connection());
    }

    private function organisation(string $slug): Organisation
    {
        return Organisation::create(
            OrganisationId::fromString(Uuid::v7()->toRfc4122()),
            'Test Organisation',
            $slug,
            new \DateTimeImmutable('2026-11-14T10:00:00+00:00'),
        );
    }

    private function insertMembership(string $organisationId, string $userId, string $now): void
    {
        $this->connection()->insert('organisation_memberships', [
            'id' => Uuid::v7()->toRfc4122(),
            'organisation_id' => $organisationId,
            'user_id' => $userId,
            'status' => 'active',
            'joined_at' => $now,
            'created_at' => $now,
        ]);
    }
}
