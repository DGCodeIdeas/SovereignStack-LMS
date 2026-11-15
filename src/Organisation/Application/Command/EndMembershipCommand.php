<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Organisation\Application\Command;

final readonly class EndMembershipCommand
{
    public function __construct(
        public string $actorUserId,
        public string $organisationId,
        public string $userId,
    ) {}
}
