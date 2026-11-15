<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Domain\Repository;

use SovereignStack\Lms\Course\Domain\Entity\LessonContent;
use SovereignStack\Lms\Course\Domain\ValueObject\LessonId;

interface LessonContentRepositoryInterface
{
    public function save(LessonContent $content): void;

    public function findByLesson(LessonId $lessonId): ?LessonContent;
}
