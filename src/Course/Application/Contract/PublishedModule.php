<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Contract;

final readonly class PublishedModule
{
    /**
     * @param list<PublishedLesson> $lessons
     */
    public function __construct(
        public string $id,
        public string $title,
        public bool $isRequired,
        public array $lessons,
    ) {}
}
