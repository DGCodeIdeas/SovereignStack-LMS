<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Domain\Repository;

use SovereignStack\Lms\Course\Domain\Entity\Lesson;
use SovereignStack\Lms\Course\Domain\ValueObject\CourseModuleId;
use SovereignStack\Lms\Course\Domain\ValueObject\LessonId;

interface LessonRepositoryInterface
{
    public function save(Lesson $lesson): void;

    public function findById(LessonId $id): ?Lesson;

    /**
     * @return list<Lesson>
     */
    public function listByModule(CourseModuleId $courseModuleId): array;

    public function delete(LessonId $id): void;

    public function nextPosition(CourseModuleId $courseModuleId): int;

    /**
     * Applies the given order to every lesson of the module. The list must
     * contain exactly the ids currently stored for that module.
     *
     * @param list<LessonId> $lessonIds
     */
    public function reorder(CourseModuleId $courseModuleId, array $lessonIds): void;
}
