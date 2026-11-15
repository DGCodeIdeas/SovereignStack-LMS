<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Integration\Course;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use SovereignStack\Lms\Course\Domain\Entity\Course;
use SovereignStack\Lms\Course\Domain\Enum\CourseVisibility;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseId;
use SovereignStack\Lms\Course\Infrastructure\Persistence\DBALCourseRepository;
use SovereignStack\Lms\Shared\Domain\ValueObject\OrganisationIdReference;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdReference;
use SovereignStack\Lms\Tests\Integration\DatabaseIntegrationTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 *
 * @coversNothing
 */
final class CourseRepositoryTest extends DatabaseIntegrationTestCase
{
    use CourseFixtures;

    private string $organisationId;
    private string $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->truncateCourseTables();
        $this->organisationId = $this->insertOrganisation('course-'.bin2hex(random_bytes(4)));
        $this->userId = $this->insertUser('course-'.bin2hex(random_bytes(4)).'@example.com');
    }

    public function testCreateFindAndListByOrganisation(): void
    {
        $repository = $this->repository();
        $first = $this->course('first-course');
        $second = $this->course('second-course');

        $repository->save($first);
        $repository->save($second);

        $found = $repository->findById($first->id());
        self::assertNotNull($found);
        self::assertSame('first-course', $found->slug());
        self::assertSame(CourseVisibility::ORGANISATION_ONLY, $found->visibility());
        self::assertNull($found->currentPublishedVersionId());

        $bySlug = $repository->findByOrganisationAndSlug($first->organisationId(), 'first-course');
        self::assertNotNull($bySlug);
        self::assertSame($first->id()->value(), $bySlug->id()->value());

        $slugs = array_map(
            static fn (Course $course): string => $course->slug(),
            $repository->listByOrganisation($first->organisationId()),
        );
        self::assertSame(['first-course', 'second-course'], $slugs);

        $missing = $repository->findByOrganisationAndSlug($first->organisationId(), 'missing-course');
        self::assertNull($missing);
    }

    public function testDuplicateSlugInSameOrganisationIsRejected(): void
    {
        $repository = $this->repository();
        $repository->save($this->course('duplicate-slug'));

        $this->expectException(UniqueConstraintViolationException::class);

        $repository->save($this->course('duplicate-slug'));
    }

    public function testSameSlugInAnotherOrganisationIsAllowed(): void
    {
        $repository = $this->repository();
        $repository->save($this->course('shared-slug'));

        $otherOrganisation = $this->insertOrganisation('other-'.bin2hex(random_bytes(4)));
        $other = Course::create(
            CourseId::fromString(Uuid::v7()->toRfc4122()),
            OrganisationIdReference::fromString($otherOrganisation),
            UserIdReference::fromString($this->userId),
            'shared-slug',
            CourseVisibility::PUBLIC,
            new \DateTimeImmutable('2026-11-15T10:00:00+00:00'),
        );

        $repository->save($other);

        $found = $repository->findByOrganisationAndSlug($other->organisationId(), 'shared-slug');
        self::assertNotNull($found);
        self::assertSame($otherOrganisation, $found->organisationId()->value());
    }

    public function testUpdatePersistsSlugAndVisibilityWithoutTouchingPublishedColumns(): void
    {
        $repository = $this->repository();
        $course = $this->course('update-course');
        $repository->save($course);

        $versionId = $this->insertVersionRow($course->id()->value(), $this->userId, 1, 'published', $this->timestamp());
        $course->publishVersion($versionId, $course->updatedAt()->modify('+1 hour'));
        $repository->updatePublishedVersion($course);

        $course->changeSlug('updated-course', $course->updatedAt()->modify('+2 hours'));
        $course->changeVisibility(CourseVisibility::RESTRICTED, $course->updatedAt());
        $repository->save($course);

        $found = $repository->findById($course->id());
        self::assertNotNull($found);
        self::assertSame('updated-course', $found->slug());
        self::assertSame(CourseVisibility::RESTRICTED, $found->visibility());
        self::assertSame($versionId, $found->currentPublishedVersionId());
        self::assertSame($course->id()->value(), $found->currentPublishedVersionCourseId());
    }

    public function testPublishAndClearPublishedVersion(): void
    {
        $repository = $this->repository();
        $course = $this->course('publish-course');
        $repository->save($course);

        $versionId = $this->insertVersionRow($course->id()->value(), $this->userId);
        $course->publishVersion($versionId, $course->updatedAt()->modify('+1 hour'));
        $repository->updatePublishedVersion($course);

        $row = $this->connection()->fetchAssociative(
            'SELECT current_published_version_id, current_published_version_course_id FROM courses WHERE id = :id',
            ['id' => $course->id()->value()],
        );
        self::assertIsArray($row);
        self::assertSame($versionId, $row['current_published_version_id']);
        self::assertSame($course->id()->value(), $row['current_published_version_course_id']);

        $course->clearPublishedVersion($course->updatedAt()->modify('+2 hours'));
        $repository->clearPublishedVersion($course);

        $row = $this->connection()->fetchAssociative(
            'SELECT current_published_version_id, current_published_version_course_id FROM courses WHERE id = :id',
            ['id' => $course->id()->value()],
        );
        self::assertIsArray($row);
        self::assertNull($row['current_published_version_id']);
        self::assertNull($row['current_published_version_course_id']);
    }

    public function testPublishingVersionOfAnotherCourseIsRejected(): void
    {
        $repository = $this->repository();
        $first = $this->course('first-course');
        $second = $this->course('second-course');
        $repository->save($first);
        $repository->save($second);

        $foreignVersionId = $this->insertVersionRow($first->id()->value(), $this->userId);

        $second->publishVersion($foreignVersionId, $second->updatedAt()->modify('+1 hour'));

        try {
            $repository->updatePublishedVersion($second);
            self::fail('Publishing another course\'s version must be rejected by the composite foreign key.');
        } catch (Exception $exception) {
            self::assertStringContainsString('foreign key', strtolower($exception->getMessage()));
        }

        $row = $this->connection()->fetchAssociative(
            'SELECT current_published_version_id FROM courses WHERE id = :id',
            ['id' => $second->id()->value()],
        );
        self::assertIsArray($row);
        self::assertNull($row['current_published_version_id']);
    }

    public function testUpdatePublishedVersionRequiresPublishedIds(): void
    {
        $repository = $this->repository();
        $course = $this->course('unpublished-course');
        $repository->save($course);

        $this->expectException(\LogicException::class);

        $repository->updatePublishedVersion($course);
    }

    private function repository(): DBALCourseRepository
    {
        return new DBALCourseRepository($this->connection());
    }

    private function course(string $slug): Course
    {
        return Course::create(
            CourseId::fromString(Uuid::v7()->toRfc4122()),
            OrganisationIdReference::fromString($this->organisationId),
            UserIdReference::fromString($this->userId),
            $slug,
            CourseVisibility::ORGANISATION_ONLY,
            new \DateTimeImmutable('2026-11-15T10:00:00+00:00'),
        );
    }
}
