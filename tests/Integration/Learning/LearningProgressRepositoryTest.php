<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Integration\Learning;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use SovereignStack\Lms\Learning\Domain\Entity\LessonProgress;
use SovereignStack\Lms\Learning\Domain\Entity\ModuleProgress;
use SovereignStack\Lms\Learning\Domain\Enum\ProgressStatus;
use SovereignStack\Lms\Learning\Domain\Exception\ProgressConflictException;
use SovereignStack\Lms\Learning\Domain\ValueObject\LessonProgressId;
use SovereignStack\Lms\Learning\Domain\ValueObject\ModuleProgressId;
use SovereignStack\Lms\Learning\Infrastructure\Persistence\DBALLessonProgressRepository;
use SovereignStack\Lms\Learning\Infrastructure\Persistence\DBALModuleProgressRepository;
use SovereignStack\Lms\Tests\Integration\DatabaseIntegrationTestCase;
use SovereignStack\Lms\Tests\Integration\Enrollment\EnrollmentFixtures;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 *
 * @coversNothing
 */
final class LearningProgressRepositoryTest extends DatabaseIntegrationTestCase
{
    use EnrollmentFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->truncateEnrollmentTables();
    }

    public function testModuleProgressSaveFindListAndUpdate(): void
    {
        $context = $this->seedEnrollmentContext('module');
        $enrollmentId = $this->insertEnrollmentRow(
            $context['organisationId'],
            $context['learnerUserId'],
            $context['courseId'],
            $context['courseVersionId'],
        );
        $content = $this->seedModuleAndLesson($context['courseVersionId'], 'module');
        $repository = $this->moduleRepository();

        $progress = $this->moduleProgress($enrollmentId, $content['moduleId']);
        $repository->save($progress);

        $found = $repository->findById($progress->id());
        self::assertNotNull($found);
        self::assertSame($enrollmentId, $found->enrollmentId());
        self::assertSame($content['moduleId'], $found->courseModuleId());
        self::assertSame(ProgressStatus::NOT_STARTED, $found->status());
        self::assertSame(1, $found->version());

        $startedAt = new \DateTimeImmutable('2026-11-16T11:00:00+00:00');
        $progress->markStarted($startedAt);
        $repository->save($progress);

        $found = $repository->findById($progress->id());
        self::assertNotNull($found);
        self::assertSame(ProgressStatus::IN_PROGRESS, $found->status());
        self::assertEquals($startedAt, $found->startedAt());
        self::assertSame(2, $found->version());

        $listed = $repository->listByEnrollment($enrollmentId);
        self::assertCount(1, $listed);
        self::assertSame($progress->id()->value(), $listed[0]->id()->value());
        self::assertNotNull($repository->findByEnrollmentAndModule($enrollmentId, $content['moduleId']));
    }

    public function testModuleProgressFindForUpdate(): void
    {
        $context = $this->seedEnrollmentContext('module-lock');
        $enrollmentId = $this->insertEnrollmentRow(
            $context['organisationId'],
            $context['learnerUserId'],
            $context['courseId'],
            $context['courseVersionId'],
        );
        $content = $this->seedModuleAndLesson($context['courseVersionId'], 'module-lock');
        $repository = $this->moduleRepository();
        $progress = $this->moduleProgress($enrollmentId, $content['moduleId']);
        $repository->save($progress);

        $found = $repository->findForUpdate($enrollmentId, $content['moduleId']);

        self::assertNotNull($found);
        self::assertSame($progress->id()->value(), $found->id()->value());
    }

    public function testModuleProgressOptimisticConflict(): void
    {
        $context = $this->seedEnrollmentContext('module-conflict');
        $enrollmentId = $this->insertEnrollmentRow(
            $context['organisationId'],
            $context['learnerUserId'],
            $context['courseId'],
            $context['courseVersionId'],
        );
        $content = $this->seedModuleAndLesson($context['courseVersionId'], 'module-conflict');
        $repository = $this->moduleRepository();

        $progress = $this->moduleProgress($enrollmentId, $content['moduleId']);
        $repository->save($progress);
        $stale = $repository->findById($progress->id());
        self::assertNotNull($stale);

        $progress->markStarted(new \DateTimeImmutable('2026-11-16T11:00:00+00:00'));
        $repository->save($progress);

        $stale->markStarted(new \DateTimeImmutable('2026-11-16T11:01:00+00:00'));

        $this->expectException(ProgressConflictException::class);

        $repository->save($stale);
    }

    public function testModuleProgressIsUniquePerEnrollmentAndModule(): void
    {
        $context = $this->seedEnrollmentContext('module-unique');
        $enrollmentId = $this->insertEnrollmentRow(
            $context['organisationId'],
            $context['learnerUserId'],
            $context['courseId'],
            $context['courseVersionId'],
        );
        $content = $this->seedModuleAndLesson($context['courseVersionId'], 'module-unique');
        $repository = $this->moduleRepository();
        $repository->save($this->moduleProgress($enrollmentId, $content['moduleId']));

        $this->expectException(UniqueConstraintViolationException::class);

        $repository->save($this->moduleProgress($enrollmentId, $content['moduleId']));
    }

    public function testLessonProgressSaveFindListAndProgressData(): void
    {
        $context = $this->seedEnrollmentContext('lesson');
        $enrollmentId = $this->insertEnrollmentRow(
            $context['organisationId'],
            $context['learnerUserId'],
            $context['courseId'],
            $context['courseVersionId'],
        );
        $content = $this->seedModuleAndLesson($context['courseVersionId'], 'lesson');
        $secondLessonId = $this->insertLessonRow($content['moduleId'], 'Second Lesson', 1);
        $repository = $this->lessonRepository();

        $progress = $this->lessonProgress($enrollmentId, $content['lessonId']);
        $repository->save($progress);

        $found = $repository->findById($progress->id());
        self::assertNotNull($found);
        self::assertSame($enrollmentId, $found->enrollmentId());
        self::assertSame($content['lessonId'], $found->lessonId());
        self::assertSame(ProgressStatus::NOT_STARTED, $found->status());
        self::assertSame([], $found->progressData());

        $startedAt = new \DateTimeImmutable('2026-11-16T11:00:00+00:00');
        $updatedAt = new \DateTimeImmutable('2026-11-16T11:02:00+00:00');
        $progress->markStarted($startedAt);
        $progress->replaceProgressData(['position' => 2, 'answers' => ['a', 'b']], $updatedAt);
        $repository->save($progress);

        $found = $repository->findById($progress->id());
        self::assertNotNull($found);
        self::assertSame(ProgressStatus::IN_PROGRESS, $found->status());
        self::assertEquals($startedAt, $found->startedAt());
        self::assertEquals($updatedAt, $found->updatedAt());
        self::assertSame(
            self::sortKeysRecursively(['position' => 2, 'answers' => ['a', 'b']]),
            self::sortKeysRecursively($found->progressData()),
        );
        self::assertSame(2, $found->version());

        $second = $this->lessonProgress($enrollmentId, $secondLessonId);
        $repository->save($second);

        $listed = $repository->listByEnrollment($enrollmentId);
        self::assertCount(2, $listed);

        $listedLessonIds = array_map(static fn (LessonProgress $item): string => $item->lessonId(), $listed);
        $expectedLessonIds = [$content['lessonId'], $secondLessonId];
        sort($listedLessonIds);
        sort($expectedLessonIds);
        self::assertSame($expectedLessonIds, $listedLessonIds);

        $forModule = $repository->listByEnrollmentAndModule($enrollmentId, $content['moduleId']);
        self::assertCount(2, $forModule);
        self::assertSame(
            [$content['lessonId'], $secondLessonId],
            array_map(static fn (LessonProgress $item): string => $item->lessonId(), $forModule),
        );
        self::assertNotNull($repository->findByEnrollmentAndLesson($enrollmentId, $content['lessonId']));
    }

    public function testLessonProgressOptimisticConflict(): void
    {
        $context = $this->seedEnrollmentContext('lesson-conflict');
        $enrollmentId = $this->insertEnrollmentRow(
            $context['organisationId'],
            $context['learnerUserId'],
            $context['courseId'],
            $context['courseVersionId'],
        );
        $content = $this->seedModuleAndLesson($context['courseVersionId'], 'lesson-conflict');
        $repository = $this->lessonRepository();

        $progress = $this->lessonProgress($enrollmentId, $content['lessonId']);
        $repository->save($progress);
        $stale = $repository->findById($progress->id());
        self::assertNotNull($stale);

        $progress->markStarted(new \DateTimeImmutable('2026-11-16T11:00:00+00:00'));
        $repository->save($progress);

        $stale->markStarted(new \DateTimeImmutable('2026-11-16T11:01:00+00:00'));

        $this->expectException(ProgressConflictException::class);

        $repository->save($stale);
    }

    public function testLessonProgressIsUniquePerEnrollmentAndLesson(): void
    {
        $context = $this->seedEnrollmentContext('lesson-unique');
        $enrollmentId = $this->insertEnrollmentRow(
            $context['organisationId'],
            $context['learnerUserId'],
            $context['courseId'],
            $context['courseVersionId'],
        );
        $content = $this->seedModuleAndLesson($context['courseVersionId'], 'lesson-unique');
        $repository = $this->lessonRepository();
        $repository->save($this->lessonProgress($enrollmentId, $content['lessonId']));

        $this->expectException(UniqueConstraintViolationException::class);

        $repository->save($this->lessonProgress($enrollmentId, $content['lessonId']));
    }

    public function testMissingProgressReturnsNull(): void
    {
        $context = $this->seedEnrollmentContext('missing');
        $enrollmentId = $this->insertEnrollmentRow(
            $context['organisationId'],
            $context['learnerUserId'],
            $context['courseId'],
            $context['courseVersionId'],
        );
        $content = $this->seedModuleAndLesson($context['courseVersionId'], 'missing');

        self::assertNull($this->moduleRepository()->findById(ModuleProgressId::fromString(Uuid::v7()->toRfc4122())));
        self::assertNull($this->moduleRepository()->findByEnrollmentAndModule($enrollmentId, $content['moduleId']));
        self::assertNull($this->lessonRepository()->findById(LessonProgressId::fromString(Uuid::v7()->toRfc4122())));
        self::assertNull($this->lessonRepository()->findByEnrollmentAndLesson($enrollmentId, $content['lessonId']));
    }

    private function moduleRepository(): DBALModuleProgressRepository
    {
        return new DBALModuleProgressRepository($this->connection());
    }

    private function lessonRepository(): DBALLessonProgressRepository
    {
        return new DBALLessonProgressRepository($this->connection());
    }

    private function moduleProgress(string $enrollmentId, string $courseModuleId): ModuleProgress
    {
        return ModuleProgress::create(
            ModuleProgressId::fromString(Uuid::v7()->toRfc4122()),
            $enrollmentId,
            $courseModuleId,
            new \DateTimeImmutable('2026-11-16T10:30:00+00:00'),
        );
    }

    private function lessonProgress(string $enrollmentId, string $lessonId): LessonProgress
    {
        return LessonProgress::create(
            LessonProgressId::fromString(Uuid::v7()->toRfc4122()),
            $enrollmentId,
            $lessonId,
            new \DateTimeImmutable('2026-11-16T10:30:00+00:00'),
        );
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private static function sortKeysRecursively(array $data): array
    {
        ksort($data);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::sortKeysRecursively($value);
            }
        }

        return $data;
    }
}
