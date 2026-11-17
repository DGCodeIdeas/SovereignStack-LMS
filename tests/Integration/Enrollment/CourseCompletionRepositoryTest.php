<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Integration\Enrollment;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use SovereignStack\Lms\Enrollment\Domain\Entity\CourseCompletion;
use SovereignStack\Lms\Enrollment\Domain\ValueObject\CourseCompletionId;
use SovereignStack\Lms\Enrollment\Infrastructure\Persistence\DBALCourseCompletionRepository;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdReference;
use SovereignStack\Lms\Tests\Integration\DatabaseIntegrationTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 *
 * @coversNothing
 */
final class CourseCompletionRepositoryTest extends DatabaseIntegrationTestCase
{
    use EnrollmentFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->truncateEnrollmentTables();
    }

    public function testSaveFindAndExists(): void
    {
        $context = $this->seedEnrollmentContext('completion');
        $enrollmentId = $this->insertEnrollmentRow(
            $context['organisationId'],
            $context['learnerUserId'],
            $context['courseId'],
            $context['courseVersionId'],
        );
        $repository = $this->repository();

        self::assertFalse($repository->existsForEnrollment($enrollmentId));

        $completion = $this->completion($context, $enrollmentId);
        $repository->save($completion);

        $found = $repository->findByEnrollment($enrollmentId);
        self::assertNotNull($found);
        self::assertSame($completion->id()->value(), $found->id()->value());
        self::assertSame($enrollmentId, $found->enrollmentId());
        self::assertSame($context['learnerUserId'], $found->learnerUserId()->value());
        self::assertSame($context['courseId'], $found->courseId());
        self::assertSame($context['courseVersionId'], $found->courseVersionId());
        self::assertEquals($completion->completedAt(), $found->completedAt());
        self::assertEquals($completion->createdAt(), $found->createdAt());
        self::assertTrue($repository->existsForEnrollment($enrollmentId));
    }

    public function testCompletionIsAppendOncePerEnrollment(): void
    {
        $context = $this->seedEnrollmentContext('append');
        $enrollmentId = $this->insertEnrollmentRow(
            $context['organisationId'],
            $context['learnerUserId'],
            $context['courseId'],
            $context['courseVersionId'],
        );
        $repository = $this->repository();
        $repository->save($this->completion($context, $enrollmentId));

        $this->expectException(UniqueConstraintViolationException::class);

        $repository->save($this->completion($context, $enrollmentId));
    }

    public function testMissingCompletionReturnsNull(): void
    {
        $context = $this->seedEnrollmentContext('missing');
        $enrollmentId = $this->insertEnrollmentRow(
            $context['organisationId'],
            $context['learnerUserId'],
            $context['courseId'],
            $context['courseVersionId'],
        );
        $repository = $this->repository();

        self::assertNull($repository->findByEnrollment($enrollmentId));
        self::assertFalse($repository->existsForEnrollment($enrollmentId));
    }

    private function repository(): DBALCourseCompletionRepository
    {
        return new DBALCourseCompletionRepository($this->connection());
    }

    /**
     * @param array{organisationId: string, learnerUserId: string, courseId: string, courseVersionId: string} $context
     */
    private function completion(array $context, string $enrollmentId): CourseCompletion
    {
        return CourseCompletion::record(
            CourseCompletionId::fromString(Uuid::v7()->toRfc4122()),
            $enrollmentId,
            UserIdReference::fromString($context['learnerUserId']),
            $context['courseId'],
            $context['courseVersionId'],
            new \DateTimeImmutable('2026-11-18T10:00:00+00:00'),
        );
    }
}
