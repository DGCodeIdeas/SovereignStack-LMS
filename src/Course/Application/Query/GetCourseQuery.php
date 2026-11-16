<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Query;

final readonly class GetCourseQuery
{
    public function __construct(
        public string $actorUserId,
        public string $courseId,
    ) {}
}
