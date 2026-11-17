<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Integration\Enrollment;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use SovereignStack\Lms\Enrollment\Domain\Entity\Enrollment;
use SovereignStack\Lms\Enrollment\Domain\ValueObject\EnrollmentId;
use SovereignStack\Lms\Enrollment\Infrastructure\Persistence\DBALEnrollmentRepository;
use SovereignStack\Lms\Shared\Domain\ValueObject\OrganisationIdReference;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdReference;
use SovereignStack\Lms\Tests\Integration\DatabaseIntegrationTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 *
 * @coversNothing
 */
final class EnrollmentRepositoryTest extends DatabaseIntegrationTestCase
{
    use EnrollmentFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->truncateEnrollmentTables();
    }

    public function testSaveFindAndLists(): void
    {
        $context = $this->seedEnrollmentContext('repo');
        $repository = $this->repository();

        $enrollment = $this->enrollment($context, new \DateTimeImmutable('2026-11-16T10:00:00+00:00'));
        $repository->save($enrollment);

        $found = $repository->findById($enrollment->id());
        self::assertNotNull($found);
        self::assertSame($enrollment->id()->value(), $found->id()->value());
        self::assertSame($context['learnerUserId'], $found->learnerUserId()->value());
        self::assertSame($context['courseId'], $found->courseId());
        self::assertSame($context['courseVersionId'], $found->courseVersionId());
        self::assertEquals($enrollment->enrolledAt(), $found->enrolledAt());

        $forLearner = $repository->listForLearner(UserIdReference::fromString($context['learnerUserId']));
        self::assertCount(1, $forLearner);
        self::assertSame($enrollment->id()->value(), $forLearner[0]->id()->value());

        $forCourse = $repository->listForCourse($context['courseId']);
        self::assertCount(1, $forCourse);
        self::assertSame($enrollment->id()->value(), $forCourse[0]->id()->value());
    }

    public function testFindForUpdateReturnsEnrollment(): void
    {
        $context = $this->seedEnrollmentContext('lock');
        $repository = $this->repository();
        $enrollment = $this->enrollment($context, new \DateTimeImmutable('2026-11-16T10:00:00+00:00'));
        $repository->save($enrollment);

        $found = $repository->findForUpdate($enrollment->id());

        self::assertNotNull($found);
        self::assertSame($enrollment->id()->value(), $found->id()->value());
    }

    public function testCompletePersistsStatusAndTimestamps(): void
    {
        $context = $this->seedEnrollmentContext('complete');
        $repository = $this->repository();
        $enrollment = $this->enrollment($context, new \DateTimeImmutable('2026-11-16T10:00:00+00:00'));
        $repository->save($enrollment);

        $completedAt = new \DateTimeImmutable('2026-11-17T10:00:00+00:00');
        $enrollment->complete($completedAt);
        $repository->save($enrollment);

        $found = $repository->findById($enrollment->id());
        self::assertNotNull($found);
        self::assertSame('completed', $found->status()->value);
        self::assertEquals($completedAt, $found->completedAt());
        self::assertNull($found->cancelledAt());
        self::assertEquals($completedAt, $found->updatedAt());
    }

    public function testCancelPersistsStatusAndTimestamps(): void
    {
        $context = $this->seedEnrollmentContext('cancel');
        $repository = $this->repository();
        $enrollment = $this->enrollment($context, new \DateTimeImmutable('2026-11-16T10:00:00+00:00'));
        $repository->save($enrollment);

        $cancelledAt = new \DateTimeImmutable('2026-11-17T10:00:00+00:00');
        $enrollment->cancel($cancelledAt);
        $repository->save($enrollment);

        $found = $repository->findById($enrollment->id());
        self::assertNotNull($found);
        self::assertSame('cancelled', $found->status()->value);
        self::assertNull($found->completedAt());
        self::assertEquals($cancelledAt, $found->cancelledAt());
        self::assertEquals($cancelledAt, $found->updatedAt());
    }

    public function testDuplicateActiveEnrollmentIsRejected(): void
    {
        $context = $this->seedEnrollmentContext('duplicate');
        $repository = $this->repository();
        $repository->save($this->enrollment($context, new \DateTimeImmutable('2026-11-16T10:00:00+00:00')));

        $this->expectException(UniqueConstraintViolationException::class);

        $repository->save($this->enrollment($context, new \DateTimeImmutable('2026-11-17T10:00:00+00:00')));
    }

    public function testFindActiveByLearnerAndCourseIgnoresNonActive(): void
    {
        $context = $this->seedEnrollmentContext('active');
        $repository = $this->repository();
        $enrollment = $this->enrollment($context, new \DateTimeImmutable('2026-11-16T10:00:00+00:00'));
        $repository->save($enrollment);

        $found = $repository->findActiveByLearnerAndCourse(
            UserIdReference::fromString($context['learnerUserId']),
            $context['courseId'],
        );
        self::assertNotNull($found);
        self::assertSame($enrollment->id()->value(), $found->id()->value());

        $enrollment->complete(new \DateTimeImmutable('2026-11-17T10:00:00+00:00'));
        $repository->save($enrollment);

        self::assertNull($repository->findActiveByLearnerAndCourse(
            UserIdReference::fromString($context['learnerUserId']),
            $context['courseId'],
        ));
    }

    public function testListForCourseReturnsAllLearners(): void
    {
        $context = $this->seedEnrollmentContext('list');
        $repository = $this->repository();
        $otherLearnerId = $this->insertUser('other-'.bin2hex(random_bytes(4)).'@example.com');

        $first = $this->enrollment($context, new \DateTimeImmutable('2026-11-16T09:00:00+00:00'));
        $second = Enrollment::create(
            EnrollmentId::fromString(Uuid::v7()->toRfc4122()),
            OrganisationIdReference::fromString($context['organisationId']),
            UserIdReference::fromString($otherLearnerId),
            $context['courseId'],
            $context['courseVersionId'],
            new \DateTimeImmutable('2026-11-16T10:00:00+00:00'),
        );

        $repository->save($first);
        $repository->save($second);

        $forCourse = $repository->listForCourse($context['courseId']);
        self::assertCount(2, $forCourse);
        self::assertSame($second->id()->value(), $forCourse[0]->id()->value());
        self::assertSame($first->id()->value(), $forCourse[1]->id()->value());
    }

    public function testMissingEnrollmentReturnsNull(): void
    {
        $repository = $this->repository();

        self::assertNull($repository->findById(EnrollmentId::fromString(Uuid::v7()->toRfc4122())));
    }

    private function repository(): DBALEnrollmentRepository
    {
        return new DBALEnrollmentRepository($this->connection());
    }

    /**
     * @param array{organisationId: string, learnerUserId: string, courseId: string, courseVersionId: string} $context
     */
    private function enrollment(array $context, \DateTimeImmutable $now): Enrollment
    {
        return Enrollment::create(
            EnrollmentId::fromString(Uuid::v7()->toRfc4122()),
            OrganisationIdReference::fromString($context['organisationId']),
            UserIdReference::fromString($context['learnerUserId']),
            $context['courseId'],
            $context['courseVersionId'],
            $now,
        );
    }
}
