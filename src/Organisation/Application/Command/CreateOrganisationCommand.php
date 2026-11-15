<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Organisation\Application\Command;

final readonly class CreateOrganisationCommand
{
    public function __construct(
        public string $actorUserId,
        public string $name,
        public string $slug,
    ) {}
}
