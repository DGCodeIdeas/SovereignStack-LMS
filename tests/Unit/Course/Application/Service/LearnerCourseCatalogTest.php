<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Course\Application\Service;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Course\Application\Contract\PublishedCourseVersion;
use SovereignStack\Lms\Course\Application\Contract\PublishedLesson;
use SovereignStack\Lms\Course\Application\Contract\PublishedModule;
use SovereignStack\Lms\Course\Application\Service\LearnerCourseCatalog;
use SovereignStack\Lms\Course\Domain\Entity\Course;
use SovereignStack\Lms\Course\Domain\Enum\CourseVisibility;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseId;
use SovereignStack\Lms\Shared\Domain\ValueObject\OrganisationIdReference;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdReference;
use SovereignStack\Lms\Tests\Unit\Course\Application\Fakes\FakePublishedContentProvider;
use SovereignStack\Lms\Tests\Unit\Course\Application\Fakes\InMemoryCourseRepository;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 *
 * @coversNothing
 */
final class LearnerCourseCatalogTest extends TestCase
{
    private const ORGANISATION_A = '01900000-0000-7000-8000-000000000101';
    private const ORGANISATION_B = '01900000-0000-7000-8000-000000000102';
    private const USER_ID = '01900000-0000-7000-8000-000000000103';

    private InMemoryCourseRepository $courses;
    private FakePublishedContentProvider $content;
    private LearnerCourseCatalog $catalog;

    protected function setUp(): void
    {
        $this->courses = new InMemoryCourseRepository();
        $this->content = new FakePublishedContentProvider();
        $this->catalog = new LearnerCourseCatalog($this->courses, $this->content);
    }

    public function testListIncludesPublicAndMatchingOrganisationOnlyCourses(): void
    {
        $this->courses->learnerCourses = [
            $this->catalogueRow('public-course', 'Public course', 'public', self::ORGANISATION_B, 2),
            $this->catalogueRow('org-course-a', 'Organisation A course', 'organisation_only', self::ORGANISATION_A, 1),
            $this->catalogueRow('org-course-b', 'Organisation B course', 'organisation_only', self::ORGANISATION_B, 3),
            $this->catalogueRow('restricted-course', 'Restricted course', 'restricted', self::ORGANISATION_A, 1),
            $this->catalogueRow('archived-course', 'Archived course', 'archived', self::ORGANISATION_A, 1),
        ];

        $courses = $this->catalog->list('user', [self::ORGANISATION_A]);

        self::assertSame(
            ['public-course', 'org-course-a'],
            array_map(static fn (array $course): string => $course['slug'], $courses),
        );
        self::assertSame([
            'id' => '01900000-0000-7000-8000-000000000201',
            'slug' => 'public-course',
            'title' => 'Public course',
            'description' => 'Public course description',
            'organisationId' => self::ORGANISATION_B,
            'visibility' => 'public',
            'moduleCount' => 2,
        ], $courses[0]);
    }

    public function testListWithoutOrganisationsReturnsOnlyPublicCourses(): void
    {
        $this->courses->learnerCourses = [
            $this->catalogueRow('public-course', 'Public course', 'public', self::ORGANISATION_A, 1),
            $this->catalogueRow('org-course', 'Organisation course', 'organisation_only', self::ORGANISATION_A, 1),
        ];

        $courses = $this->catalog->list('user', []);

        self::assertSame(
            ['public-course'],
            array_map(static fn (array $course): string => $course['slug'], $courses),
        );
    }

    public function testViewReturnsPublishedModulesForVisibleCourse(): void
    {
        [$course, $versionId] = $this->course('public-detail', CourseVisibility::PUBLIC);
        $this->publishVersion($course, $versionId, 'public', 'A public course.');

        $detail = $this->catalog->view($course->id()->value(), [self::ORGANISATION_A]);

        self::assertNotNull($detail);
        self::assertSame($course->id()->value(), $detail['courseId']);
        self::assertSame('Published title', $detail['title']);
        self::assertSame('A public course.', $detail['description']);
        self::assertCount(1, $detail['modules']);
        self::assertSame('Module one', $detail['modules'][0]['title']);
        self::assertTrue($detail['modules'][0]['isRequired']);
        self::assertSame([['id' => 'lesson-1', 'title' => 'Lesson one']], $detail['modules'][0]['lessons']);
    }

