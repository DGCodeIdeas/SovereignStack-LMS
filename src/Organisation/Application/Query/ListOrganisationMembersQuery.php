<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Organisation\Application\Query;

final readonly class ListOrganisationMembersQuery
{
    public function __construct(
        public string $actorUserId,
        public string $organisationId,
    ) {}
}
