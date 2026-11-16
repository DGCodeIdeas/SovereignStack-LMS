<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Course\Application\Query;

final readonly class ListCoursesQuery
{
    public function __construct(
        public string $actorUserId,
        public string $organisationId,
    ) {}
}
