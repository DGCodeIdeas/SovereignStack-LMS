<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Integration\Course;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use SovereignStack\Lms\Course\Domain\Entity\CourseModule;
use SovereignStack\Lms\Course\Domain\Entity\Lesson;
use SovereignStack\Lms\Course\Domain\Entity\LessonContent;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseModuleId;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseVersionId;
use SovereignStack\Lms\Course\Domain\ValueObject\LessonId;
use SovereignStack\Lms\Course\Infrastructure\Persistence\DBALCourseModuleRepository;
use SovereignStack\Lms\Course\Infrastructure\Persistence\DBALLessonContentRepository;
use SovereignStack\Lms\Course\Infrastructure\Persistence\DBALLessonRepository;
use SovereignStack\Lms\Tests\Integration\DatabaseIntegrationTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 *
 * @coversNothing
 */
final class CourseContentRepositoryTest extends DatabaseIntegrationTestCase
{
    use CourseFixtures;

    private const NOW = '2026-11-15T10:00:00+00:00';

    private string $versionId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->truncateCourseTables();
        $organisationId = $this->insertOrganisation('content-'.bin2hex(random_bytes(4)));
        $userId = $this->insertUser('content-'.bin2hex(random_bytes(4)).'@example.com');
        $courseId = $this->insertCourseRow($organisationId, $userId, 'content-'.bin2hex(random_bytes(4)));
        $this->versionId = $this->insertVersionRow($courseId, $userId);
    }

    public function testModuleCreateListAndNextPosition(): void
    {
        $repository = $this->moduleRepository();
        $versionId = $this->version();

        self::assertSame(0, $repository->nextPosition($versionId));

        $first = $this->module('First', 0);
        $second = $this->module('Second', 1);
        $repository->save($first);
        $repository->save($second);

        self::assertSame(2, $repository->nextPosition($versionId));

        $modules = $repository->listByVersion($versionId);
        self::assertSame(['First', 'Second'], array_map(
            static fn (CourseModule $module): string => $module->title(),
            $modules,
        ));

        $found = $repository->findById($first->id());
        self::assertNotNull($found);
        self::assertTrue($found->isRequired());
        self::assertNull($found->description());
    }

    public function testModuleUpdatePersistsChanges(): void
    {
        $repository = $this->moduleRepository();
        $module = $this->module('First', 0);
        $repository->save($module);

        $module->rename('Renamed', new \DateTimeImmutable('2026-11-16T10:00:00+00:00'));
        $module->changeRequirement(false, new \DateTimeImmutable('2026-11-16T10:00:00+00:00'));
        $repository->save($module);

        $found = $repository->findById($module->id());
        self::assertNotNull($found);
        self::assertSame('Renamed', $found->title());
        self::assertFalse($found->isRequired());
    }

    public function testUniqueModulePositionIsEnforced(): void
    {
        $repository = $this->moduleRepository();
        $repository->save($this->module('First', 0));

        $this->expectException(UniqueConstraintViolationException::class);

        $repository->save($this->module('Clashing', 0));
    }

    public function testModuleReorderReversesOrderWithoutUniqueCollisions(): void
    {
        $repository = $this->moduleRepository();
        $versionId = $this->version();

        $first = $this->module('First', 0);
        $second = $this->module('Second', 1);
        $third = $this->module('Third', 2);
        $repository->save($first);
        $repository->save($second);
        $repository->save($third);

        $repository->reorder($versionId, [$third->id(), $second->id(), $first->id()]);

        $modules = $repository->listByVersion($versionId);
        self::assertSame(
            [$third->id()->value(), $second->id()->value(), $first->id()->value()],
            array_map(static fn (CourseModule $module): string => $module->id()->value(), $modules),
        );
        self::assertSame([0, 1, 2], array_map(
            static fn (CourseModule $module): int => $module->position(),
            $modules,
        ));
    }

    public function testModuleReorderRejectsIncompleteOrDuplicateLists(): void
    {
        $repository = $this->moduleRepository();
        $versionId = $this->version();

        $first = $this->module('First', 0);
        $second = $this->module('Second', 1);
        $repository->save($first);
        $repository->save($second);

        try {
            $repository->reorder($versionId, [$first->id()]);
            self::fail('Reordering with a partial list must be rejected.');
        } catch (\InvalidArgumentException) {
            self::addToAssertionCount(1);
        }

        $this->expectException(\InvalidArgumentException::class);

        $repository->reorder($versionId, [$first->id(), $first->id()]);
    }

    public function testLessonCreateListAndNextPosition(): void
    {
        $moduleRepository = $this->moduleRepository();
        $lessonRepository = $this->lessonRepository();
        $module = $this->module('Module', 0);
        $moduleRepository->save($module);

        self::assertSame(0, $lessonRepository->nextPosition($module->id()));

        $lessonRepository->save($this->lesson($module->id(), 'Welcome', 0));
        $lessonRepository->save($this->lesson($module->id(), 'Setup', 1));

        self::assertSame(2, $lessonRepository->nextPosition($module->id()));

        $titles = array_map(
            static fn (Lesson $lesson): string => $lesson->title(),
            $lessonRepository->listByModule($module->id()),
        );
        self::assertSame(['Welcome', 'Setup'], $titles);
    }

    public function testUniqueLessonPositionIsEnforced(): void
    {
        $moduleRepository = $this->moduleRepository();
        $lessonRepository = $this->lessonRepository();
        $module = $this->module('Module', 0);
        $moduleRepository->save($module);
        $lessonRepository->save($this->lesson($module->id(), 'Welcome', 0));

        $this->expectException(UniqueConstraintViolationException::class);

        $lessonRepository->save($this->lesson($module->id(), 'Clashing', 0));
    }

    public function testLessonReorderReversesOrder(): void
    {
        $moduleRepository = $this->moduleRepository();
        $lessonRepository = $this->lessonRepository();
        $module = $this->module('Module', 0);
        $moduleRepository->save($module);

        $first = $this->lesson($module->id(), 'First', 0);
        $second = $this->lesson($module->id(), 'Second', 1);
        $lessonRepository->save($first);
        $lessonRepository->save($second);

        $lessonRepository->reorder($module->id(), [$second->id(), $first->id()]);

        $lessons = $lessonRepository->listByModule($module->id());
        self::assertSame(['Second', 'First'], array_map(
            static fn (Lesson $lesson): string => $lesson->title(),
            $lessons,
        ));
        self::assertSame([0, 1], array_map(
            static fn (Lesson $lesson): int => $lesson->position(),
            $lessons,
        ));
    }

    public function testLessonContentSaveFindAndReplace(): void
    {
        $repository = $this->contentRepository();
        $lessonId = LessonId::fromString($this->createLesson());

        $content = LessonContent::create(
            Uuid::v7()->toRfc4122(),
            $lessonId,
            '<p>Hello</p>',
            ['format' => 'html'],
            new \DateTimeImmutable(self::NOW),
        );
        $repository->save($content);

        $found = $repository->findByLesson($lessonId);
        self::assertNotNull($found);
        self::assertSame('<p>Hello</p>', $found->contentHtml());
        self::assertSame(['format' => 'html'], $found->metadata());

        $content->replaceContent('<p>Updated</p>', ['format' => 'html', 'revision' => 2], new \DateTimeImmutable('2026-11-16T10:00:00+00:00'));
        $repository->save($content);

        $reloaded = $repository->findByLesson($lessonId);
        self::assertNotNull($reloaded);
        self::assertSame('<p>Updated</p>', $reloaded->contentHtml());
        self::assertSame(['format' => 'html', 'revision' => 2], $reloaded->metadata());
        self::assertEquals(new \DateTimeImmutable('2026-11-16T10:00:00+00:00'), $reloaded->updatedAt());
    }

    public function testLessonContentIsUniquePerLesson(): void
    {
        $repository = $this->contentRepository();
        $lessonId = LessonId::fromString($this->createLesson());

        $repository->save(LessonContent::create(Uuid::v7()->toRfc4122(), $lessonId, '<p>One</p>', [], new \DateTimeImmutable(self::NOW)));

        $this->expectException(UniqueConstraintViolationException::class);

        $repository->save(LessonContent::create(Uuid::v7()->toRfc4122(), $lessonId, '<p>Two</p>', [], new \DateTimeImmutable(self::NOW)));
    }

    public function testDeletingLessonCascadesContent(): void
    {
        $lessonRepository = $this->lessonRepository();
        $contentRepository = $this->contentRepository();
        $moduleRepository = $this->moduleRepository();

        $module = $this->module('Module', 0);
        $moduleRepository->save($module);
        $lesson = $this->lesson($module->id(), 'Welcome', 0);
        $lessonRepository->save($lesson);
        $contentRepository->save(LessonContent::create(
            Uuid::v7()->toRfc4122(),
            $lesson->id(),
            '<p>Hello</p>',
            [],
            new \DateTimeImmutable(self::NOW),
        ));

        $lessonRepository->delete($lesson->id());

        self::assertNull($lessonRepository->findById($lesson->id()));
        self::assertNull($contentRepository->findByLesson($lesson->id()));
        self::assertSame(0, $this->fetchInt('SELECT COUNT(*) FROM lesson_contents WHERE lesson_id = :id', ['id' => $lesson->id()->value()]));
    }

    public function testDeletingModuleCascadesLessonsAndContent(): void
    {
        $moduleRepository = $this->moduleRepository();
        $lessonRepository = $this->lessonRepository();
        $contentRepository = $this->contentRepository();

        $module = $this->module('Module', 0);
        $moduleRepository->save($module);
        $lesson = $this->lesson($module->id(), 'Welcome', 0);
        $lessonRepository->save($lesson);
        $contentRepository->save(LessonContent::create(
            Uuid::v7()->toRfc4122(),
            $lesson->id(),
            '<p>Hello</p>',
            [],
            new \DateTimeImmutable(self::NOW),
        ));

        $moduleRepository->delete($module->id());

        self::assertSame(0, $this->fetchInt('SELECT COUNT(*) FROM lessons WHERE course_module_id = :id', ['id' => $module->id()->value()]));
        self::assertSame(0, $this->fetchInt('SELECT COUNT(*) FROM lesson_contents WHERE lesson_id = :id', ['id' => $lesson->id()->value()]));
    }

    private function moduleRepository(): DBALCourseModuleRepository
    {
        return new DBALCourseModuleRepository($this->connection());
    }

    private function lessonRepository(): DBALLessonRepository
    {
        return new DBALLessonRepository($this->connection());
    }

    private function contentRepository(): DBALLessonContentRepository
    {
        return new DBALLessonContentRepository($this->connection());
    }

    private function version(): CourseVersionId
    {
        return CourseVersionId::fromString($this->versionId);
    }

    private function module(string $title, int $position): CourseModule
    {
        return CourseModule::create(
            CourseModuleId::fromString(Uuid::v7()->toRfc4122()),
            $this->version(),
            $title,
            null,
            $position,
            true,
            new \DateTimeImmutable(self::NOW),
        );
    }

    private function lesson(CourseModuleId $moduleId, string $title, int $position): Lesson
    {
        return Lesson::create(
            LessonId::fromString(Uuid::v7()->toRfc4122()),
            $moduleId,
            $title,
            $position,
            new \DateTimeImmutable(self::NOW),
        );
    }

    private function createLesson(): string
    {
        $module = $this->module('Module', 0);
        $this->moduleRepository()->save($module);
        $lesson = $this->lesson($module->id(), 'Welcome', 0);
        $this->lessonRepository()->save($lesson);

        return $lesson->id()->value();
    }
}
