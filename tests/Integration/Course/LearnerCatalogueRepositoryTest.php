<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Integration\Course;

use SovereignStack\Lms\Course\Infrastructure\Persistence\DBALCourseRepository;
use SovereignStack\Lms\Tests\Integration\DatabaseIntegrationTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class LearnerCatalogueRepositoryTest extends DatabaseIntegrationTestCase
{
    use CourseFixtures;

    private string $organisationA;
    private string $organisationB;
    private string $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->truncateCourseTables();
        $this->organisationA = $this->insertOrganisation('learner-catalogue-a-'.bin2hex(random_bytes(4)));
        $this->organisationB = $this->insertOrganisation('learner-catalogue-b-'.bin2hex(random_bytes(4)));
        $this->userId = $this->insertUser('learner-catalogue-'.bin2hex(random_bytes(4)).'@example.com');
    }

    public function testPublishedCoursesAreFilteredByVisibilityAndOrganisation(): void
    {
        $public = $this->publishedCourse('public-course', 'public', $this->organisationA);
        $organisationCourseA = $this->publishedCourse('organisation-a', 'organisation_only', $this->organisationA);
        $organisationCourseB = $this->publishedCourse('organisation-b', 'organisation_only', $this->organisationB);
        $restricted = $this->publishedCourse('restricted-course', 'restricted', $this->organisationA);
        $archived = $this->publishedCourse('archived-course', 'archived', $this->organisationA);
        $draft = $this->insertCourseRow($this->organisationA, $this->userId, 'draft-course');

        $repository = new DBALCourseRepository($this->connection());

        $courses = $repository->listPublishedForLearner([$this->organisationA]);
        $ids = array_column($courses, 'id');

        self::assertContains($public, $ids);
        self::assertContains($organisationCourseA, $ids);
        self::assertNotContains($organisationCourseB, $ids);
        self::assertNotContains($restricted, $ids);
        self::assertNotContains($archived, $ids);
        self::assertNotContains($draft, $ids);

        $bothOrganisations = array_column(
            $repository->listPublishedForLearner([$this->organisationA, $this->organisationB]),
            'id',
        );
        self::assertContains($organisationCourseB, $bothOrganisations);

        self::assertSame(
            [$public],
            array_column($repository->listPublishedForLearner([]), 'id'),
        );
    }

    public function testPublishedCourseRowsExposeCatalogueFieldsAndModuleCount(): void
    {
        $courseId = $this->publishedCourse('catalogue-fields', 'organisation_only', $this->organisationA);
        $versionId = $this->currentPublishedVersionId($courseId);
        $this->insertModuleRow($versionId, 'First module', 0);
        $this->insertModuleRow($versionId, 'Second module', 1);

        $repository = new DBALCourseRepository($this->connection());
        $courses = $repository->listPublishedForLearner([$this->organisationA]);

        self::assertCount(1, $courses);
        $course = $courses[0];

        self::assertSame($courseId, $course['id']);
        self::assertSame('catalogue-fields', $course['slug']);
        self::assertSame('Version 1', $course['title']);
        self::assertSame('Description 1', $course['description']);
        self::assertSame($this->organisationA, $course['organisationId']);
        self::assertSame('organisation_only', $course['visibility']);
        self::assertSame(2, $course['moduleCount']);
    }

    private function publishedCourse(string $slug, string $visibility, string $organisationId): string
    {
        $courseId = $this->insertCourseRow($organisationId, $this->userId, $slug, $visibility);
        $versionId = $this->insertVersionRow($courseId, $this->userId, 1, 'published', $this->timestamp());

        $this->connection()->update('courses', [
            'current_published_version_id' => $versionId,
            'current_published_version_course_id' => $courseId,
        ], ['id' => $courseId]);

        return $courseId;
    }

    private function currentPublishedVersionId(string $courseId): string
    {
        return $this->fetchString(
            'SELECT current_published_version_id FROM courses WHERE id = :id',
            ['id' => $courseId],
        );
    }
}
