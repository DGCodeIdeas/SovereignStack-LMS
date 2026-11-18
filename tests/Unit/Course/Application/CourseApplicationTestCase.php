<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Course\Application;

use PHPUnit\Framework\TestCase;
use SovereignStack\Lms\Course\Application\Security\CourseAccessPolicy;
use SovereignStack\Lms\Course\Application\Support\CourseVersionResolver;
use SovereignStack\Lms\Course\Domain\Entity\Course;
use SovereignStack\Lms\Course\Domain\Entity\CourseModule;
use SovereignStack\Lms\Course\Domain\Entity\CourseVersion;
use SovereignStack\Lms\Course\Domain\Entity\Lesson;
use SovereignStack\Lms\Course\Domain\Entity\LessonContent;
use SovereignStack\Lms\Course\Domain\Enum\CourseVisibility;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseId;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseModuleId;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseVersionId;
use SovereignStack\Lms\Course\Domain\ValueObject\LessonId;
use SovereignStack\Lms\Shared\Domain\ValueObject\OrganisationIdReference;
use SovereignStack\Lms\Shared\Domain\ValueObject\UserIdReference;
use SovereignStack\Lms\Tests\Support\Fakes\FakeTransactionManager;
use SovereignStack\Lms\Tests\Support\Fakes\FixedClock;
use SovereignStack\Lms\Tests\Support\Fakes\RecordingAuditLogger;
use SovereignStack\Lms\Tests\Support\Fakes\SequentialUuidGenerator;
use SovereignStack\Lms\Tests\Unit\Course\Application\Fakes\FakeCoursePermissionChecker;
use SovereignStack\Lms\Tests\Unit\Course\Application\Fakes\FakeHtmlSanitizer;
use SovereignStack\Lms\Tests\Unit\Course\Application\Fakes\InMemoryCourseInstructorRepository;
use SovereignStack\Lms\Tests\Unit\Course\Application\Fakes\InMemoryCourseModuleRepository;
use SovereignStack\Lms\Tests\Unit\Course\Application\Fakes\InMemoryCourseRepository;
use SovereignStack\Lms\Tests\Unit\Course\Application\Fakes\InMemoryCourseVersionRepository;
use SovereignStack\Lms\Tests\Unit\Course\Application\Fakes\InMemoryLessonContentRepository;
use SovereignStack\Lms\Tests\Unit\Course\Application\Fakes\InMemoryLessonRepository;

abstract class CourseApplicationTestCase extends TestCase
{
    protected const ORGANISATION_ID = '01900000-0000-7000-8000-000000000001';
    protected const OWNER_ID = '01900000-0000-7000-8000-000000000002';
    protected const INSTRUCTOR_ID = '01900000-0000-7000-8000-000000000003';
    protected const ACTOR_ID = '01900000-0000-7000-8000-000000000004';
    protected const COURSE_ID = '01900000-0000-7000-8000-000000000010';

    protected FixedClock $clock;
    protected SequentialUuidGenerator $uuid;
    protected RecordingAuditLogger $audit;
    protected FakeTransactionManager $transactions;
    protected FakeCoursePermissionChecker $permissions;
    protected InMemoryCourseRepository $courses;
    protected InMemoryCourseVersionRepository $versions;
    protected InMemoryCourseModuleRepository $modules;
    protected InMemoryLessonRepository $lessons;
    protected InMemoryLessonContentRepository $contents;
    protected InMemoryCourseInstructorRepository $instructors;
    protected FakeHtmlSanitizer $sanitizer;
    protected CourseAccessPolicy $policy;
    protected CourseVersionResolver $versionResolver;

    protected function setUp(): void
    {
        $this->clock = new FixedClock(new \DateTimeImmutable('2026-11-15T10:00:00+00:00'));
        $this->uuid = new SequentialUuidGenerator();
        $this->audit = new RecordingAuditLogger();
        $this->transactions = new FakeTransactionManager();
        $this->permissions = new FakeCoursePermissionChecker();
        $this->courses = new InMemoryCourseRepository();
        $this->versions = new InMemoryCourseVersionRepository();
        $this->modules = new InMemoryCourseModuleRepository();
        $this->lessons = new InMemoryLessonRepository();
        $this->contents = new InMemoryLessonContentRepository();
        $this->instructors = new InMemoryCourseInstructorRepository();
        $this->sanitizer = new FakeHtmlSanitizer();
        $this->policy = new CourseAccessPolicy($this->permissions, $this->instructors, $this->clock);
        $this->versionResolver = new CourseVersionResolver($this->versions);
    }

    /**
     * @return array{Course, CourseVersion}
     */
    protected function draftCourse(
        string $slug = 'intro',
        string $courseId = self::COURSE_ID,
        string $ownerId = self::OWNER_ID,
    ): array {
        $course = Course::create(
            CourseId::fromString($courseId),
            OrganisationIdReference::fromString(self::ORGANISATION_ID),
            UserIdReference::fromString($ownerId),
            $slug,
            CourseVisibility::ORGANISATION_ONLY,
            $this->clock->now(),
        );

        $version = CourseVersion::createDraft(
            CourseVersionId::fromString($this->uuid->generate()),
            $course->id(),
            1,
            'Introduction',
            'First version',
            UserIdReference::fromString($ownerId),
            $this->clock->now(),
        );

        $this->courses->save($course);
        $this->versions->save($version);

        return [$course, $version];
    }

    /**
     * @return array{Course, CourseVersion}
     */
    protected function publishedCourse(string $slug = 'intro'): array
    {
        [$course, $version] = $this->draftCourse($slug);

        $version->submitForReview();
        $version->publish($this->clock->now()->modify('+1 hour'));
        $course->publishVersion($version->id()->value(), $this->clock->now()->modify('+1 hour'));

        $this->versions->save($version);
        $this->courses->updatePublishedVersion($course);

        $this->courses->publishedVersionUpdates = 0;
        $this->courses->publishedVersionClears = 0;

        return [$course, $version];
    }

    protected function addModule(CourseVersion $version, string $title = 'Module', bool $isRequired = true): CourseModule
    {
        $module = CourseModule::create(
            CourseModuleId::fromString($this->uuid->generate()),
            $version->id(),
            $title,
            null,
            $this->modules->nextPosition($version->id()),
            $isRequired,
            $this->clock->now(),
        );

        $this->modules->save($module);

        return $module;
    }

    protected function addLesson(CourseModule $module, string $title = 'Lesson'): Lesson
    {
        $lesson = Lesson::create(
            LessonId::fromString($this->uuid->generate()),
            $module->id(),
            $title,
            $this->lessons->nextPosition($module->id()),
            $this->clock->now(),
        );

        $this->lessons->save($lesson);

        return $lesson;
    }

    protected function addContent(Lesson $lesson, string $html = '<p>Body</p>'): LessonContent
    {
        $content = LessonContent::create(
            $this->uuid->generate(),
            $lesson->id(),
            $html,
            ['format' => 'html'],
            $this->clock->now(),
        );

        $this->contents->save($content);

        return $content;
    }
}
