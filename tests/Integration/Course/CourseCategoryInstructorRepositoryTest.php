<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Integration\Course;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use SovereignStack\Lms\Course\Domain\Entity\CourseCategory;
use SovereignStack\Lms\Course\Domain\Entity\CourseInstructor;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseCategoryId;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseId;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseInstructorId;
use SovereignStack\Lms\Course\Infrastructure\Persistence\DBALCourseCategoryRepository;
use SovereignStack\Lms\Course\Infrastructure\Persistence\DBALCourseInstructorRepository;
use SovereignStack\Lms\Shared\Domain\ValueObject\OrganisationIdReference;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdReference;
use SovereignStack\Lms\Tests\Integration\DatabaseIntegrationTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 *
 * @coversNothing
 */
final class CourseCategoryInstructorRepositoryTest extends DatabaseIntegrationTestCase
{
    use CourseFixtures;

    private const NOW = '2026-11-15T10:00:00+00:00';

    private string $organisationId;
    private string $userId;
    private string $courseId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->truncateCourseTables();
        $this->organisationId = $this->insertOrganisation('taxonomy-'.bin2hex(random_bytes(4)));
        $this->userId = $this->insertUser('taxonomy-'.bin2hex(random_bytes(4)).'@example.com');
        $this->courseId = $this->insertCourseRow($this->organisationId, $this->userId, 'taxonomy-'.bin2hex(random_bytes(4)));
    }

    public function testCategoryCreateFindAndList(): void
    {
        $repository = $this->categoryRepository();

        $programming = $this->category('Programming', 'programming');
        $design = $this->category('Design', 'design');
        $repository->save($programming);
        $repository->save($design);

        $found = $repository->findById($programming->id());
        self::assertNotNull($found);
        self::assertSame('Programming', $found->name());
        self::assertSame($this->organisationId, $found->organisationId()->value());

        $bySlug = $repository->findByOrganisationAndSlug($programming->organisationId(), 'design');
        self::assertNotNull($bySlug);
        self::assertSame($design->id()->value(), $bySlug->id()->value());

        $names = array_map(
            static fn (CourseCategory $category): string => $category->name(),
            $repository->listByOrganisation($programming->organisationId()),
        );
        self::assertSame(['Design', 'Programming'], $names);

        self::assertNull($repository->findByOrganisationAndSlug($programming->organisationId(), 'missing'));
    }

    public function testCategorySlugIsUniquePerOrganisation(): void
    {
        $repository = $this->categoryRepository();
        $repository->save($this->category('Programming', 'programming'));

        $this->expectException(UniqueConstraintViolationException::class);

        $repository->save($this->category('Programming Again', 'programming'));
    }

    public function testSameCategorySlugInAnotherOrganisationIsAllowed(): void
    {
        $repository = $this->categoryRepository();
        $repository->save($this->category('Programming', 'programming'));

        $otherOrganisation = $this->insertOrganisation('other-taxonomy-'.bin2hex(random_bytes(4)));
        $other = CourseCategory::create(
            CourseCategoryId::fromString(Uuid::v7()->toRfc4122()),
            OrganisationIdReference::fromString($otherOrganisation),
            'Programming',
            'programming',
            new \DateTimeImmutable(self::NOW),
        );

        $repository->save($other);

        self::assertNotNull($repository->findByOrganisationAndSlug($other->organisationId(), 'programming'));
    }

    public function testInstructorAssignFindAndList(): void
    {
        $repository = $this->instructorRepository();
        $secondUserId = $this->insertUser('instructor-'.bin2hex(random_bytes(4)).'@example.com');

        $first = $this->instructor(
            $secondUserId,
            new \DateTimeImmutable('2026-11-15T10:00:00+00:00'),
            new \DateTimeImmutable('2026-12-15T10:00:00+00:00'),
        );
        $second = $this->instructor(
            $this->userId,
            new \DateTimeImmutable('2026-11-16T10:00:00+00:00'),
            null,
        );
        $repository->save($first);
        $repository->save($second);

        $found = $repository->findByCourseAndUser(CourseId::fromString($this->courseId), UserIdReference::fromString($secondUserId));
        self::assertNotNull($found);
        self::assertSame($first->id()->value(), $found->id()->value());
        self::assertTrue($found->isEffectiveAt(new \DateTimeImmutable('2026-11-20T10:00:00+00:00')));
        self::assertFalse($found->isEffectiveAt(new \DateTimeImmutable('2026-12-20T10:00:00+00:00')));

        $userIds = array_map(
            static fn (CourseInstructor $instructor): string => $instructor->userId()->value(),
            $repository->listByCourse(CourseId::fromString($this->courseId)),
        );
        self::assertSame([$secondUserId, $this->userId], $userIds);

        self::assertNull($repository->findByCourseAndUser(
            CourseId::fromString($this->courseId),
            UserIdReference::fromString('01900000-0000-7000-8000-000000009901'),
        ));
    }

    public function testInstructorWindowUpdatePersists(): void
    {
        $repository = $this->instructorRepository();
        $instructor = $this->instructor($this->userId, new \DateTimeImmutable(self::NOW), null);
        $repository->save($instructor);

        $updated = CourseInstructor::restore(
            $instructor->id(),
            $instructor->courseId(),
            $instructor->userId(),
            $instructor->assignedBy(),
            $instructor->assignedAt(),
            $instructor->effectiveFrom(),
            new \DateTimeImmutable('2027-01-01T00:00:00+00:00'),
        );
        $repository->save($updated);

        $found = $repository->findByCourseAndUser(CourseId::fromString($this->courseId), UserIdReference::fromString($this->userId));
        self::assertNotNull($found);
        self::assertNotNull($found->effectiveUntil());
        self::assertEquals(new \DateTimeImmutable('2027-01-01T00:00:00+00:00'), $found->effectiveUntil());
    }

    private function categoryRepository(): DBALCourseCategoryRepository
    {
        return new DBALCourseCategoryRepository($this->connection());
    }

    private function instructorRepository(): DBALCourseInstructorRepository
    {
        return new DBALCourseInstructorRepository($this->connection());
    }

    private function category(string $name, string $slug): CourseCategory
    {
        return CourseCategory::create(
            CourseCategoryId::fromString(Uuid::v7()->toRfc4122()),
            OrganisationIdReference::fromString($this->organisationId),
            $name,
            $slug,
            new \DateTimeImmutable(self::NOW),
        );
    }

    private function instructor(string $userId, \DateTimeImmutable $effectiveFrom, ?\DateTimeImmutable $effectiveUntil): CourseInstructor
    {
        return CourseInstructor::assign(
            CourseInstructorId::fromString(Uuid::v7()->toRfc4122()),
            CourseId::fromString($this->courseId),
            UserIdReference::fromString($userId),
            UserIdReference::fromString($this->userId),
            new \DateTimeImmutable(self::NOW),
            $effectiveFrom,
            $effectiveUntil,
        );
    }
}
