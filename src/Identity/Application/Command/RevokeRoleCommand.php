<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Application\Command;

final readonly class RevokeRoleCommand
{
    public function __construct(
        public string $actorUserId,
        public string $targetUserId,
        public string $roleCode,
        public ?string $organisationId,
    ) {}
}
