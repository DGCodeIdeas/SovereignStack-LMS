<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Tests\Unit\Course\Application\Fakes;

use SovereignStack\Lms\Course\Domain\Entity\LessonContent;
use SovereignStack\Lms\Course\Domain\Repository\LessonContentRepositoryInterface;
use SovereignStack\Lms\Course\Domain\ValueObject\LessonId;

final class InMemoryLessonContentRepository implements LessonContentRepositoryInterface
{
    /**
     * @var array<string, LessonContent>
     */
    public array $contents = [];

    public function save(LessonContent $content): void
    {
        $this->contents[$content->id()] = $content;
    }

    public function findByLesson(LessonId $lessonId): ?LessonContent
    {
        foreach ($this->contents as $content) {
            if ($content->lessonId()->equals($lessonId)) {
                return $content;
            }
        }

        return null;
    }
}
