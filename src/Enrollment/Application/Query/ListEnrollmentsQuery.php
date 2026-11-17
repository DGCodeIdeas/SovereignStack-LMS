<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Enrollment\Application\Query;

final readonly class ListEnrollmentsQuery
{
    public function __construct(
        public string $actorUserId,
    ) {}
}