    public function testViewRejectsOrganisationOnlyCourseOutsideTheLearnerOrganisations(): void
    {
        [$course, $versionId] = $this->course('org-detail', CourseVisibility::ORGANISATION_ONLY);
        $this->publishVersion($course, $versionId, 'organisation_only', 'Members only.');

        self::assertNull($this->catalog->view($course->id()->value(), [self::ORGANISATION_B]));
        self::assertNotNull($this->catalog->view($course->id()->value(), [self::ORGANISATION_A]));
    }

    public function testViewRejectsRestrictedAndArchivedCourses(): void
    {
        [$restricted, $restrictedVersion] = $this->course('restricted-detail', CourseVisibility::RESTRICTED);
        $this->publishVersion($restricted, $restrictedVersion, 'restricted', 'Staff only.');

        [$archived, $archivedVersion] = $this->course('archived-detail', CourseVisibility::ARCHIVED);
        $this->publishVersion($archived, $archivedVersion, 'archived', 'Archived.');

        self::assertNull($this->catalog->view($restricted->id()->value(), [self::ORGANISATION_A]));
        self::assertNull($this->catalog->view($archived->id()->value(), [self::ORGANISATION_A]));
    }

    public function testViewRejectsUnpublishedAndUnknownCourses(): void
    {
        [$draft] = $this->course('draft-detail', CourseVisibility::ORGANISATION_ONLY, false);

        self::assertNull($this->catalog->view($draft->id()->value(), [self::ORGANISATION_A]));
        self::assertNull($this->catalog->view('not-a-course-id', [self::ORGANISATION_A]));
        self::assertNull($this->catalog->view('01900000-0000-7000-8000-000000000999', [self::ORGANISATION_A]));
    }

    /**
     * @return array{
     *     id: string,
     *     slug: string,
     *     title: string,
     *     description: null|string,
     *     organisationId: string,
     *     visibility: string,
     *     moduleCount: int
     * }
     */
    private function catalogueRow(string $slug, string $title, string $visibility, string $organisationId, int $moduleCount): array
    {
        static $sequence = 0;
        ++$sequence;

        return [
            'id' => sprintf('01900000-0000-7000-8000-%012d', 200 + $sequence),
            'slug' => $slug,
            'title' => $title,
            'description' => $title.' description',
            'organisationId' => $organisationId,
            'visibility' => $visibility,
            'moduleCount' => $moduleCount,
        ];
    }

    /**
     * @return array{0: Course, 1: string}
     */
    private function course(string $slug, CourseVisibility $visibility, bool $published = true): array
    {
        $courseId = Uuid::v7()->toRfc4122();
        $versionId = Uuid::v7()->toRfc4122();
        $now = new \DateTimeImmutable('2026-11-17T10:00:00+00:00');

        $course = Course::create(
            CourseId::fromString($courseId),
            OrganisationIdReference::fromString(self::ORGANISATION_A),
            UserIdReference::fromString(self::USER_ID),
            $slug,
            $visibility,
            $now,
        );

        if ($published) {
            $course->publishVersion($versionId, $now);
        }

        $this->courses->save($course);

        return [$course, $versionId];
    }

    private function publishVersion(Course $course, string $versionId, string $visibility, string $description): void
    {
        $this->content->publish(new PublishedCourseVersion(
            $course->id()->value(),
            $versionId,
            $course->organisationId()->value(),
            'Published title',
            [
                new PublishedModule('module-1', 'Module one', true, [
                    new PublishedLesson('lesson-1', 'Lesson one'),
                ]),
            ],
            $visibility,
            $description,
        ));
    }
}
