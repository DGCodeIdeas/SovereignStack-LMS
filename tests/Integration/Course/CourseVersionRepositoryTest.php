<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Integration\Course;

use SovereignStack\Lms\Course\Domain\Entity\CourseVersion;
use SovereignStack\Lms\Course\Domain\Enum\CourseVersionStatus;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseId;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseVersionId;
use SovereignStack\Lms\Course\Infrastructure\Persistence\DBALCourseVersionRepository;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdReference;
use SovereignStack\Lms\Tests\Integration\DatabaseIntegrationTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 *
 * @coversNothing
 */
final class CourseVersionRepositoryTest extends DatabaseIntegrationTestCase
{
    use CourseFixtures;

    private string $courseId;
    private string $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->truncateCourseTables();
        $organisationId = $this->insertOrganisation('version-'.bin2hex(random_bytes(4)));
        $this->userId = $this->insertUser('version-'.bin2hex(random_bytes(4)).'@example.com');
        $this->courseId = $this->insertCourseRow($organisationId, $this->userId, 'version-'.bin2hex(random_bytes(4)));
    }

    public function testSaveDraftAndFindById(): void
    {
        $repository = $this->repository();
        $version = $this->version(1);

        $repository->save($version);

        $found = $repository->findById($version->id());
        self::assertNotNull($found);
        self::assertSame(1, $found->versionNumber());
        self::assertSame('Version 1', $found->title());
        self::assertSame('Description 1', $found->description());
        self::assertSame(CourseVersionStatus::DRAFT, $found->status());
        self::assertNull($found->publishedAt());
        self::assertSame($this->userId, $found->createdBy()->value());
        self::assertEquals($version->createdAt(), $found->createdAt());
    }

    public function testNextVersionNumberStartsAtOneAndIncrements(): void
    {
        $repository = $this->repository();
        $courseId = CourseId::fromString($this->courseId);

        self::assertSame(1, $repository->nextVersionNumber($courseId));

        $repository->save($this->version(1));
        self::assertSame(2, $repository->nextVersionNumber($courseId));

        $repository->save($this->version(2));
        self::assertSame(3, $repository->nextVersionNumber($courseId));
    }

    public function testFindDraftByCourseReturnsHighestDraft(): void
    {
        $repository = $this->repository();
        $courseId = CourseId::fromString($this->courseId);

        $first = $this->version(1);
        $second = $this->version(2);
        $repository->save($first);
        $repository->save($second);

        $draft = $repository->findDraftByCourse($courseId);
        self::assertNotNull($draft);
        self::assertSame($second->id()->value(), $draft->id()->value());

        $second->submitForReview();
        $repository->save($second);

        $draft = $repository->findDraftByCourse($courseId);
        self::assertNotNull($draft);
        self::assertSame($first->id()->value(), $draft->id()->value());
    }

    public function testFindDraftByCourseReturnsNullWhenNoDraftExists(): void
    {
        $repository = $this->repository();
        $version = $this->version(1);
        $version->submitForReview();
        $repository->save($version);

        self::assertNull($repository->findDraftByCourse(CourseId::fromString($this->courseId)));
    }

    public function testListByCourseIsOrderedByVersionNumber(): void
    {
        $repository = $this->repository();
        $third = $this->version(3);
        $first = $this->version(1);
        $repository->save($third);
        $repository->save($first);

        $numbers = array_map(
            static fn (CourseVersion $version): int => $version->versionNumber(),
            $repository->listByCourse(CourseId::fromString($this->courseId)),
        );

        self::assertSame([1, 3], $numbers);
    }

    public function testPublishAndArchiveTransitionsPersist(): void
    {
        $repository = $this->repository();
        $version = $this->version(1);
        $repository->save($version);

        $version->submitForReview();
        $repository->save($version);

        $publishedAt = new \DateTimeImmutable('2026-11-16T12:00:00+00:00');
        $version->publish($publishedAt);
        $repository->save($version);

        $found = $repository->findById($version->id());
        self::assertNotNull($found);
        self::assertSame(CourseVersionStatus::PUBLISHED, $found->status());
        self::assertNotNull($found->publishedAt());
        self::assertEquals($publishedAt, $found->publishedAt());

        $version->archive();
        $repository->save($version);

        $archived = $repository->findById($version->id());
        self::assertNotNull($archived);
        self::assertSame(CourseVersionStatus::ARCHIVED, $archived->status());
    }

    private function repository(): DBALCourseVersionRepository
    {
        return new DBALCourseVersionRepository($this->connection());
    }

    private function version(int $versionNumber): CourseVersion
    {
        return CourseVersion::createDraft(
            CourseVersionId::fromString(Uuid::v7()->toRfc4122()),
            CourseId::fromString($this->courseId),
            $versionNumber,
            'Version '.$versionNumber,
            'Description '.$versionNumber,
            UserIdReference::fromString($this->userId),
            new \DateTimeImmutable('2026-11-15T10:00:00+00:00'),
        );
    }
}
