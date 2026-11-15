<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Course\Application\Fakes;

use SovereignStack\Lms\Course\Domain\Entity\Lesson;
use SovereignStack\Lms\Course\Domain\Repository\LessonRepositoryInterface;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseModuleId;
use SovereignStack\Lms\Course\Domain\ValueObject\LessonId;

final class InMemoryLessonRepository implements LessonRepositoryInterface
{
    /**
     * @var array<string, Lesson>
     */
    public array $lessons = [];

    public function save(Lesson $lesson): void
    {
        $this->lessons[$lesson->id()->value()] = $lesson;
    }

    public function findById(LessonId $id): ?Lesson
    {
        return $this->lessons[$id->value()] ?? null;
    }

    public function listByModule(CourseModuleId $courseModuleId): array
    {
        $result = [];

        foreach ($this->lessons as $lesson) {
            if ($lesson->courseModuleId()->equals($courseModuleId)) {
                $result[] = $lesson;
            }
        }

        usort($result, static fn (Lesson $left, Lesson $right): int => $left->position() <=> $right->position());

        return $result;
    }

    public function delete(LessonId $id): void
    {
        unset($this->lessons[$id->value()]);
    }

    public function nextPosition(CourseModuleId $courseModuleId): int
    {
        $position = 0;

        foreach ($this->lessons as $lesson) {
            if ($lesson->courseModuleId()->equals($courseModuleId)) {
                $position = max($position, $lesson->position() + 1);
            }
        }

        return $position;
    }

    public function reorder(CourseModuleId $courseModuleId, array $lessonIds): void
    {
        foreach ($lessonIds as $position => $lessonId) {
            $lesson = $this->lessons[$lessonId->value()] ?? throw new \InvalidArgumentException('Unknown lesson in reorder list.');

            if (!$lesson->courseModuleId()->equals($courseModuleId)) {
                throw new \InvalidArgumentException('Lesson does not belong to the reordered module.');
            }

            $lesson->changePosition($position, new \DateTimeImmutable('2026-11-15T00:00:00+00:00'));
        }
    }
}
