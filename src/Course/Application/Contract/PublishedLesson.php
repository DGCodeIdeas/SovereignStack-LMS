<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Contract;

final readonly class PublishedLesson
{
    public function __construct(
        public string $id,
        public string $title,
    ) {}
}
